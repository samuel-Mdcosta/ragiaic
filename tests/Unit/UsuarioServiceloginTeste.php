<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Providers\UsuarioService;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_deve_validar_login_corretamente()
    {
        // 1. Preparação
        Usuario::create([
            'nome'  => 'Admin',
            'email' => 'admin@email.com',
            'senha' => Hash::make('secret123')
        ]);

        $service = new UsuarioService();

        // 2. Ação: Chamamos direto o método do service
        $usuarioLogado = $service->loginAuth('admin@email.com', 'secret123');

        // 3. Verificação: O service deve retornar o objeto do usuário, e não nulo
        $this->assertNotNull($usuarioLogado);
        $this->assertEquals('admin@email.com', $usuarioLogado->email);
    }
}
