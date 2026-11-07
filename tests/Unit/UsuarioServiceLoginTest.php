<?php

namespace Tests\Unit;

use App\Models\Usuario;
use App\Providers\UsuarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioServiceLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Usuario::create([
            'nome'  => 'Admin',
            'email' => 'admin@email.com',
            'senha' => Hash::make('secret123'),
        ]);
    }

    public function test_retorna_o_usuario_com_credenciais_corretas(): void
    {
        $usuario = (new UsuarioService())->loginAuth('admin@email.com', 'secret123');

        $this->assertNotNull($usuario);
        $this->assertSame('admin@email.com', $usuario->email);
    }

    public function test_retorna_null_com_senha_errada_ou_email_inexistente(): void
    {
        $service = new UsuarioService();

        $this->assertNull($service->loginAuth('admin@email.com', 'errada123'));
        $this->assertNull($service->loginAuth('ninguem@email.com', 'secret123'));
    }
}
