<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{

    public function loginAuth($email, $senha)
    {
        $usuario = Usuario::where('email', $email)->first();

        if ($usuario && Hash::check($senha, $usuario->senha)) {
            return $usuario;
        }
    }

    public function atualizarSenha($id, $novaSenha)
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->senha = Hash::make($novaSenha);

        $usuario->save();
        return $usuario;
    }

    // Atualiza apenas os campos enviados (nome e/ou foto).
    public function atualizarPerfil($id, array $dados)
    {
        $usuario = Usuario::findOrFail($id);

        if (array_key_exists('nome', $dados)) {
            $usuario->nome = $dados['nome'];
        }

        if (array_key_exists('foto', $dados)) {
            $usuario->foto = $dados['foto'];
        }

        $usuario->save();
        return $usuario;
    }
}
