<?php

namespace App\Http\Controllers;

use App\Providers\TentativaService;
use Illuminate\Http\Request;

class TentativasController extends Controller
{
    protected $tentativaService;

    public function __construct(TentativaService $tentativaService)
    {
        $this->tentativaService = $tentativaService;
    }

    public function salvarTentativas(Request $request)
    {
        $dadosValidados = $request->validate([
            'conteudoAcessado' => 'required|string|max:255',
            'acertos'          => 'required|integer',
            'erros'            => 'required|integer',
        ]);

        $usuarioId = $request->user()->id;

        $tentativa = $this->tentativaService->registrarTentativa($usuarioId, $dadosValidados);

        return response()->json([
            'message'   => 'Tentativa salva com sucesso!',
            'tentativa' => $tentativa
        ], 201);
    }
}
