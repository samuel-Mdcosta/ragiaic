<?php

namespace Tests\Feature;

use App\Models\Questao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportarQuestoesTest extends TestCase
{
    use RefreshDatabase;

    private array $arquivosTemporarios = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->arquivosTemporarios, 'is_file'));
        parent::tearDown();
    }

    public function test_importa_as_320_questoes_do_arquivo_com_textos_prontos_para_o_aluno(): void
    {
        $this->artisan('questoes:importar', ['--status' => 'revisada'])->assertSuccessful();

        $this->assertSame(320, Questao::serviveis()->count());
        foreach (config('temas.quiz') as $tema) {
            foreach (['facil' => 12, 'intermediaria' => 20, 'dificil' => 8] as $dificuldade => $quantas) {
                $this->assertSame($quantas, Questao::where('tema', $tema)->where('dificuldade', $dificuldade)->count(), "{$tema} / {$dificuldade}");
            }
        }
        $this->assertSame(488, DB::table('questoes_irmas')->count());

        $linguagemDeSistema = '/\bchunks?\b|[0-9a-f]{24}|\b(?:o|esse|este|no|do|pelo|mesmo)\s+trechos?\b/iu';
        foreach (Questao::all() as $questao) {
            foreach ([$questao->justificativa, ...array_column($questao->alternativas, 'explicacao')] as $texto) {
                $this->assertDoesNotMatchRegularExpression($linguagemDeSistema, $texto, $questao->codigo);
            }
            foreach ([$questao->enunciado(), ...$questao->opcoes()] as $texto) {
                $this->assertDoesNotMatchRegularExpression('/\bchunks?\b|[0-9a-f]{24}/iu', $texto, $questao->codigo);
            }
            foreach ($questao->referencias as $referencia) {
                $this->assertNotEmpty($referencia['titulo'] ?? null, $referencia['file_name']);
                $this->assertNotEmpty($referencia['autor'] ?? null, $referencia['file_name']);
            }
            $this->assertSame(['A', 'B', 'C', 'D'], array_column($questao->alternativas, 'letra'));
        }

        // O registro original fica intacto (com os códigos dos trechos), para rastreabilidade.
        $this->assertMatchesRegularExpression('/[0-9a-f]{24}/', json_encode(Questao::first()->bruto));
        // O código é o mesmo que o pipeline (Python) gera para a primeira questão do arquivo.
        $this->assertTrue(Questao::where('codigo', 'sha256:e19802b8a7982e961fb4bd1bb5076db105eb8c5ce41f62949724f3e7dd478ebe')->exists());
    }

    public function test_reimportar_nao_duplica_nem_desfaz_a_revisao(): void
    {
        $this->artisan('questoes:importar', ['--status' => 'revisada'])->assertSuccessful();
        $ids = Questao::orderBy('id')->pluck('id')->all();

        $this->artisan('questoes:importar')->assertSuccessful(); // status padrão: pendente_revisao

        $this->assertSame($ids, Questao::orderBy('id')->pluck('id')->all());
        $this->assertSame(320, Questao::serviveis()->count());
    }

    public function test_sem_status_revisada_as_questoes_novas_nao_chegam_aos_alunos(): void
    {
        $this->artisan('questoes:importar')
            ->expectsOutputToContain('NÃO são servidas aos alunos')
            ->assertSuccessful();

        $this->assertSame(320, Questao::where('status', 'pendente_revisao')->count());
        $this->assertSame(0, Questao::serviveis()->count());
    }

    public function test_justificativa_cita_o_livro_no_lugar_do_codigo_do_trecho(): void
    {
        $arquivo = $this->arquivo([$this->registro('Caso A')]);

        $this->artisan('questoes:importar', ['arquivo' => $arquivo, '--status' => 'revisada'])->assertSuccessful();

        $questao = Questao::firstOrFail();
        $this->assertSame('O livro «Neuroanatomia Funcional» descreve a resposta.', $questao->justificativa);
        $this->assertSame('Certa.', $questao->alternativas[0]['explicacao']);
        $this->assertSame('A', $questao->gabarito);
        $this->assertSame('Angelo Machado', $questao->referencias[0]['autor']);
    }

    public function test_somente_estas_arquiva_as_questoes_que_sairam_do_arquivo(): void
    {
        $primeiro = $this->arquivo([$this->registro('Caso A'), $this->registro('Caso B')]);
        $this->artisan('questoes:importar', ['arquivo' => $primeiro, '--status' => 'revisada'])->assertSuccessful();

        $segundo = $this->arquivo([$this->registro('Caso B'), $this->registro('Caso C')]);
        $this->artisan('questoes:importar', ['arquivo' => $segundo, '--status' => 'revisada', '--somente-estas' => true])
            ->assertSuccessful();

        $this->assertSame(3, Questao::count()); // nada é apagado
        $this->assertSame('arquivada', Questao::where('caso_clinico', 'Caso A')->value('status'));
        $this->assertEqualsCanonicalizing(['Caso B', 'Caso C'], Questao::serviveis()->pluck('caso_clinico')->all());
    }

    public function test_dry_run_valida_sem_gravar(): void
    {
        $this->artisan('questoes:importar', ['--dry-run' => true, '--status' => 'revisada'])
            ->expectsOutputToContain('320 válidos, 0 rejeitados')
            ->assertSuccessful();

        $this->assertSame(0, Questao::count());
    }

    public function test_registros_com_defeito_sao_rejeitados_e_os_validos_entram(): void
    {
        $duasCorretas = $this->registro('Caso X');
        $duasCorretas['item']['alternativas'][1]['correta'] = true;
        $tresAlternativas = $this->registro('Caso Y');
        array_pop($tresAlternativas['item']['alternativas']);
        $dificuldadeInvalida = $this->registro('Caso Z');
        $dificuldadeInvalida['dificuldade'] = 'muito dificil';

        $arquivo = $this->arquivo([
            $this->registro('Caso OK'), $duasCorretas, $tresAlternativas, $dificuldadeInvalida, $this->registro('caso  ok'),
        ]);

        $this->artisan('questoes:importar', ['arquivo' => $arquivo, '--status' => 'revisada'])
            ->expectsOutputToContain('1 válidos, 4 rejeitados')
            ->expectsOutputToContain('deve haver exatamente 1 alternativa correta (há 2)')
            ->expectsOutputToContain('questão repetida no arquivo')
            ->assertFailed();

        $this->assertSame(['Caso OK'], Questao::pluck('caso_clinico')->all());
    }

    public function test_status_invalido_e_arquivo_inexistente_sao_recusados(): void
    {
        $this->artisan('questoes:importar', ['--status' => 'aprovada'])->assertExitCode(2);
        $this->artisan('questoes:importar', ['arquivo' => '/caminho/que/nao/existe.json'])->assertFailed();

        $this->assertSame(0, Questao::count());
    }

    private function registro(string $caso): array
    {
        $trecho = 'aaaaaaaaaaaaaaaaaaaaaaaa';
        $alternativa = fn (string $letra, bool $correta, string $explicacao) => [
            'letra' => $letra, 'texto' => "Alternativa {$letra}", 'correta' => $correta, 'explicacao' => $explicacao,
        ];

        return [
            'tema'                  => 'Vias visuais',
            'lote_id'               => 'lote-de-teste',
            'posicao'               => 1,
            'dificuldade'           => 'facil',
            'criado_em'             => '2026-11-13T12:00:00Z',
            'subtema'               => "Subtema do {$caso}",
            'objetivo_aprendizagem' => 'Objetivo de aprendizagem.',
            'referencias'           => [['chunk_id' => $trecho, 'file_name' => 'Neuroanatomia Funcional.pdf']],
            'item'                  => [
                'caso_clinico'           => $caso,
                'comando'                => 'Qual é a alternativa correta?',
                'alternativas'           => [
                    $alternativa('A', true, "Certa [{$trecho}]."),
                    $alternativa('B', false, 'Errada.'),
                    $alternativa('C', false, 'Errada.'),
                    $alternativa('D', false, 'Errada.'),
                ],
                'justificativa_gabarito' => "O chunk {$trecho} descreve a resposta.",
            ],
        ];
    }

    private function arquivo(array $registros): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'questoes-teste-');
        file_put_contents($caminho, json_encode(['questoes' => $registros], JSON_UNESCAPED_UNICODE));
        $this->arquivosTemporarios[] = $caminho;

        return $caminho;
    }
}
