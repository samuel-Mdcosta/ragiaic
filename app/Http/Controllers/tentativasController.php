<?php

namespace App\Http\Controllers;

use App\Models\TentativaQuizz;
use Illuminate\Http\Request;

class tentativasController extends Controller
{
    public function salvarTentaivas(Request $request)
    {
        $request->validate([
            'conteudoAcessado' => 'required|string|max:255',
            'quantTentativas' => 'required|integer',
            'acertos' => 'required|integer',
            'erros' => 'required|integer',
        ]);

        $usuarioId = $request->user()->id;

        $tentativasAmount = TentativaQuizz::where('usuario_id', $usuarioId)
            ->where('conteudoAcessado', $request->conteudoAcessado)
            ->count() + 1;

        $tentativa = TentativaQuizz::create([
            'usuario_id' => $usuarioId,
            'conteudoAcessado' => $request->conteudoAcessado,
            'quantTentativas' => $request->quantTentativas,
            'acertos' => $request->acertos,
            'erros' => $request->erros,
        ]);

        return response()->json([
            'message' => 'Tentativa salva com sucesso!',
            'tentativa' => $tentativa
        ], 201);
    }
}
