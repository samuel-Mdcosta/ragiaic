<?php

namespace App\Http\Controllers;

use App\Providers\SenhaService;
use Illuminate\Http\Request;

class SenhaController extends Controller
{
    protected $senhaService;

    public function __construct(SenhaService $senhaService)
    {
        $this->senhaService = $senhaService;
    }

    public function verificarEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $usuario = $this->senhaService->buscarPorEmail($request->email);

        if (!$usuario) {
            return response()->json(['message' => 'E-mail não encontrado.'], 404);
        }

        return response()->json(['message' => 'E-mail encontrado.']);
    }

    public function redefinirSenha(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'senha'                 => 'required|min:8|confirmed',
            'senha_confirmation'    => 'required',
        ]);

        $usuario = $this->senhaService->buscarPorEmail($request->email);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        $this->senhaService->redefinirSenha($usuario, $request->senha);

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
}
