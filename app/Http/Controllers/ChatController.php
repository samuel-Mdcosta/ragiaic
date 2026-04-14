<?php

namespace App\Http\Controllers;

use App\Providers\ChatService;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function salvarUsoChat(Request $request)
    {
        $dadosValidados = $request->validate([
            'tempoUsoChat' => 'required|integer',
        ]);

        $usuarioId = $request->user()->id;
        $usoChat = $this->chatService->RegistrarUsoChat($usuarioId, $dadosValidados['tempoUsoChat']);

        return response()->json([
            'message' => 'Uso do chat registrado com sucesso!',
            'usoChat' => $usoChat,
        ], 201);
    }

    public function quantUsoChat(Request $request)
    {
        $usuarioId = $request->user()->id;

        $quantUso = $this->chatService->calcularQuantUsoChat($usuarioId);

        return response()->json([
            'quantUso' => $quantUso,
        ]);
    }

    public function tempoUsoChat(Request $request)
    {
        $usuarioId = $request->user()->id;

        $tempoTotal = $this->chatService->calcularTempoTotalUsoChat($usuarioId);

        return response()->json([
            'tempoTotalUso' => $tempoTotal,
        ]);
    }

    public function requestChat(Request $request)
    {
        $request->validate([
            'pergunta' => 'required|string|max:2000',
        ]);

        try {
            $response = Http::timeout(60)->post('https://iniciacao-cientifica-tutor-virtual-main.onrender.com/llm', [
                'texto' => $request->input('pergunta'),
            ]);
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'Não foi possível conectar ao serviço de IA. Tente novamente mais tarde.',
            ], 503);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => 'Erro ao obter resposta do chat. Tente novamente mais tarde.',
            ], 500);
        }

        return response()->json($response->json());
    }
}
