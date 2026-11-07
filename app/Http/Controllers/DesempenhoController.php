<?php

namespace App\Http\Controllers;

use App\Exceptions\FeedbackIndisponivelException;
use App\Providers\DesempenhoService;
use App\Providers\FeedbackDesempenhoService;
use Illuminate\Http\Request;

class DesempenhoController extends Controller
{
    public function __construct(
        protected DesempenhoService $desempenhoService,
        protected FeedbackDesempenhoService $feedbackService,
    ) {
    }

    // Números do rendimento do aluno por tema (rápido, sem IA).
    public function desempenho(Request $request)
    {
        return response()->json(
            $this->desempenhoService->resumoGeral($request->user()->id)
        );
    }

    // Feedback de desempenho gerado pela IA: áreas com mais dificuldade, o que, onde e por que estudar.
    public function feedback(Request $request)
    {
        try {
            $feedback = $this->feedbackService->obterFeedback($request->user()->id);
        } catch (FeedbackIndisponivelException $e) {
            return response()->json([
                'message' => 'Não foi possível gerar seu feedback de desempenho agora. Tente novamente em instantes.',
            ], 503);
        }

        return response()->json($feedback);
    }
}
