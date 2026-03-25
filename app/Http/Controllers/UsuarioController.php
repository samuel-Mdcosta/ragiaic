<?php

namespace App\Http\Controllers;

use App\Providers\UsuarioService;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{


    protected $usuarioService;

    public function __construct(UsuarioService $usuarioService)
    {
        $this->usuarioService = $usuarioService;
    }




    public function cadastro(Request $request)
    {
        $request->validate([
            'nome'  => 'required|string|min:3',
            'email' => 'required|email|unique:usuarios',
            'senha' => 'required|min:6'
        ]);

        Usuario::create([
            'nome'  => $request->nome,
            'email' => $request->email,
            'senha' => Hash::make($request->senha),
        ]);

        return response()->json(['message' => 'Usuário cadastrado com sucesso!'], 201);
    }


    public function login(Request $request)
    {
        $usuario = $this->usuarioService->loginAuth($request->email, $request->senha);

        if (!$usuario) {
            return response()->json(['message' => 'Credenciais inválidas!'], 401);
        }

        $token = $usuario->createToken('token-de-acesso')->plainTextToken;

        return response()->json([
            'message' => 'Login realizado com sucesso!',
            'token' => $token,
            'usuario' => $usuario
        ]);
    }

    public function atualizar(Request $request)
    {
        $request->validate([
            'novaSenha' => 'required|min:6'
        ]);

        $usuarioLogado = $request->user();
        $id = $usuarioLogado->id;

        return $this->usuarioService->atualizarSenha($id, $request->novaSenha);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso!']);
    }
}
