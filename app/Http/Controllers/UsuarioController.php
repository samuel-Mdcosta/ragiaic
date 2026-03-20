
<?

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{


    public function cadastro(Request $request)
    {
        $request->validate([
            'nome'  => 'required|string|min:3',
            'email' => 'required|email|unique:usuarios',
            'senha' => 'required|min:6'
        ]);

        Usuario::create([
            'nome'  => $request->nome,
            'email' => $request->email,
            'senha' => Hash::make($request->senha),
        ]);

        return response()->json(['message' => 'Usuário cadastrado com sucesso!'], 201);
    }

    public function atualizar(Request $request, $id)
    {
        $usuario = Usuario::findOrFail($id);

        $request->validate([
            'senha' => 'sometimes|required|min:6'
        ]);

        if ($request->has('senha')) {
            $usuario->senha = Hash::make($request->senha);
        }

        $usuario->save();

        return response()->json(['message' => 'Senha atualizado com sucesso!']);
    }

    public function login($email, $senha)
    {
        $usuario = Usuario::findOrFail($email);
        if (Hash::check($senha, $usuario->senha)) {
            return response()->json(['message' => 'Login bem-sucedido!']);
        } else {
            return response()->json(['message' => 'Credenciais inválidas!'], 401);
        }
    }
}
