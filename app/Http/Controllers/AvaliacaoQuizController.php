<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Providers\AvaliacaoQuizService;
use Illuminate\Http\Request;

/**
 * Avaliação de satisfação que o aluno responde ao concluir o quiz, antes de ver o resultado e o feedback.
 */
class AvaliacaoQuizController extends Controller
{
    public function __construct(protected AvaliacaoQuizService $avaliacaoService)
    {
    }

    public function questionario()
    {
        return response()->json($this->avaliacaoService->questionario());
    }

    public function avaliar(Request $request, int $quiz)
    {
        $quiz = Quiz::where('id', $quiz)->where('usuario_id', $request->user()->id)->first();
        if ($quiz === null) {
            return response()->json(['message' => 'Quiz não encontrado.'], 404);
        }
        if ($quiz->finalizado_em === null) {
            return response()->json(['message' => 'Conclua o quiz antes de avaliá-lo.'], 422);
        }

        $escala = config('avaliacao.escala');
        $regras = [
            'notas'      => 'required|array',
            'comentario' => 'nullable|string|max:' . config('avaliacao.comentario.max', 2000),
        ];
        foreach (config('avaliacao.perguntas', []) as $pergunta) {
            $regras["notas.{$pergunta['chave']}"] = "required|integer|min:{$escala['min']}|max:{$escala['max']}";
        }
        $dados = $request->validate($regras, [
            'notas.required'   => 'Responda todas as perguntas da avaliação.',
            'notas.*.required' => 'Responda todas as perguntas da avaliação.',
            'notas.*.integer'  => "As notas vão de {$escala['min']} a {$escala['max']}.",
            'notas.*.min'      => "As notas vão de {$escala['min']} a {$escala['max']}.",
            'notas.*.max'      => "As notas vão de {$escala['min']} a {$escala['max']}.",
            'comentario.max'   => 'O comentário pode ter até :max caracteres.',
        ]);

        $comentario = trim((string) ($dados['comentario'] ?? '')) ?: null;
        $gravou = $this->avaliacaoService->avaliar($quiz, $dados['notas'], $comentario);

        return response()->json(['registrada' => true, 'ja_avaliado' => ! $gravou], $gravou ? 201 : 200);
    }
}
