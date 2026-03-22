<?php

namespace App\Providers;

use App\Models\parametroChat;

class ChatService
{

    public function RegistrarUsoChat($usuarioId, $tempoUso)
    {
        return parametroChat::create([
            'usuario_id' => $usuarioId,
            'usoChat' => true,
            'tempoUsoChat' => $tempoUso
        ]);
    }

    public function calcularQuantUsoChat($usuarioId)
    {
        return parametroChat::where('usuario_id', $usuarioId)->count();
    }

    public function calcularTempoTotalUsoChat($usuarioId)
    {
        return parametroChat::where('usuario_id', $usuarioId)->sum('tempoUsoChat');
    }
}
