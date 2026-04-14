<?php

namespace App\Http\Controllers;

use App\Providers\TentativaService;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\support\facades\Http;

class TentativasController extends Controller
{
    protected $tentativaService;

    public function __construct(TentativaService $tentativaService)
    {
        $this->tentativaService = $tentativaService;
    }

    public function registrarTentativa(Request $request)
    {
        $dadosValidados = $request->validate([
            'conteudoAcessado' => 'required|string|max:255',
            'acertos' => 'required|integer',
            'erros' => 'required|integer',
        ]);

        $usuarioId = $request->user()->id;

        $tentativa = $this->tentativaService->registrarTentativa($usuarioId, $dadosValidados);

        return response()->json([
            'message' => 'Tentativa salva com sucesso!',
            'tentativa' => $tentativa,
        ], 201);
    }

    public function quantTentativas(Request $request)
    {
        $usuarioId = $request->user()->id;

        $quantTentativas = $this->tentativaService->calcularQuantTentativas($usuarioId);

        return response()->json([
            'quantTentativas' => $quantTentativas,
        ]);
    }

    public function stats(Request $request)
    {
        $usuarioId = $request->user()->id;

        $stats = $this->tentativaService->calcularStats($usuarioId);

        return response()->json($stats);
    }

    public function requestPerguntas(Request $request)
    {
        $request->validate([
            'tema' => 'required|string|max:255',
        ]);

        try {
            $response = Http::timeout(60)->post('https://iniciacao-cientifica-tutor-virtual-main.onrender.com/quizz', [
                'texto' => $request->input('tema'),
            ]);
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'Não foi possível conectar ao serviço de perguntas. Tente novamente mais tarde.',
            ], 503);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => 'Erro ao obter perguntas. Tente novamente mais tarde.',
            ], 500);
        }

        return response()->json($response->json());
    }
}
