<?php

namespace App\Providers;

use App\Models\Questao;
use App\Models\Quiz;
use App\Models\QuizQuestao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

/**
 * Quiz servido a partir do banco de questões.
 *
 * O aluno recebe as questões SEM gabarito. Cada resposta é gravada antes de qualquer feedback (a primeira é
 * a que vale) e, no modo com feedback, o gabarito daquela questão só sai depois da gravação. Quando a última
 * questão é respondida, o quiz é concluído e a tentativa é registrada aqui mesmo, com acertos e erros
 * calculados pelo servidor.
 */
class QuizService
{
    public function __construct(
        private SorteioQuizService $sorteio,
        private ReferenciasService $referencias,
        private TentativaService $tentativas,
    ) {
    }

    public function iniciar(int $usuarioId, string $tema, string $modo, ?Randomizer $acaso = null): array
    {
        $pool = Questao::serviveis()
            ->where('tema', $tema)
            ->get(['id', 'dificuldade'])
            ->map(fn (Questao $q) => ['id' => $q->id, 'dificuldade' => $q->dificuldade])
            ->all();

        if (! $pool) {
            return $this->semQuestoes($tema, $modo, 'Ainda não há questões disponíveis para este tema.');
        }

        $ids = array_column($pool, 'id');
        [$escolhidas, $info] = $this->sorteio->sortear(
            $pool,
            $this->ultimasRespostas($usuarioId, $ids),
            (int) config('quiz.questoes_por_quiz', 10),
            Carbon::now(),
            (int) config('quiz.janela_sem_repeticao_dias', 7),
            $this->irmas($ids),
            $acaso,
        );

        $quiz = DB::transaction(function () use ($usuarioId, $tema, $modo, $escolhidas) {
            $quiz = Quiz::create(['usuario_id' => $usuarioId, 'tema' => $tema, 'modo' => $modo]);
            foreach ($escolhidas as $ordem => $escolhida) {
                QuizQuestao::create(['quiz_id' => $quiz->id, 'questao_id' => $escolhida['id'], 'ordem' => $ordem]);
            }

            return $quiz;
        });

        $questoes = Questao::whereIn('id', array_column($escolhidas, 'id'))->get()->keyBy('id');

        return [
            'quiz_id'  => $quiz->id,
            'tema'     => $tema,
            'modo'     => $modo,
            'questoes' => array_map(fn (array $c) => $this->questaoPublica($questoes[$c['id']]), $escolhidas),
            'aviso'    => $this->aviso($info),
            'info'     => ['proxima_liberacao' => $info['proxima_liberacao']?->toIso8601String()] + $info,
        ];
    }

    /**
     * Grava a resposta inicial e, se for a última, conclui o quiz. Devolve null se a questão não é deste quiz.
     */
    public function responder(Quiz $quiz, int $questaoId, int $indice): ?array
    {
        $letra = Questao::LETRAS[$indice];

        $resultado = DB::transaction(function () use ($quiz, $questaoId, $letra) {
            // Trava o quiz: duas respostas simultâneas não podem deixá-lo sem concluir.
            $quiz = Quiz::whereKey($quiz->id)->lockForUpdate()->firstOrFail();

            $item = QuizQuestao::with('questao')->where('quiz_id', $quiz->id)->where('questao_id', $questaoId)->first();
            if ($item === null) {
                return null;
            }

            // Só grava se ainda não havia resposta: a resposta inicial nunca é alterada.
            $gravou = QuizQuestao::whereKey($item->id)->whereNull('letra_escolhida')->update([
                'letra_escolhida' => $letra,
                'acertou'         => $letra === $item->questao->gabarito,
                'respondida_em'   => Carbon::now(),
            ]) === 1;
            $item->refresh();

            if ($quiz->finalizado_em === null && ! $quiz->itens()->whereNull('letra_escolhida')->exists()) {
                $this->concluir($quiz);
            }

            return [$quiz, $item, $gravou];
        });

        if ($resultado === null) {
            return null;
        }
        [$quiz, $item, $gravou] = $resultado;

        $saida = [
            'registrada'       => true,
            'ja_respondida'    => ! $gravou,
            'resposta_inicial' => Questao::indiceDaLetra($item->letra_escolhida),
            'quiz_finalizado'  => $quiz->finalizado_em !== null,
        ];
        if ($quiz->comFeedback()) {
            $saida['feedback'] = $this->gabaritoDaQuestao($item);
        }

        return $saida;
    }

    /** Gabarito com as justificativas, só das questões deste quiz que o aluno já respondeu. */
    public function gabarito(Quiz $quiz): array
    {
        $itens = $quiz->itens()->with('questao')->get();
        $respondidas = $itens->filter(fn (QuizQuestao $item) => $item->respondida());

        return [
            'quiz_id'    => $quiz->id,
            'tema'       => $quiz->tema,
            'modo'       => $quiz->modo,
            'finalizado' => $quiz->finalizado_em !== null,
            'pendentes'  => $itens->count() - $respondidas->count(),
            'questoes'   => $respondidas
                ->map(fn (QuizQuestao $item) => ['ordem' => $item->ordem] + $this->gabaritoDaQuestao($item))
                ->values()
                ->all(),
        ];
    }

    /** O que o aluno recebe ao iniciar o quiz: nunca inclui o gabarito nem as justificativas. */
    public function questaoPublica(Questao $questao): array
    {
        return [
            'id'          => $questao->id,
            'dificuldade' => $questao->dificuldade,
            'pergunta'    => $questao->enunciado(),
            'opcoes'      => $questao->opcoes(),
            'subtema'     => $questao->subtema,
            'referencias' => $this->referencias->referenciasTexto($questao->referencias),
        ];
    }

    private function gabaritoDaQuestao(QuizQuestao $item): array
    {
        $questao = $item->questao;

        return [
            'id'                       => $questao->id,
            'correta'                  => $questao->indiceCorreto(),
            'escolhida'                => Questao::indiceDaLetra($item->letra_escolhida),
            'acertou'                  => (bool) $item->acertou,
            'explicacao'               => $questao->justificativa,
            'explicacoes_alternativas' => array_map(
                fn (array $alternativa, int $indice) => [
                    'indice'     => $indice,
                    'letra'      => $alternativa['letra'],
                    'explicacao' => $alternativa['explicacao'],
                ],
                $questao->alternativas,
                array_keys($questao->alternativas),
            ),
            'fontes'                   => $this->referencias->citacoesDaQuestao($questao->referencias),
        ];
    }

    /**
     * Quiz concluído: registra a tentativa (parametros_quiz + respostas_quiz), que alimenta "Meu Desempenho",
     * o feedback da IA e o painel do professor.
     */
    private function concluir(Quiz $quiz): void
    {
        $itens = $quiz->itens()->with('questao')->get();
        $acertos = $itens->where('acertou', true)->count();

        $tentativa = $this->tentativas->registrarTentativa($quiz->usuario_id, [
            'conteudoAcessado' => $quiz->tema,
            'acertos'          => $acertos,
            'erros'            => $itens->count() - $acertos,
            'questoes'         => $itens->map(function (QuizQuestao $item) {
                $questao = $item->questao;
                $opcoes = $questao->opcoes();

                return [
                    'pergunta'           => $questao->enunciado(),
                    'subtema'            => $questao->subtema,
                    'resposta_escolhida' => $opcoes[Questao::indiceDaLetra($item->letra_escolhida)],
                    'resposta_correta'   => $opcoes[$questao->indiceCorreto()],
                    'acertou'            => (bool) $item->acertou,
                    'referencias'        => $this->referencias->referenciasTexto($questao->referencias),
                ];
            })->all(),
        ]);

        $quiz->update(['finalizado_em' => Carbon::now(), 'tentativa_id' => $tentativa->id]);
    }

    /**
     * Histórico individual: a última resposta do aluno a cada questão, só de quizzes CONCLUÍDOS
     * (quiz abandonado no meio vale como não feito).
     *
     * @return array<int, Carbon>
     */
    private function ultimasRespostas(int $usuarioId, array $ids): array
    {
        $linhas = DB::table('quiz_questoes as qq')
            ->join('quizzes as z', 'z.id', '=', 'qq.quiz_id')
            ->where('z.usuario_id', $usuarioId)
            ->whereNotNull('z.finalizado_em')
            ->whereNotNull('qq.respondida_em')
            ->whereIn('qq.questao_id', $ids)
            ->groupBy('qq.questao_id')
            ->selectRaw('qq.questao_id, max(qq.respondida_em) as ultima')
            ->get();

        $respondidas = [];
        foreach ($linhas as $linha) {
            $respondidas[(int) $linha->questao_id] = Carbon::parse($linha->ultima);
        }

        return $respondidas;
    }

    /** @return array<int, list<int>> */
    private function irmas(array $ids): array
    {
        $irmas = [];
        foreach (DB::table('questoes_irmas')->whereIn('questao_id', $ids)->get(['questao_id', 'irma_id']) as $par) {
            $irmas[(int) $par->questao_id][] = (int) $par->irma_id;
        }

        return $irmas;
    }

    private function aviso(array $info): ?string
    {
        $repetidas = $info['reaproveitadas'];
        if ($repetidas > 0) {
            $quantas = $repetidas === 1 ? '1 questão se repete' : "{$repetidas} questões se repetem";

            return "Você já respondeu, nos últimos dias, quase todas as questões disponíveis deste tema: {$quantas}, "
                . 'começando pelas que você respondeu há mais tempo.';
        }
        if ($info['entregues'] < $info['solicitadas']) {
            return "Este tema tem só {$info['entregues']} questões disponíveis no momento (o previsto eram {$info['solicitadas']}).";
        }

        return null;
    }

    private function semQuestoes(string $tema, string $modo, string $aviso): array
    {
        return ['quiz_id' => null, 'tema' => $tema, 'modo' => $modo, 'questoes' => [], 'aviso' => $aviso, 'info' => null];
    }
}
