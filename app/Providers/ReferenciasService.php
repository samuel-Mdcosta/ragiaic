<?php

namespace App\Providers;

use Illuminate\Support\Str;

/**
 * Referências bibliográficas das questões. Identifica o livro de cada referência apesar das variações no
 * nome do arquivo (".pdf", "Livro ", acentos, "_"), completa os metadados pelo catálogo
 * (database/data/livros.json) e monta os textos mostrados ao aluno: o título curto, para usar dentro de
 * frases, e a citação completa ("Título — Autor (4ª ed., Editora, 2017)").
 */
class ReferenciasService
{
    public const CAMPOS_DA_FONTE = ['titulo', 'autor', 'edicao', 'editora', 'publicacao'];

    public function chaveDoLivro(string $arquivo): string
    {
        $base = strtolower(Str::ascii(str_replace('_', ' ', $this->semPdf($arquivo))));

        return preg_replace('/^livro\s+/u', '', $this->espacosSimples($base));
    }

    /** Último recurso, quando a referência não tem título: o nome do arquivo sem ".pdf", "_" e "Livro ". */
    public function nomeDoLivro(string $arquivo): string
    {
        $base = $this->espacosSimples(str_replace('_', ' ', $this->semPdf($arquivo)));

        return preg_replace('/^livro\s+/iu', '', $base);
    }

    /** "Neurociências: Desvendando o Sistema Nervoso" -> "Neurociências". */
    public function tituloCurto(string $titulo): string
    {
        return trim(explode(':', $titulo, 2)[0]) ?: $titulo;
    }

    /**
     * Catálogo indexado pela chave do livro, a partir de `livros` do livros.json (arquivo => metadados).
     *
     * @param  array<string, array<string, mixed>>  $livros
     * @return array<string, array<string, mixed>>
     */
    public function catalogo(array $livros): array
    {
        $catalogo = [];
        foreach ($livros as $arquivo => $metadados) {
            $catalogo[$this->chaveDoLivro($arquivo)] ??= array_intersect_key($metadados, array_flip(self::CAMPOS_DA_FONTE));
        }

        return $catalogo;
    }

    /**
     * Completa os campos que faltam em cada referência com os do mesmo livro no catálogo, sem sobrescrever o
     * que a referência já tem. `equivalentes` (arquivo => arquivo) cobre o mesmo livro com outro nome de
     * arquivo: a referência passa a usar o nome de destino, para o livro contar como um só.
     */
    public function enriquecer(array $referencias, array $catalogo, array $equivalentes = []): array
    {
        $porChave = [];
        foreach ($equivalentes as $origem => $destino) {
            $porChave[$this->chaveDoLivro($origem)] = $destino;
        }

        return array_map(function (array $ref) use ($catalogo, $porChave) {
            $arquivo = $porChave[$this->chaveDoLivro((string) ($ref['file_name'] ?? ''))] ?? null;
            if ($arquivo) {
                $ref['file_name'] = $arquivo;
            }
            $extra = $catalogo[$this->chaveDoLivro((string) ($ref['file_name'] ?? ''))] ?? [];
            foreach (self::CAMPOS_DA_FONTE as $campo) {
                if (in_array($ref[$campo] ?? null, [null, ''], true) && ! in_array($extra[$campo] ?? null, [null, ''], true)) {
                    $ref[$campo] = $extra[$campo];
                }
            }

            return $ref;
        }, array_values($referencias));
    }

    /** "Título — Autor (4ª ed., Editora, 2017)", com o que houver nos metadados. */
    public function citacao(array $ref): string
    {
        $texto = ($ref['titulo'] ?? null) ?: $this->nomeDoLivro((string) ($ref['file_name'] ?? ''));
        if (! empty($ref['autor'])) {
            $texto .= ' — ' . $this->autores((string) $ref['autor']);
        }

        $detalhes = array_values(array_filter([
            ! empty($ref['edicao']) ? $ref['edicao'] . 'ª ed.' : '',
            ! empty($ref['editora']) ? (string) $ref['editora'] : '',
            ! empty($ref['publicacao']) ? (string) $ref['publicacao'] : '',
        ], fn (string $d) => $d !== ''));

        return $texto . ($detalhes ? ' (' . implode(', ', $detalhes) . ')' : '');
    }

    /**
     * Para usar dentro de frases: [id do trecho => título curto do livro, títulos curtos sem repetição].
     * Referências do mesmo livro contam uma vez; a que tem metadados vence a que só tem o nome do arquivo.
     *
     * @return array{0: array<string, string>, 1: list<string>}
     */
    public function fontesDaQuestao(array $referencias): array
    {
        $nomePorLivro = [];
        foreach ($referencias as $ref) {
            $chave = $this->chaveDoLivro((string) ($ref['file_name'] ?? ''));
            if (! empty($ref['titulo'])) {
                $nomePorLivro[$chave] = $this->tituloCurto((string) $ref['titulo']);
            } elseif (! array_key_exists($chave, $nomePorLivro)) {
                $nomePorLivro[$chave] = $this->nomeDoLivro((string) ($ref['file_name'] ?? ''));
            }
        }

        $porId = [];
        foreach ($referencias as $ref) {
            $nome = $nomePorLivro[$this->chaveDoLivro((string) ($ref['file_name'] ?? ''))] ?? '';
            if ($nome !== '') {
                $porId[(string) ($ref['chunk_id'] ?? '')] = $nome;
            }
        }

        return [$porId, array_values(array_unique(array_filter($nomePorLivro, fn (string $n) => $n !== '')))];
    }

    /** Citações completas, uma por livro, para as "Referências Bibliográficas". */
    public function citacoesDaQuestao(array $referencias): array
    {
        $porLivro = [];
        foreach ($referencias as $ref) {
            $chave = $this->chaveDoLivro((string) ($ref['file_name'] ?? ''));
            $completa = ! empty($ref['titulo']);
            if (! isset($porLivro[$chave]) || ($completa && ! $porLivro[$chave][0])) {
                $porLivro[$chave] = [$completa, $this->citacao($ref)];
            }
        }

        return array_values(array_map(fn (array $par) => $par[1], $porLivro));
    }

    /** Títulos curtos dos livros da questão, separados por vírgula (cabe na coluna de 500 caracteres). */
    public function referenciasTexto(array $referencias, int $limite = 500): string
    {
        return mb_substr(implode(', ', $this->fontesDaQuestao($referencias)[1]), 0, $limite);
    }

    private function autores(string $autor): string
    {
        $nomes = array_values(array_filter(array_map('trim', preg_split('/;|\se\s/u', $autor)), fn (string $n) => $n !== ''));
        if (count($nomes) > 3) {
            return $nomes[0] . ' et al.';
        }
        if (count($nomes) > 1) {
            return implode('; ', array_slice($nomes, 0, -1)) . ' e ' . end($nomes);
        }

        return $nomes[0] ?? '';
    }

    private function semPdf(string $arquivo): string
    {
        $barra = strrpos($arquivo, '/');
        $base = $barra === false ? $arquivo : substr($arquivo, $barra + 1);

        return str_ends_with(strtolower($base), '.pdf') ? substr($base, 0, -4) : $base;
    }

    private function espacosSimples(string $texto): string
    {
        return implode(' ', preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY));
    }
}
