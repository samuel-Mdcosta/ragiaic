<?php

namespace Tests\Feature;

use App\Models\FeedbackDesempenho;
use App\Models\RespostaQuiz;
use App\Models\TentativaQuizz;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DesempenhoFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private const HIPOCAMPO = 'Hipocampo e memória espacial';
    private const MEMORIA = 'Sistemas e processos da memória';
    private const LINGUAGEM = 'Hemisférios cerebrais e linguagem';
    private const URL_MOTOR = 'http://motor-ia.test';
    private const SEGREDO_MOTOR = 'segredo-de-teste';

    private Usuario $aluno;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tutor_ia.url' => self::URL_MOTOR, 'services.tutor_ia.secret' => self::SEGREDO_MOTOR]);

        $this->aluno = Usuario::create([
            'nome'  => 'Aluna Teste',
            'email' => 'aluna@teste.com',
            'senha' => Hash::make('secret123'),
        ]);
        $this->token = $this->aluno->createToken('teste')->plainTextToken;
    }

    private function autenticado()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function registrar(string $tema, int $acertos, int $erros, array $questoes = [])
    {
        $dados = ['conteudoAcessado' => $tema, 'acertos' => $acertos, 'erros' => $erros];
        if ($questoes) {
            $dados['questoes'] = $questoes;
        }

        return $this->autenticado()->postJson('/api/users/login/tentativas', $dados);
    }

    private function questao(string $pergunta, ?string $subtema, bool $acertou): array
    {
        return [
            'pergunta'           => $pergunta,
            'subtema'            => $subtema,
            'resposta_escolhida' => $acertou ? 'Certa' : 'Errada',
            'resposta_correta'   => 'Certa',
            'acertou'            => $acertou,
            'referencias'        => 'Neurociencia Cognitiva',
        ];
    }

    private function respostaMotor(): array
    {
        return [
            'feedback' => [
                'resumo_geral'       => 'Você precisa reforçar o Hipocampo e Sistema Límbico.',
                'areas_prioritarias' => [[
                    'tema'            => self::HIPOCAMPO,
                    'diagnostico'     => 'Você confunde as estruturas do circuito de Papez.',
                    'o_que_estudar'   => ['Circuito de Papez'],
                    'por_que_estudar' => 'Integra emoção e memória.',
                    'onde_estudar'    => ['Neurociencia Cognitiva'],
                ]],
                'pontos_fortes'   => [self::LINGUAGEM],
                'proximos_passos' => ['Revisar o circuito de Papez'],
            ],
            'uso_tokens' => ['entrada' => 900, 'saida' => 300, 'total' => 1200],
        ];
    }

    // ---------- registro por questão ----------

    public function test_registrar_tentativa_salva_cada_questao(): void
    {
        $resposta = $this->registrar(self::HIPOCAMPO, 1, 9, [
            $this->questao('Q1', 'Circuito de Papez', false),
            $this->questao('Q2', 'Circuito de Papez', false),
            $this->questao('Q3', 'Amígdala', true),
        ]);

        $resposta->assertCreated();
        $tentativa = TentativaQuizz::firstOrFail();
        $this->assertSame(3, $tentativa->respostas()->count());
        $this->assertDatabaseHas('respostas_quiz', [
            'tentativa_id' => $tentativa->id,
            'usuario_id'   => $this->aluno->id,
            'tema'         => self::HIPOCAMPO,
            'subtema'      => 'Circuito de Papez',
            'pergunta'     => 'Q1',
            'acertou'      => false,
        ]);
    }

    public function test_registrar_tentativa_sem_questoes_continua_funcionando(): void
    {
        $this->registrar(self::HIPOCAMPO, 7, 3)->assertCreated();

        $this->assertSame(1, TentativaQuizz::count());
        $this->assertSame(0, RespostaQuiz::count());
    }

    public function test_registrar_tentativa_valida_as_questoes(): void
    {
        $this->registrar(self::HIPOCAMPO, 1, 0, [['pergunta' => 'Q1']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['questoes.0.acertou']);

        $this->assertSame(0, TentativaQuizz::count());
    }

    // ---------- números do desempenho ----------

    public function test_desempenho_calcula_niveis_e_ordena_do_pior_para_o_melhor(): void
    {
        $this->registrar(self::HIPOCAMPO, 3, 7);
        $this->registrar(self::HIPOCAMPO, 5, 5);
        $this->registrar(self::LINGUAGEM, 9, 1);
        $this->registrar(self::MEMORIA, 6, 4);

        $resposta = $this->autenticado()->getJson('/api/users/login/desempenho')->assertOk();

        $this->assertSame([self::HIPOCAMPO, self::MEMORIA, self::LINGUAGEM], array_column($resposta->json('desempenho_por_tema'), 'tema'));

        $hipocampo = $resposta->json('desempenho_por_tema.0');
        $this->assertEquals(40, $hipocampo['taxa_acerto']);
        $this->assertSame('critico', $hipocampo['nivel']);
        $this->assertSame('melhorando', $hipocampo['tendencia']);
        $this->assertSame('atencao', $resposta->json('desempenho_por_tema.1.nivel'));
        $this->assertSame('bom', $resposta->json('desempenho_por_tema.2.nivel'));

        $this->assertSame(40, $resposta->json('questoes_respondidas'));
        $this->assertEquals(57.5, $resposta->json('taxa_acerto'));
        $this->assertCount(count(config('temas.quiz')) - 3, $resposta->json('temas_nao_praticados'));
        $this->assertNotContains(self::HIPOCAMPO, $resposta->json('temas_nao_praticados'));
    }

    public function test_nivel_usa_as_tentativas_recentes(): void
    {
        // Começou mal, mas acertou 80% nas três últimas: hoje o nível é "bom".
        $this->registrar(self::MEMORIA, 0, 10);
        $this->registrar(self::MEMORIA, 8, 2);
        $this->registrar(self::MEMORIA, 8, 2);
        $this->registrar(self::MEMORIA, 8, 2);

        $tema = $this->autenticado()->getJson('/api/users/login/desempenho')->json('desempenho_por_tema.0');

        $this->assertEquals(60, $tema['taxa_acerto']);
        $this->assertEquals(80, $tema['taxa_recente']);
        $this->assertSame('bom', $tema['nivel']);
    }

    // ---------- feedback da IA ----------

    public function test_feedback_envia_os_dados_ao_motor_e_usa_cache(): void
    {
        Http::fake([self::URL_MOTOR . '/*' => Http::response($this->respostaMotor())]);

        $this->registrar(self::HIPOCAMPO, 1, 9, [
            $this->questao('Q1', 'Circuito de Papez', false),
            $this->questao('Q2', 'Consolidação', false),
            $this->questao('Q3', 'Amígdala', true),
        ]);
        $this->registrar(self::LINGUAGEM, 9, 1);

        $primeira = $this->autenticado()->getJson('/api/users/login/desempenho/feedback')->assertOk();

        $primeira->assertJson(['status' => 'ok', 'em_cache' => false, 'total_tentativas' => 2]);
        $area = $primeira->json('feedback.areas_prioritarias.0');
        $this->assertSame(self::HIPOCAMPO, $area['tema']);
        $this->assertSame('critico', $area['nivel']);
        $this->assertEquals(10, $area['taxa_recente']);

        Http::assertSent(function (HttpRequest $request) {
            $areas = $request['areas_prioritarias'];

            return $request->url() === self::URL_MOTOR . '/feedback'
                && $request->hasHeader('X-API-Key', self::SEGREDO_MOTOR)
                && count($areas) === 1
                && $areas[0]['tema'] === self::HIPOCAMPO
                && array_column($areas[0]['questoes_erradas'], 'pergunta') === ['Q2', 'Q1']
                && $areas[0]['subtemas_acertados'] === ['Amígdala']
                && count($request['desempenho_por_tema']) === 2
                && in_array(self::MEMORIA, $request['temas_nao_praticados'], true);
        });

        // Sem tentativa nova: devolve o feedback salvo, sem chamar a IA de novo.
        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')
            ->assertOk()
            ->assertJson(['em_cache' => true]);
        Http::assertSentCount(1);
        $this->assertSame(1, FeedbackDesempenho::count());
        $this->assertSame(1200, FeedbackDesempenho::first()->uso_tokens['total']);

        // Tentativa nova: gera outro feedback.
        $this->registrar(self::HIPOCAMPO, 6, 4);
        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')
            ->assertOk()
            ->assertJson(['em_cache' => false, 'total_tentativas' => 3]);
        Http::assertSentCount(2);
    }

    public function test_feedback_sem_tentativas_nao_chama_a_ia(): void
    {
        Http::fake();

        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')
            ->assertOk()
            ->assertJson(['status' => 'sem_dados', 'feedback' => null]);

        Http::assertNothingSent();
    }

    public function test_feedback_com_motor_com_erro_retorna_503_e_nao_salva(): void
    {
        Http::fake([self::URL_MOTOR . '/*' => Http::response(['erro' => 'Formato inválido gerado pela IA'], 502)]);
        $this->registrar(self::HIPOCAMPO, 2, 8);

        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')->assertStatus(503);

        $this->assertSame(0, FeedbackDesempenho::count());
    }

    public function test_feedback_com_motor_fora_do_ar_retorna_503(): void
    {
        Http::fake([self::URL_MOTOR . '/*' => Http::failedConnection('timeout')]);
        $this->registrar(self::HIPOCAMPO, 2, 8);

        $this->autenticado()->getJson('/api/users/login/desempenho/feedback')->assertStatus(503);
    }

    public function test_rotas_de_desempenho_exigem_login(): void
    {
        $this->getJson('/api/users/login/desempenho')->assertUnauthorized();
        $this->getJson('/api/users/login/desempenho/feedback')->assertUnauthorized();
    }
}
