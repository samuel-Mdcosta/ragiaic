<?php

namespace App\Providers;

use App\Models\Questao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Normalizer;

/**
 * Importação do banco de questões a partir dos arquivos do pipeline de geração. Idempotente: a questão é
 * identificada pelo conteúdo (`codigo`), então reimportar atualiza a linha em vez de duplicar, e o
 * histórico dos alunos continua apontando para ela.
 */
class ImportacaoQuestoesService
{
    public function __construct(
        private ReferenciasService $referencias,
        private TextoAlunoService $texto,
    ) {
    }

    /**
     * Identificador do conteúdo pela MESMA regra do pipeline (`hash_questao`): ignora maiúsculas, espaços
     * repetidos e a ordem das alternativas.
     */
    public static function codigo(string $casoClinico, string $comando, string $tema): string
    {
        $normalizar = function (string $texto): string {
            if (class_exists(Normalizer::class)) {
                $texto = Normalizer::normalize($texto, Normalizer::FORM_C) ?: $texto;
            }

            return mb_convert_case(trim(preg_replace('/\s+/u', ' ', $texto)), MB_CASE_FOLD, 'UTF-8');
        };

        return 'sha256:' . hash('sha256', implode('|', array_map($normalizar, [$casoClinico, $comando, $tema])));
    }

    /**
     * Lê um arquivo de questões: `{"questoes": [...]}` (formato plano, como questoes_40_por_tema.json) ou uma
     * lista de registros (também no formato antigo do pipeline, com os campos dentro de `question`).
     *
     * @return list<array<string, mixed>>
     */
    public function lerArquivo(string $caminho): array
    {
        if (! is_file($caminho)) {
            throw new InvalidArgumentException("Arquivo não encontrado: {$caminho}");
        }

        $dados = json_decode(file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
        $lista = is_array($dados) && array_key_exists('questoes', $dados) ? $dados['questoes'] : $dados;
        if (! is_array($lista) || ! array_is_list($lista)) {
            throw new InvalidArgumentException('O arquivo deve trazer uma lista de questões (ou {"questoes": [...]}).');
        }

        return $lista;
    }

    /**
     * Catálogo de livros (database/data/livros.json): metadados por livro e nomes de arquivo equivalentes.
     *
     * @return array{catalogo: array<string, array<string, mixed>>, equivalentes: array<string, string>}
     */
    public function lerCatalogo(string $caminho): array
    {
        $dados = is_file($caminho) ? json_decode(file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR) : [];

        return [
            'catalogo'     => $this->referencias->catalogo($dados['livros'] ?? []),
            'equivalentes' => $dados['equivalentes'] ?? [],
        ];
    }

    /**
     * Valida os registros e os converte em linhas da tabela `questoes`.
     *
     * @return array{validas: list<array<string, mixed>>, rejeitadas: list<array{posicao: int, problemas: list<string>}>}
     */
    public function preparar(array $registros, array $catalogo, array $equivalentes): array
    {
        $validas = [];
        $rejeitadas = [];
        $vistos = [];

        foreach ($registros as $i => $bruto) {
            $registro = is_array($bruto) ? $this->normalizar($bruto) : [];
            $problemas = $registro ? $this->problemas($registro) : ['registro não é um objeto JSON'];

            if (! $problemas) {
                $linha = $this->linha($registro, $bruto, $catalogo, $equivalentes);
                if (isset($vistos[$linha['codigo']])) {
                    $problemas[] = 'questão repetida no arquivo (mesmo caso clínico, comando e tema da posição ' . $vistos[$linha['codigo']] . ')';
                } else {
                    $vistos[$linha['codigo']] = $i + 1;
                    $validas[] = $linha;
                }
            }

            if ($problemas) {
                $rejeitadas[] = ['posicao' => $i + 1, 'problemas' => $problemas];
            }
        }

        return ['validas' => $validas, 'rejeitadas' => $rejeitadas];
    }

    /**
     * Grava as questões (tudo ou nada) e recalcula as irmãs.
     *
     * O status só avança: `revisada` promove as que já existem; qualquer outro status vale só para as novas.
     * Assim, reimportar nunca desfaz uma revisão já registrada. Com `$somenteEstas`, as questões do banco que
     * não estão no arquivo são arquivadas (nada é apagado: o histórico dos alunos continua apontando para elas).
     *
     * @return array{inseridas: int, atualizadas: int, arquivadas: int, com_irma: int, pares_de_irmas: int}
     */
    public function importar(array $linhas, string $status, bool $somenteEstas, float $limiarIrmas): array
    {
        return DB::transaction(function () use ($linhas, $status, $somenteEstas, $limiarIrmas) {
            $codigos = array_column($linhas, 'codigo');
            $existentes = Questao::whereIn('codigo', $codigos)->get()->keyBy('codigo');
            $inseridas = 0;

            foreach ($linhas as $linha) {
                $questao = $existentes->get($linha['codigo']);
                if ($questao === null) {
                    Questao::create($linha + ['status' => $status]);
                    $inseridas++;
                    continue;
                }
                $questao->fill($linha);
                if ($status === Questao::STATUS_SERVIDO) {
                    $questao->status = $status;
                }
                $questao->save();
            }

            $arquivadas = 0;
            if ($somenteEstas) {
                $arquivadas = Questao::whereNotIn('codigo', $codigos)
                    ->where('status', '!=', 'arquivada')
                    ->update(['status' => 'arquivada', 'updated_at' => now()]);
            }

            [$comIrma, $pares] = $this->recalcularIrmas($limiarIrmas);

            return [
                'inseridas'      => $inseridas,
                'atualizadas'    => count($linhas) - $inseridas,
                'arquivadas'     => $arquivadas,
                'com_irma'       => $comIrma,
                'pares_de_irmas' => $pares,
            ];
        });
    }

    /**
     * Questões "irmãs": mesmo tema e trechos de livro parecidos (Jaccard dos `chunk_id` >= limiar). Foram
     * escritas a partir das mesmas passagens, então tendem a cobrir o mesmo conceito com casos clínicos
     * diferentes. É uma aproximação: não há um campo de "conceito" nos dados. A relação é simétrica.
     *
     * @param  list<array{id: int|string, tema: string, referencias: array}>  $questoes
     * @return array<int|string, array<int|string, float>> id => [id da irmã => semelhança]
     */
    public static function agruparIrmas(array $questoes, float $limiar): array
    {
        $itens = [];
        foreach ($questoes as $q) {
            $trechos = [];
            foreach ($q['referencias'] ?? [] as $ref) {
                if (! empty($ref['chunk_id'])) {
                    $trechos[(string) $ref['chunk_id']] = true;
                }
            }
            $itens[] = [$q['id'], $q['tema'], $trechos];
        }

        $porTrecho = [];
        foreach ($itens as $i => [, , $trechos]) {
            foreach (array_keys($trechos) as $trecho) {
                $porTrecho[$trecho][] = $i;
            }
        }

        $irmas = [];
        foreach ($itens as $i => [$id, $tema, $trechos]) {
            $candidatas = [];
            foreach (array_keys($trechos) as $trecho) {
                foreach ($porTrecho[$trecho] as $j) {
                    if ($j !== $i && $itens[$j][1] === $tema) {
                        $candidatas[$j] = true;
                    }
                }
            }
            foreach (array_keys($candidatas) as $j) {
                $outros = $itens[$j][2];
                $semelhanca = count(array_intersect_key($trechos, $outros)) / count($trechos + $outros);
                if ($semelhanca >= $limiar && $itens[$j][0] !== $id) {
                    $irmas[$id][$itens[$j][0]] = round($semelhanca, 4);
                }
            }
        }

        return $irmas;
    }

    /**
     * Recalcula `questoes_irmas` sobre todas as questões não arquivadas (a relação depende do conjunto inteiro).
     *
     * @return array{0: int, 1: int} [questões com ao menos uma irmã, linhas gravadas (os dois sentidos)]
     */
    public function recalcularIrmas(float $limiar): array
    {
        $questoes = Questao::where('status', '!=', 'arquivada')
            ->get(['id', 'tema', 'referencias'])
            ->map(fn (Questao $q) => ['id' => $q->id, 'tema' => $q->tema, 'referencias' => $q->referencias])
            ->all();

        $linhas = [];
        foreach (self::agruparIrmas($questoes, $limiar) as $id => $outras) {
            foreach ($outras as $irma => $semelhanca) {
                $linhas[] = ['questao_id' => $id, 'irma_id' => $irma, 'similaridade' => $semelhanca];
            }
        }

        DB::table('questoes_irmas')->delete();
        foreach (array_chunk($linhas, 250) as $lote) {
            DB::table('questoes_irmas')->insert($lote);
        }

        return [count(array_unique(array_column($linhas, 'questao_id'))), count($linhas)];
    }

    /** Campos da questão no nível de cima (o formato antigo do pipeline os guarda em `question`). */
    private function normalizar(array $registro): array
    {
        return isset($registro['question']) && is_array($registro['question'])
            ? $registro['question'] + $registro
            : $registro;
    }

    /** @return list<string> */
    private function problemas(array $registro): array
    {
        $validador = Validator::make($registro, [
            'tema'                           => 'required|string|max:255',
            'subtema'                        => 'required|string|max:255',
            'dificuldade'                    => ['required', Rule::in(Questao::DIFICULDADES)],
            'objetivo_aprendizagem'          => 'required|string',
            'referencias'                    => 'present|array',
            'referencias.*.file_name'        => 'required|string',
            'referencias.*.chunk_id'         => 'nullable|string',
            'item'                           => 'required|array',
            'item.caso_clinico'              => 'required|string',
            'item.comando'                   => 'required|string',
            'item.justificativa_gabarito'    => 'required|string',
            'item.alternativas'              => 'required|array|size:4',
            'item.alternativas.*.letra'      => ['required', Rule::in(Questao::LETRAS)],
            'item.alternativas.*.texto'      => 'required|string',
            'item.alternativas.*.correta'    => 'required|boolean',
            'item.alternativas.*.explicacao' => 'nullable|string',
        ]);
        $problemas = $validador->errors()->all();

        $alternativas = $registro['item']['alternativas'] ?? null;
        if (is_array($alternativas) && count($alternativas) === 4 && ! $problemas) {
            $letras = array_column($alternativas, 'letra');
            sort($letras);
            if ($letras !== Questao::LETRAS) {
                $problemas[] = 'as alternativas devem ter as letras A, B, C e D, sem repetição';
            }
            $corretas = count(array_filter($alternativas, fn ($a) => ! empty($a['correta'])));
            if ($corretas !== 1) {
                $problemas[] = "deve haver exatamente 1 alternativa correta (há {$corretas})";
            }
        }

        return $problemas;
    }

    private function linha(array $registro, array $bruto, array $catalogo, array $equivalentes): array
    {
        $item = $registro['item'];
        $referencias = $this->referencias->enriquecer($registro['referencias'], $catalogo, $equivalentes);
        $alternativas = collect($item['alternativas'])->sortBy('letra')->values();

        return [
            'codigo'                => $registro['id'] ?? self::codigo($item['caso_clinico'], $item['comando'], $registro['tema']),
            'tema'                  => $registro['tema'],
            'subtema'               => $registro['subtema'],
            'dificuldade'           => $registro['dificuldade'],
            'objetivo_aprendizagem' => $registro['objetivo_aprendizagem'],
            'caso_clinico'          => $item['caso_clinico'],
            'comando'               => $item['comando'],
            'alternativas'          => $alternativas->map(fn (array $a) => [
                'letra'      => $a['letra'],
                'texto'      => $a['texto'],
                'explicacao' => $this->texto->paraAluno($a['explicacao'] ?? '', $referencias),
            ])->all(),
            'gabarito'              => $alternativas->first(fn (array $a) => ! empty($a['correta']))['letra'],
            'justificativa'         => $this->texto->paraAluno($item['justificativa_gabarito'], $referencias),
            'referencias'           => $referencias,
            'lote_id'               => $registro['lote_id'] ?? null,
            'posicao'               => $registro['posicao'] ?? null,
            'gerada_em'             => isset($registro['criado_em']) ? Carbon::parse($registro['criado_em'])->utc() : null,
            'bruto'                 => $bruto,
        ];
    }
}
