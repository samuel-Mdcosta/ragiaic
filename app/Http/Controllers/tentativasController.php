<?php

namespace App\Http\Controllers;

use App\Providers\TentativaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

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
            'acertos' => 'required|integer|min:0|max:10',
            'erros' => 'required|integer|min:0|max:10',
        ]);

        if ($dadosValidados['acertos'] + $dadosValidados['erros'] !== 10) {
            return response()->json([
                'message' => 'A soma de acertos e erros deve ser igual a 10.',
            ], 422);
        }

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
            'quantidade' => $quantTentativas,
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
            'tema' => ['required', 'string', Rule::in(config('temas.quiz'))],
        ]);

        try {
            $response = Http::timeout(60)
                ->withHeaders(['X-API-Key' => config('services.python.secret')])
                ->post(config('services.python.url') . '/quizz', [
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
