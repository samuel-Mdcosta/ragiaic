<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioCadastroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sem restrição de domínio por padrão; o teste de domínio configura o seu.
        config(['services.institucional.dominio' => null]);
    }

    private function cadastrar(array $dados = [])
    {
        return $this->postJson('/api/users/cadastro', array_merge([
            'nome'  => 'Teste User',
            'email' => 'teste@email.com',
            'senha' => '123456',
        ], $dados));
    }

    public function test_usuario_pode_ser_cadastrado_com_sucesso(): void
    {
        $this->cadastrar()
            ->assertCreated()
            ->assertJson(['message' => 'Usuário cadastrado com sucesso!']);

        $this->assertDatabaseHas('usuarios', ['email' => 'teste@email.com']);
    }

    public function test_senha_e_salva_criptografada(): void
    {
        $this->cadastrar(['senha' => 'secret123'])->assertCreated();

        $usuario = Usuario::where('email', 'teste@email.com')->firstOrFail();
        $this->assertNotSame('secret123', $usuario->senha);
        $this->assertTrue(Hash::check('secret123', $usuario->senha));
    }

    public function test_email_repetido_e_recusado(): void
    {
        $this->cadastrar()->assertCreated();

        $this->cadastrar()
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_exige_email_institucional_quando_o_dominio_esta_configurado(): void
    {
        config(['services.institucional.dominio' => 'universidade.edu.br']);

        $this->cadastrar(['email' => 'aluno@gmail.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->cadastrar(['email' => 'aluno@universidade.edu.br'])->assertCreated();
    }
}
