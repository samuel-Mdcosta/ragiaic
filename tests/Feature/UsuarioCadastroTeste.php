
<?

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioCadastroTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_pode_ser_cadastrado_com_sucesso()
    {
        $dados = [
            'nome'  => 'Teste User',
            'email' => 'teste@email.com',
            'senha' => '123456'
        ];

        $response = $this->postJson('/users/cadastro', $dados);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Usuário cadastrado com sucesso!']);


        $this->assertDatabaseHas('usuarios', [
            'email' => 'teste@email.com'
        ]);
    }
}
