<?php

namespace App\Providers;

use App\Models\AvaliacaoQuiz;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;

/**
 * Avaliação de satisfação do quiz. Ao concluir um quiz, o aluno avalia o quiz (notas de 0 a 5, obrigatórias,
 * e um comentário opcional) antes de ver o resultado: o gabarito do quiz e a análise do tutor só são liberados
 * depois da avaliação. As perguntas ficam em config/avaliacao.php.
 */
class AvaliacaoQuizService
{
    /** O questionário, como o front o monta. */
    public function questionario(): array
    {
        return [
            'versao'     => config('avaliacao.versao'),
            'escala'     => config('avaliacao.escala'),
            'perguntas'  => array_map(
                fn (array $pergunta) => ['chave' => $pergunta['chave'], 'texto' => $pergunta['texto']],
                config('avaliacao.perguntas', []),
            ),
            'comentario' => config('avaliacao.comentario'),
        ];
    }

    /**
     * Grava a avaliação do quiz. A primeira é a que vale: avaliar de novo não altera nada.
     *
     * @param  array<string, int>  $notas  chave da pergunta => nota (já validadas)
     * @return bool true se gravou agora, false se o quiz já tinha sido avaliado
     */
    public function avaliar(Quiz $quiz, array $notas, ?string $comentario): bool
    {
        return DB::transaction(function () use ($quiz, $notas, $comentario) {
            // Trava o quiz: dois envios simultâneos não podem gravar duas avaliações.
            $quiz = Quiz::whereKey($quiz->id)->lockForUpdate()->firstOrFail();
            if ($quiz->avaliacao()->exists()) {
                return false;
            }

            $avaliacao = AvaliacaoQuiz::create([
                'quiz_id'             => $quiz->id,
                'usuario_id'          => $quiz->usuario_id,
                'versao_questionario' => config('avaliacao.versao'),
                'comentario'          => $comentario,
            ]);
            foreach (config('avaliacao.perguntas', []) as $pergunta) {
                $avaliacao->notas()->create([
                    'pergunta' => $pergunta['chave'],
                    'texto'    => $pergunta['texto'],
                    'nota'     => (int) $notas[$pergunta['chave']],
                ]);
            }

            return true;
        });
    }

    /** O resultado de um quiz concluído (gabarito e feedback) só sai depois da avaliação. */
    public function resultadoBloqueado(Quiz $quiz): bool
    {
        return $quiz->finalizado_em !== null && $quiz->avaliacao()->doesntExist();
    }

    /**
     * O último quiz concluído do aluno, se ele ainda não o avaliou. Enquanto houver um, a análise do tutor
     * espera pela avaliação (na tela de resultado e em "Meu Desempenho").
     */
    public function pendente(int $usuarioId): ?Quiz
    {
        $ultimo = Quiz::where('usuario_id', $usuarioId)
            ->whereNotNull('finalizado_em')
            ->orderByDesc('finalizado_em')
            ->orderByDesc('id')
            ->first();

        return $ultimo && $this->resultadoBloqueado($ultimo) ? $ultimo : null;
    }
}
