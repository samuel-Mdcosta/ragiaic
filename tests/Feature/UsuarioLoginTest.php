<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioLoginTest extends TestCase
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

    private function login(string $senha)
    {
        return $this->postJson('/api/users/login', ['email' => 'admin@email.com', 'senha' => $senha]);
    }

    public function test_login_com_credenciais_validas_retorna_token(): void
    {
        $this->login('secret123')
            ->assertOk()
            ->assertJson(['message' => 'Login realizado com sucesso!'])
            ->assertJsonStructure(['token', 'role', 'usuario'])
            ->assertJsonMissingPath('usuario.senha');
    }

    public function test_login_com_senha_errada_retorna_401(): void
    {
        $this->login('errada123')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Credenciais inválidas!'])
            ->assertJsonMissingPath('token');
    }

    public function test_login_bloqueia_apos_cinco_tentativas_por_minuto(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('errada123')->assertUnauthorized();
        }

        // Mesmo com a senha certa, a sexta tentativa no mesmo minuto é barrada.
        $this->login('secret123')->assertStatus(429);
    }
}
