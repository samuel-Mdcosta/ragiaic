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
}
