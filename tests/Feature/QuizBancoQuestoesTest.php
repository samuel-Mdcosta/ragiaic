<?php

namespace Tests\Feature;

use App\Models\Questao;
use App\Models\Quiz;
use App\Models\QuizQuestao;
use App\Models\RespostaQuiz;
use App\Models\TentativaQuizz;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuizBancoQuestoesTest extends TestCase
{
    use RefreshDatabase;

    private const TEMA = 'Vias visuais';

    private Usuario $aluno;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aluno = Usuario::create(['nome' => 'Aluna Teste', 'email' => 'aluna@teste.com', 'senha' => Hash::make('secret123')]);
        $this->token = $this->aluno->createToken('teste')->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------- iniciar ----------

    public function test_iniciar_quiz_exige_login(): void
    {
        $this->postJson('/api/users/login/tentativas/perguntas', ['tema' => self::TEMA])->assertUnauthorized();
    }

    public function test_tema_fora_da_lista_e_recusado(): void
    {
        $this->iniciar('Hipocampo e Sistema Límbico')->assertStatus(422)->assertJsonValidationErrors(['tema']);
        $this->iniciar(self::TEMA, 'modo_invalido')->assertStatus(422)->assertJsonValidationErrors(['modo']);
    }

    public function test_sorteia_10_questoes_3_5_2_sem_gabarito(): void
    {
        $this->criarQuestoes(self::TEMA);

        $resposta = $this->iniciar(self::TEMA)->assertOk();

        $questoes = $resposta->json('questoes');
        $this->assertCount(10, $questoes);
        $this->assertSame(
            [...array_fill(0, 3, 'facil'), ...array_fill(0, 5, 'intermediaria'), ...array_fill(0, 2, 'dificil')],
            array_column($questoes, 'dificuldade'),
        );
        foreach ($questoes as $questao) {
            $this->assertSame(['id', 'dificuldade', 'pergunta', 'opcoes', 'subtema', 'referencias'], array_keys($questao));
            $this->assertCount(4, $questao['opcoes']);
        }
        // Nada do gabarito sai antes da resposta.
        $this->assertDoesNotMatchRegularExpression('/"(correta|gabarito|justificativa|explicacao|explicacoes_alternativas|fontes)"\s*:/', $resposta->getContent());
        $resposta->assertJson(['tema' => self::TEMA, 'modo' => 'com_feedback', 'aviso' => null]);

        $quiz = Quiz::findOrFail($resposta->json('quiz_id'));
        $this->assertSame($this->aluno->id, $quiz->usuario_id);
        $this->assertSame(array_column($questoes, 'id'), $quiz->itens()->pluck('questao_id')->all());
    }

    public function test_tema_sem_questoes_avisa_e_nao_cria_quiz(): void
    {
        $this->iniciar(self::TEMA)
            ->assertOk()
            ->assertJson(['quiz_id' => null, 'questoes' => [], 'aviso' => 'Ainda não há questões disponíveis para este tema.']);

        $this->assertSame(0, Quiz::count());
    }

    public function test_so_serve_questoes_revisadas(): void
    {
        $this->criarQuestoes(self::TEMA);
        Questao::where('dificuldade', 'dificil')->update(['status' => 'pendente_revisao']);

        $questoes = $this->iniciar(self::TEMA)->json('questoes');

        $this->assertCount(10, $questoes);
        $this->assertNotContains('dificil', array_column($questoes, 'dificuldade'));
    }

    // ---------- responder ----------

    public function test_com_feedback_grava_a_resposta_e_so_entao_devolve_o_gabarito(): void
    {
        $this->criarQuestoes(self::TEMA);
        $inicio = $this->iniciar(self::TEMA)->json();
        $questao = Questao::findOrFail($inicio['questoes'][0]['id']);
        $errada = ($questao->indiceCorreto() + 1) % 4;

        $resposta = $this->responder($inicio['quiz_id'], $questao->id, $errada)->assertOk();

        $resposta->assertJson([
            'registrada' => true, 'ja_respondida' => false, 'resposta_inicial' => $errada, 'quiz_finalizado' => false,
            'feedback' => [
                'id' => $questao->id, 'correta' => $questao->indiceCorreto(), 'escolhida' => $errada, 'acertou' => false,
                'explicacao' => $questao->justificativa,
            ],
        ]);
        $this->assertCount(4, $resposta->json('feedback.explicacoes_alternativas'));
        $this->assertSame(['Neuroanatomia Funcional — Angelo Machado (4ª ed., Atheneu, 2021)'], $resposta->json('feedback.fontes'));

        $item = QuizQuestao::where('quiz_id', $inicio['quiz_id'])->where('questao_id', $questao->id)->firstOrFail();
        $this->assertSame(Questao::LETRAS[$errada], $item->letra_escolhida);
        $this->assertFalse($item->acertou);
    }

    public function test_a_resposta_inicial_nao_pode_ser_trocada(): void
    {
        $this->criarQuestoes(self::TEMA);
        $inicio = $this->iniciar(self::TEMA)->json();
        $questao = Questao::findOrFail($inicio['questoes'][0]['id']);
        $errada = ($questao->indiceCorreto() + 1) % 4;

        $this->responder($inicio['quiz_id'], $questao->id, $errada)->assertOk();
        $segunda = $this->responder($inicio['quiz_id'], $questao->id, $questao->indiceCorreto())->assertOk();

        $segunda->assertJson(['ja_respondida' => true, 'resposta_inicial' => $errada, 'feedback' => ['acertou' => false]]);
        $this->assertSame(Questao::LETRAS[$errada], QuizQuestao::where('questao_id', $questao->id)->value('letra_escolhida'));
    }

    public function test_sem_feedback_nao_revela_o_gabarito_ao_responder(): void
    {
        $this->criarQuestoes(self::TEMA);
        $inicio = $this->iniciar(self::TEMA, 'sem_feedback')->json();

        $resposta = $this->responder($inicio['quiz_id'], $inicio['questoes'][0]['id'], 0)->assertOk();

        $resposta->assertJson(['registrada' => true, 'resposta_inicial' => 0]);
        $this->assertArrayNotHasKey('feedback', $resposta->json());
    }

    public function test_responder_so_vale_para_o_proprio_quiz_e_indices_de_0_a_3(): void
    {
        $this->criarQuestoes(self::TEMA);
        $inicio = $this->iniciar(self::TEMA)->json();
        $quizId = $inicio['quiz_id'];
        $questaoId = $inicio['questoes'][0]['id'];
        $foraDoQuiz = Questao::whereNotIn('id', array_column($inicio['questoes'], 'id'))->value('id');

        $this->responder($quizId, $foraDoQuiz, 0)->assertNotFound()->assertJson(['message' => 'Esta questão não faz parte do quiz.']);
        $this->responder($quizId, $questaoId, 4)->assertStatus(422)->assertJsonValidationErrors(['indice']);
        $this->responder($quizId + 999, $questaoId, 0)->assertNotFound();
        $this->autenticado()->postJson('/api/users/login/quiz/abc/responder', ['questao_id' => $questaoId, 'indice' => 0])->assertNotFound();

        // Quiz de outro aluno.
        $outro = Usuario::create(['nome' => 'Outro Aluno', 'email' => 'outro@teste.com', 'senha' => Hash::make('secret123')]);
        $this->como($outro)
            ->postJson("/api/users/login/quiz/{$quizId}/responder", ['questao_id' => $questaoId, 'indice' => 0])
            ->assertNotFound();
        $this->como($outro)->getJson("/api/users/login/quiz/{$quizId}/gabarito")->assertNotFound();

        $this->assertSame(0, QuizQuestao::whereNotNull('letra_escolhida')->count());
    }

    // ---------- concluir e gabarito ----------

    public function test_ao_responder_a_ultima_o_quiz_conclui_e_a_tentativa_e_registrada_pelo_servidor(): void
    {
        $this->criarQuestoes(self::TEMA);
        $inicio = $this->iniciar(self::TEMA)->json();

        $ultima = null;
        foreach ($inicio['questoes'] as $i => $publica) {
            $questao = Questao::findOrFail($publica['id']);
            $indice = $i < 7 ? $questao->indiceCorreto() : ($questao->indiceCorreto() + 1) % 4; // 7 certas, 3 erradas
            $ultima = $this->responder($inicio['quiz_id'], $questao->id, $indice)->assertOk();
            $this->assertSame($i === 9, $ultima->json('quiz_finalizado'));
        }

        $quiz = Quiz::findOrFail($inicio['quiz_id']);
        $this->assertNotNull($quiz->finalizado_em);
        $tentativa = TentativaQuizz::findOrFail($quiz->tentativa_id);
        $this->assertSame(
            ['usuario_id' => $this->aluno->id, 'conteudoAcessado' => self::TEMA, 'acertos' => 7, 'erros' => 3],
            $tentativa->only(['usuario_id', 'conteudoAcessado', 'acertos', 'erros']),
        );

        $respostas = RespostaQuiz::where('tentativa_id', $tentativa->id)->get();
        $this->assertCount(10, $respostas);
        $this->assertSame(3, $respostas->where('acertou', false)->count());
        $primeira = Questao::findOrFail($inicio['questoes'][0]['id']);
        $this->assertDatabaseHas('respostas_quiz', [
            'tentativa_id'     => $tentativa->id,
            'tema'             => self::TEMA,
            'subtema'          => $primeira->subtema,
            'pergunta'         => $primeira->enunciado(),
            'resposta_correta' => $primeira->opcoes()[$primeira->indiceCorreto()],
            'acertou'          => true,
            'referencias'      => 'Neuroanatomia Funcional',
        ]);

        // A tentativa já alimenta "Meu Desempenho".
        $this->autenticado()->getJson('/api/users/login/desempenho')
            ->assertOk()
            ->assertJson(['questoes_respondidas' => 10, 'taxa_acerto' => 70, 'tentativas' => 1])
            ->assertJsonPath('desempenho_por_tema.0.tema', self::TEMA);
        $this->autenticado()->getJson('/api/users/login/tentativas/quantidade')->assertJson(['quantidade' => 1]);

        // Responder de novo não registra outra tentativa.
        $this->responder($inicio['quiz_id'], $inicio['questoes'][9]['id'], 0)->assertJson(['ja_respondida' => true, 'quiz_finalizado' => true]);
        $this->assertSame(1, TentativaQuizz::count());
    }

    public function test_gabarito_traz_so_as_questoes_ja_respondidas(): void
    {
        $this->criarQuestoes(self::TEMA);
        $inicio = $this->iniciar(self::TEMA, 'sem_feedback')->json();
        foreach (array_slice($inicio['questoes'], 0, 3) as $questao) {
            $this->responder($inicio['quiz_id'], $questao['id'], 1)->assertOk();
        }

        $gabarito = $this->autenticado()->getJson("/api/users/login/quiz/{$inicio['quiz_id']}/gabarito")->assertOk();

        $gabarito->assertJson(['quiz_id' => $inicio['quiz_id'], 'tema' => self::TEMA, 'finalizado' => false, 'pendentes' => 7]);
        $this->assertSame([0, 1, 2], array_column($gabarito->json('questoes'), 'ordem'));
        $primeira = Questao::findOrFail($inicio['questoes'][0]['id']);
        $gabarito->assertJsonPath('questoes.0.correta', $primeira->indiceCorreto());
        $gabarito->assertJsonPath('questoes.0.escolhida', 1);
        $gabarito->assertJsonPath('questoes.0.explicacao', $primeira->justificativa);
    }

    // ---------- histórico: nada se repete por 7 dias ----------

    public function test_questoes_respondidas_nao_voltam_antes_de_7_dias(): void
    {
        $this->criarQuestoes(self::TEMA); // 40 questões: dá para 4 quizzes sem repetir
        $agora = Carbon::create(2026, 11, 14, 12, 0, 0, 'UTC');

        $vistas = [];
        $primeiro = null;
        foreach (range(0, 3) as $k) {
            Carbon::setTestNow($agora->copy()->addHours($k));
            $ids = $this->fazerQuiz();
            $this->assertEmpty(array_intersect($ids, $vistas), "o quiz {$k} repetiu questões");
            $vistas = [...$vistas, ...$ids];
            $primeiro ??= $ids;
        }
        $this->assertCount(40, array_unique($vistas));

        // Tema esgotado dentro da janela: o quiz sai completo, avisa e repete as respondidas há mais tempo.
        Carbon::setTestNow($agora->copy()->addDays(2));
        $quinto = $this->iniciar(self::TEMA)->assertOk();
        $this->assertCount(10, $quinto->json('questoes'));
        $this->assertStringContainsString('10 questões se repetem', $quinto->json('aviso'));
        $this->assertEqualsCanonicalizing($primeiro, array_column($quinto->json('questoes'), 'id'));
        $this->assertSame(10, $quinto->json('info.reaproveitadas'));

        // Passados os 7 dias do primeiro quiz, as questões dele voltam normalmente (sem aviso).
        Carbon::setTestNow($agora->copy()->addDays(7)->addMinutes(30));
        $depois = $this->iniciar(self::TEMA)->assertOk()->assertJson(['aviso' => null]);
        $this->assertEqualsCanonicalizing($primeiro, array_column($depois->json('questoes'), 'id'));
    }

    public function test_quiz_abandonado_nao_entra_no_historico(): void
    {
        $this->criarQuestoes(self::TEMA, 3, 5, 2); // o tema tem exatamente 10 questões

        $abandonado = $this->iniciar(self::TEMA)->json();
        foreach (array_slice($abandonado['questoes'], 0, 5) as $questao) {
            $this->responder($abandonado['quiz_id'], $questao['id'], 0)->assertOk();
        }
        $this->iniciar(self::TEMA)->assertJson(['aviso' => null, 'info' => ['reaproveitadas' => 0]]);

        $this->fazerQuiz();
        $this->iniciar(self::TEMA)->assertJson(['info' => ['reaproveitadas' => 10]]);
    }

    public function test_historico_e_individual(): void
    {
        $this->criarQuestoes(self::TEMA, 3, 5, 2);
        $this->fazerQuiz();

        $outro = Usuario::create(['nome' => 'Outro Aluno', 'email' => 'outro@teste.com', 'senha' => Hash::make('secret123')]);
        $this->como($outro)
            ->postJson('/api/users/login/tentativas/perguntas', ['tema' => self::TEMA])
            ->assertJson(['aviso' => null, 'info' => ['reaproveitadas' => 0]]);
    }

    // ---------- com o arquivo real ----------

    public function test_cada_tema_do_arquivo_real_entrega_um_quiz_completo(): void
    {
        $this->artisan('questoes:importar', ['--status' => 'revisada'])->assertSuccessful();

        foreach (config('temas.quiz') as $tema) {
            $resposta = $this->iniciar($tema)->assertOk();
            $this->assertSame(['facil' => 3, 'intermediaria' => 5, 'dificil' => 2], array_count_values(array_column($resposta->json('questoes'), 'dificuldade')), $tema);
            $this->assertSame(0, $resposta->json('info.pares_de_irmas_no_quiz'), $tema);
        }

        $quiz = Quiz::firstOrFail();
        $feedback = $this->responder($quiz->id, $quiz->itens()->value('questao_id'), 0)->json('feedback');
        $this->assertNotEmpty($feedback['fontes']);
        $this->assertStringContainsString('ª ed.', $feedback['fontes'][0]);
        $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{24}|chunk/i', json_encode($feedback, JSON_UNESCAPED_UNICODE));
    }

    // ---------- apoio ----------

    private function autenticado()
    {
        return $this->como($this->aluno, $this->token);
    }

    /** O guard do Sanctum guarda o usuário entre requisições do mesmo teste: esquece-o antes de trocar. */
    private function como(Usuario $usuario, ?string $token = null)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . ($token ?? $usuario->createToken('teste')->plainTextToken));
    }

    private function iniciar(string $tema, ?string $modo = null)
    {
        return $this->autenticado()->postJson('/api/users/login/tentativas/perguntas', array_filter(['tema' => $tema, 'modo' => $modo]));
    }

    private function responder(int $quizId, int $questaoId, int $indice)
    {
        return $this->autenticado()->postJson("/api/users/login/quiz/{$quizId}/responder", ['questao_id' => $questaoId, 'indice' => $indice]);
    }

    /** Faz um quiz inteiro (respondendo sempre a alternativa A) e devolve os ids das questões. */
    private function fazerQuiz(): array
    {
        $inicio = $this->iniciar(self::TEMA)->assertOk()->json();
        foreach ($inicio['questoes'] as $questao) {
            $this->responder($inicio['quiz_id'], $questao['id'], 0)->assertOk();
        }
        $this->assertNotNull(Quiz::findOrFail($inicio['quiz_id'])->finalizado_em);

        return array_column($inicio['questoes'], 'id');
    }

    private function criarQuestoes(string $tema, int $faceis = 12, int $medias = 20, int $dificeis = 8): void
    {
        $n = 0;
        foreach (['facil' => $faceis, 'intermediaria' => $medias, 'dificil' => $dificeis] as $dificuldade => $quantas) {
            for ($k = 0; $k < $quantas; $k++, $n++) {
                $gabarito = Questao::LETRAS[$n % 4];
                Questao::create([
                    'codigo'                => "teste:{$tema}:{$n}",
                    'tema'                  => $tema,
                    'subtema'               => "Subtema {$n}",
                    'dificuldade'           => $dificuldade,
                    'objetivo_aprendizagem' => 'Objetivo.',
                    'caso_clinico'          => "Caso clínico {$n}.",
                    'comando'               => 'Qual é a alternativa correta?',
                    'alternativas'          => array_map(fn (string $letra) => [
                        'letra' => $letra, 'texto' => "Opção {$letra} da questão {$n}", 'explicacao' => "Explicação da {$letra}.",
                    ], Questao::LETRAS),
                    'gabarito'              => $gabarito,
                    'justificativa'         => "A correta é a {$gabarito}.",
                    'referencias'           => [[
                        'chunk_id' => sprintf('%024x', $n), 'file_name' => 'Neuroanatomia Funcional.pdf',
                        'titulo' => 'Neuroanatomia Funcional', 'autor' => 'Angelo Machado', 'edicao' => 4,
                        'editora' => 'Atheneu', 'publicacao' => 2021,
                    ]],
                    'status'                => 'revisada',
                    'bruto'                 => [],
                ]);
            }
        }
    }
}
