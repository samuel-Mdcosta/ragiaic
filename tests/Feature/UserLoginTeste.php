
<?php

namespace Tests\Feature; // Note que mudou para Feature

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_deve_retornar_sucesso_com_credenciais_validas()
    {
        // 1. Preparação (Arrange): Criamos o usuário no banco
        Usuario::create([
            'nome'  => 'Admin',
            'email' => 'admin@email.com',
            'senha' => Hash::make('secret123')
        ]);

        // 2. Ação (Act): Simulamos um POST para a rota de login
        // O Laravel automaticamente passa isso pelo Controller e pelo Service!
        $response = $this->postJson('/users/login', [
            'email' => 'admin@email.com',
            'senha' => 'secret123'
        ]);

        // 3. Verificação (Assert): Checamos a resposta
        $response->assertStatus(200)
            ->assertJson(['message' => 'Login bem-sucedido!']);
    }
}
