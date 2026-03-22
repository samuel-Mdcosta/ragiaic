<?php

namespace App\Http\Controllers;

use App\Providers\ChatService;
use Illuminate\Http\Request;

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
            'usoChat' => $usoChat
        ], 201);
    }

    public function quantUsoChat(Request $request)
    {
        $usuarioId = $request->user()->id;

        $quantUso = $this->chatService->calcularQuantUsoChat($usuarioId);

        return response()->json([
            'quantUso' => $quantUso
        ]);
    }

    public function tempoUsoChat(Request $request)
    {
        $usuarioId = $request->user()->id;

        $quantUso = $this->chatService->calcularQuantUsoChat($usuarioId);

        return response()->json([
            'quantUso' => $quantUso
        ]);
    }
}
