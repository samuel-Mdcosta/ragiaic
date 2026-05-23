<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class SenhaService
{
    public function buscarPorEmail(string $email): ?Usuario
    {
        return Usuario::where('email', $email)->first();
    }

    public function redefinirSenha(Usuario $usuario, string $novaSenha): void
    {
        $usuario->senha = Hash::make($novaSenha);
        $usuario->save();
    }
}
