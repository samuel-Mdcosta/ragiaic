<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Providers\QuizService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Quiz com o banco de questões: iniciar (sorteio sem gabarito), responder uma questão e ver o gabarito.
 */
class QuizController extends Controller
{
    public function __construct(protected QuizService $quizService)
    {
    }

    public function iniciar(Request $request)
    {
        $dados = $request->validate([
            'tema' => ['required', 'string', Rule::in(config('temas.quiz'))],
            'modo' => ['sometimes', 'string', Rule::in(Quiz::MODOS)],
        ]);

        return response()->json(
            $this->quizService->iniciar($request->user()->id, $dados['tema'], $dados['modo'] ?? 'com_feedback')
        );
    }

    public function responder(Request $request, int $quiz)
    {
        $quiz = $this->quizDoUsuario($request, $quiz);

        $dados = $request->validate([
            'questao_id' => 'required|integer',
            'indice'     => 'required|integer|min:0|max:3',
        ]);

        $resposta = $this->quizService->responder($quiz, (int) $dados['questao_id'], (int) $dados['indice']);
        if ($resposta === null) {
            return response()->json(['message' => 'Esta questão não faz parte do quiz.'], 404);
        }

        return response()->json($resposta);
    }

    public function gabarito(Request $request, int $quiz)
    {
        return response()->json($this->quizService->gabarito($this->quizDoUsuario($request, $quiz)));
    }

    private function quizDoUsuario(Request $request, int $id): Quiz
    {
        $quiz = Quiz::where('id', $id)->where('usuario_id', $request->user()->id)->first();
        abort_if($quiz === null, 404, 'Quiz não encontrado.');

        return $quiz;
    }
}
