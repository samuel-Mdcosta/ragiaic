<?php

namespace Tests\Feature;

use App\Models\AvaliacaoQuiz;
use App\Models\AvaliacaoQuizNota;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BancoDeQuestoes;
use Tests\TestCase;

class AvaliacaoQuizTest extends TestCase
{
    use BancoDeQuestoes;
    use RefreshDatabase;

    private const TEMA = 'Vias visuais';
    private const URL_MOTOR = 'http://motor-ia.test';

    private Usuario $aluno;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tutor_ia.url' => self::URL_MOTOR, 'services.tutor_ia.secret' => 'segredo-de-teste']);
        $this->aluno = Usuario::create(['nome' => 'Aluna Teste', 'email' => 'aluna@teste.com', 'senha' => Hash::make('secret123')]);
        $this->token = $this->aluno->createToken('teste')->plainTextToken;
        $this->criarQuestoes(self::TEMA);
    }

    // ---------- questionário ----------

    public function test_questionario_vem_da_configuracao_e_exige_login(): void
    {
        $this->getJson('/api/users/login/avaliacao/questionario')->assertUnauthorized();

        $resposta = $this->autenticado()->getJson('/api/users/login/avaliacao/questionario')->assertOk();

        $resposta->assertJson([
            'versao'     => config('avaliacao.versao'),
            'escala'     => ['min' => 0, 'max' => 5, 'rotulo_min' => 'Muito ruim', 'rotulo_max' => 'Excelente'],
            'comentario' => ['texto' => config('avaliacao.comentario.texto'), 'max' => 2000],
        ]);
        $this->assertSame(config('avaliacao.perguntas'), $resposta->json('perguntas'));
        $this->assertGreaterThanOrEqual(1, count($resposta->json('perguntas')));
    }

    // ---------- avaliar ----------

    public function test_avaliacao_grava_cada_nota_com_o_texto_da_pergunta_e_o_comentario(): void
    {
        $quizId = $this->concluirQuiz();
        $notas = [];
        foreach ($this->chaves() as $i => $chave) {
            $notas[$chave] = [5, 4, 0, 3][$i % 4]; // inclui a nota 0
        }

        $this->avaliar($quizId, ['notas' => $notas, 'comentario' => '  Gostei dos casos clínicos.  '])
            ->assertCreated()
            ->assertJson(['registrada' => true, 'ja_avaliado' => false]);

        $avaliacao = AvaliacaoQuiz::where('quiz_id', $quizId)->firstOrFail();
        $this->assertSame($this->aluno->id, $avaliacao->usuario_id);
        $this->assertSame(config('avaliacao.versao'), $avaliacao->versao_questionario);
        $this->assertSame('Gostei dos casos clínicos.', $avaliacao->comentario);
        foreach (config('avaliacao.perguntas') as $pergunta) {
            $this->assertDatabaseHas('avaliacoes_quiz_notas', [
                'avaliacao_id' => $avaliacao->id,
                'pergunta'     => $pergunta['chave'],
                'texto'        => $pergunta['texto'],
                'nota'         => $notas[$pergunta['chave']],
            ]);
        }
        $this->assertSame(count($notas), $avaliacao->notas()->count());
    }

    public function test_comentario_e_opcional(): void
    {
        $this->avaliar($this->concluirQuiz(), ['notas' => $this->notas()])->assertCreated();
        $this->avaliar($this->concluirQuiz(), ['notas' => $this->notas(), 'comentario' => '   '])->assertCreated();

        $this->assertSame([null, null], AvaliacaoQuiz::orderBy('id')->pluck('comentario')->all());
    }

    public function test_todas_as_notas_de_0_a_5_sao_obrigatorias(): void
    {
        $quizId = $this->concluirQuiz();
        [$primeira] = $this->chaves();
        $semUma = $this->notas();
        unset($semUma[$primeira]);

        $this->avaliar($quizId, [])->assertStatus(422)->assertJsonValidationErrors(['notas']);
        $this->avaliar($quizId, ['notas' => $semUma])
            ->assertStatus(422)
            ->assertJsonValidationErrors(["notas.{$primeira}" => 'Responda todas as perguntas da avaliação.']);
        foreach ([6, -1, 'muito boa', 2.5] as $invalida) {
            $this->avaliar($quizId, ['notas' => [$primeira => $invalida] + $this->notas()])
                ->assertStatus(422)
                ->assertJsonValidationErrors(["notas.{$primeira}"]);
        }
        $this->avaliar($quizId, ['notas' => $this->notas(), 'comentario' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comentario']);

        $this->assertSame(0, AvaliacaoQuiz::count());
        $this->assertSame(0, AvaliacaoQuizNota::count());
    }

    public function test_so_avalia_quiz_concluido_e_do_proprio_aluno(): void
    {
        $emAndamento = $this->iniciar()->json();
        $this->responder($emAndamento['quiz_id'], $emAndamento['questoes'][0]['id']);
        $this->avaliar($emAndamento['quiz_id'], ['notas' => $this->notas()])
            ->assertStatus(422)
            ->assertJson(['message' => 'Conclua o quiz antes de avaliá-lo.']);

        $quizId = $this->concluirQuiz();
        $outro = Usuario::create(['nome' => 'Outro Aluno', 'email' => 'outro@teste.com', 'senha' => Hash::make('secret123')]);
        $this->como($outro)->postJson("/api/users/login/quiz/{$quizId}/avaliacao", ['notas' => $this->notas()])->assertNotFound();
        $this->avaliar($quizId + 999, ['notas' => $this->notas()])->assertNotFound();

        $this->assertSame(0, AvaliacaoQuiz::count());
    }

    public function test_a_primeira_avaliacao_e_a_que_vale(): void
    {
        $quizId = $this->concluirQuiz();

        $this->avaliar($quizId, ['notas' => $this->notas(5), 'comentario' => 'Primeira'])->assertCreated();
        $this->avaliar($quizId, ['notas' => $this->notas(0), 'comentario' => 'Segunda'])
            ->assertOk()
            ->assertJson(['registrada' => true, 'ja_avaliado' => true]);

        $this->assertSame(1, AvaliacaoQuiz::count());
        $this->assertSame('Primeira', AvaliacaoQuiz::first()->comentario);
        $this->assertEquals([5], AvaliacaoQuizNota::distinct()->pluck('nota')->all());
    }

    // ---------- o resultado só sai depois da avaliação ----------

    public function test_gabarito_do_quiz_concluido_so_sai_depois_da_avaliacao(): void
    {
        // Durante o quiz o gabarito das já respondidas continua disponível (modo com feedback).
        $emAndamento = $this->iniciar()->json();
        $this->responder($emAndamento['quiz_id'], $emAndamento['questoes'][0]['id']);
        $this->autenticado()->getJson("/api/users/login/quiz/{$emAndamento['quiz_id']}/gabarito")->assertOk();

        $quizId = $this->concluirQuiz();
        $this->autenticado()->getJson("/api/users/login/quiz/{$quizId}/gabarito")
            ->assertForbidden()
            ->assertJson(['avaliacao_pendente' => true, 'message' => 'Avalie o quiz para ver o resultado e o feedback.']);

        $this->avaliar($quizId, ['notas' => $this->notas()])->assertCreated();

        $gabarito = $this->autenticado()->getJson("/api/users/login/quiz/{$quizId}/gabarito")->assertOk();
        $this->assertCount(10, $gabarito->json('questoes'));
    }

    public function test_analise_do_tutor_espera_a_avaliacao_sem_chamar_a_ia(): void
    {
        Http::fake([self::URL_MOTOR . '/*' => Http::response($this->respostaMotor())]);
        $quizId = $this->concluirQuiz();

        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')
            ->assertOk()
            ->assertJson([
                'status'    => 'avaliacao_pendente',
                'feedback'  => null,
                'avaliacao' => ['quiz_id' => $quizId, 'tema' => self::TEMA],
            ]);
        Http::assertNothingSent();

        $this->avaliar($quizId, ['notas' => $this->notas()])->assertCreated();

        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')->assertOk()->assertJson(['status' => 'ok']);
        Http::assertSentCount(1);
    }

    public function test_vale_a_avaliacao_do_ultimo_quiz_concluido(): void
    {
        Http::fake([self::URL_MOTOR . '/*' => Http::response($this->respostaMotor())]);

        $this->concluirQuiz(); // fechou a aba sem avaliar
        $segundo = $this->concluirQuiz();
        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')
            ->assertJson(['status' => 'avaliacao_pendente', 'avaliacao' => ['quiz_id' => $segundo]]);

        // Avaliar o último libera a análise, mesmo com um quiz antigo sem avaliação.
        $this->avaliar($segundo, ['notas' => $this->notas()])->assertCreated();
        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')->assertJson(['status' => 'ok']);

        // Um quiz novo concluído volta a pedir a avaliação.
        $terceiro = $this->concluirQuiz();
        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')
            ->assertJson(['status' => 'avaliacao_pendente', 'avaliacao' => ['quiz_id' => $terceiro]]);
    }

    public function test_as_perguntas_seguem_a_configuracao(): void
    {
        config([
            'avaliacao.versao'    => 'definitiva-1',
            'avaliacao.perguntas' => [['chave' => 'aprendizado', 'texto' => 'O quiz ajudou você a aprender?']],
        ]);

        $this->autenticado()->getJson('/api/users/login/avaliacao/questionario')
            ->assertJsonPath('perguntas', [['chave' => 'aprendizado', 'texto' => 'O quiz ajudou você a aprender?']]);

        $this->avaliar($this->concluirQuiz(), ['notas' => ['clareza' => 5]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notas.aprendizado']);

        $quizId = $this->concluirQuiz();
        $this->avaliar($quizId, ['notas' => ['aprendizado' => 2]])->assertCreated();
        $avaliacao = AvaliacaoQuiz::where('quiz_id', $quizId)->firstOrFail();
        $this->assertSame('definitiva-1', $avaliacao->versao_questionario);
        $this->assertSame(
            [['pergunta' => 'aprendizado', 'texto' => 'O quiz ajudou você a aprender?', 'nota' => 2]],
            $avaliacao->notas()->get(['pergunta', 'texto', 'nota'])->toArray(),
        );
    }

    // ---------- apoio ----------

    private function autenticado()
    {
        return $this->como($this->aluno, $this->token);
    }

    private function iniciar(string $modo = 'com_feedback')
    {
        return $this->autenticado()->postJson('/api/users/login/tentativas/perguntas', ['tema' => self::TEMA, 'modo' => $modo])->assertOk();
    }

    private function responder(int $quizId, int $questaoId)
    {
        return $this->autenticado()->postJson("/api/users/login/quiz/{$quizId}/responder", ['questao_id' => $questaoId, 'indice' => 0])->assertOk();
    }

    /** Responde as 10 questões de um quiz novo e devolve o id do quiz (concluído, ainda sem avaliação). */
    private function concluirQuiz(): int
    {
        $inicio = $this->iniciar()->json();
        foreach ($inicio['questoes'] as $questao) {
            $ultima = $this->responder($inicio['quiz_id'], $questao['id']);
        }
        $ultima->assertJson(['quiz_finalizado' => true]);

        return $inicio['quiz_id'];
    }

    private function avaliar(int $quizId, array $corpo)
    {
        return $this->autenticado()->postJson("/api/users/login/quiz/{$quizId}/avaliacao", $corpo);
    }

    private function chaves(): array
    {
        return array_column(config('avaliacao.perguntas'), 'chave');
    }

    private function notas(int $nota = 4): array
    {
        return array_fill_keys($this->chaves(), $nota);
    }

    private function respostaMotor(): array
    {
        return [
            'feedback'   => [
                'resumo_geral'       => 'Bom começo.',
                'areas_prioritarias' => [],
                'pontos_fortes'      => [],
                'proximos_passos'    => ['Fazer outra lista.'],
            ],
            'uso_tokens' => ['entrada' => 10, 'saida' => 10, 'total' => 20],
        ];
    }
}
