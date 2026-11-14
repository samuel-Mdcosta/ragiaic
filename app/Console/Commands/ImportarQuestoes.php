<?php

namespace App\Console\Commands;

use App\Models\Questao;
use App\Providers\ImportacaoQuestoesService;
use Illuminate\Console\Command;
use Throwable;

class ImportarQuestoes extends Command
{
    protected $signature = 'questoes:importar
        {arquivo? : Arquivo JSON do pipeline de geração (padrão: database/data/questoes_40_por_tema.json)}
        {--status=pendente_revisao : Status das questões novas: revisada (liberadas aos alunos), pendente_revisao, reprovada ou arquivada. Nas que já existem, só promove para revisada}
        {--somente-estas : Depois da importação, arquiva as questões do banco que não estão neste arquivo (nada é apagado)}
        {--dry-run : Só valida e mostra o resumo, sem gravar nada}';

    protected $description = 'Importa ou atualiza o banco de questões a partir de um arquivo do pipeline de geração';

    public function handle(ImportacaoQuestoesService $importacao): int
    {
        $status = (string) $this->option('status');
        if (! in_array($status, Questao::STATUS, true)) {
            $this->error('Status inválido. Use um destes: ' . implode(', ', Questao::STATUS) . '.');

            return self::INVALID;
        }

        $arquivo = $this->argument('arquivo') ?? config('quiz.arquivo_questoes');
        try {
            $registros = $importacao->lerArquivo($arquivo);
            $livros = $importacao->lerCatalogo(config('quiz.arquivo_livros'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $preparo = $importacao->preparar($registros, $livros['catalogo'], $livros['equivalentes']);
        $validas = $preparo['validas'];
        $rejeitadas = $preparo['rejeitadas'];

        $this->info(count($registros) . " registros em {$arquivo}: " . count($validas) . ' válidos, ' . count($rejeitadas) . ' rejeitados.');
        foreach ($rejeitadas as $rejeitada) {
            $this->warn("  Registro {$rejeitada['posicao']} rejeitado: " . implode('; ', $rejeitada['problemas']));
        }
        $this->resumo($validas);

        if ($this->option('dry-run')) {
            $this->info('Simulação (--dry-run): nada foi gravado.');

            return $rejeitadas ? self::FAILURE : self::SUCCESS;
        }

        $resultado = $importacao->importar($validas, $status, (bool) $this->option('somente-estas'), (float) config('quiz.similaridade_irmas'));

        $this->info("Gravadas: {$resultado['inseridas']} novas e {$resultado['atualizadas']} atualizadas.");
        if ($this->option('somente-estas')) {
            $this->info("Arquivadas (fora deste arquivo): {$resultado['arquivadas']}.");
        }
        $this->info("Questões irmãs: {$resultado['com_irma']} questões com ao menos uma irmã ({$resultado['pares_de_irmas']} ligações).");
        $this->info('Servidas aos alunos agora: ' . Questao::serviveis()->count() . ' questões revisadas.');
        if ($status !== Questao::STATUS_SERVIDO && $resultado['inseridas'] > 0) {
            $this->warn("As {$resultado['inseridas']} questões novas entraram como '{$status}' e NÃO são servidas aos alunos. "
                . 'Se o arquivo já foi revisado pelo docente, rode de novo com --status=revisada.');
        }

        return $rejeitadas ? self::FAILURE : self::SUCCESS;
    }

    private function resumo(array $linhas): void
    {
        $temasDoConfig = config('temas.quiz', []);
        $porTema = collect($linhas)->groupBy('tema');

        $this->table(
            ['Tema', 'Fáceis', 'Médias', 'Difíceis', 'Total'],
            $porTema->map(fn ($questoes, $tema) => [
                $tema . (in_array($tema, $temasDoConfig, true) ? '' : '  (fora de config/temas.php)'),
                $questoes->where('dificuldade', 'facil')->count(),
                $questoes->where('dificuldade', 'intermediaria')->count(),
                $questoes->where('dificuldade', 'dificil')->count(),
                $questoes->count(),
            ])->sortKeys()->values()->all(),
        );

        $letras = collect($linhas)->countBy('gabarito')->sortKeys()->map(fn ($n, $letra) => "{$letra}={$n}")->implode(', ');
        $this->line("Letras do gabarito: {$letras}.");

        $foraDoConfig = $porTema->keys()->diff($temasDoConfig);
        if ($foraDoConfig->isNotEmpty()) {
            $this->warn('Temas que não estão em config/temas.php (e no front, em src/data/temasQuiz.js), então os alunos não '
                . 'conseguem escolhê-los: ' . $foraDoConfig->implode(', ') . '.');
        }
    }
}
