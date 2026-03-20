<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use app\Http\Controllers\UsuarioController;
use App\Service\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UsuarioServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_deve_criptografar_a_senha()
    {
        $controller = new UsuarioController(new UsuarioService());
        $dados = ['nome' => 'Admin', 'email' => 'admin@email.com', 'senha' => 'secret123'];

        $request = new Request($dados);
        $controller->cadastro($request);

        $usuario = Usuario::where('email', 'admin@email.com')->first();

        $this->assertNotEquals('secret123', $usuario->senha);
        $this->assertTrue(Hash::check('secret123', $usuario->senha));
    }
}
