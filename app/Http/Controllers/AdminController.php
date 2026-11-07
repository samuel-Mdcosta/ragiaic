<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\parametroChat;
use App\Models\TentativaQuizz;
use App\Providers\SenhaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    protected $senhaService;

    public function __construct(SenhaService $senhaService)
    {
        $this->senhaService = $senhaService;
    }

    public function listarAlunos(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        $alunos = Usuario::where('role', '!=', 'admin')->get();

        $dado = $alunos->map(function ($aluno) {
            $chat = parametroChat::where('usuario_id', $aluno->id)
                ->selectRaw('COUNT(*) as sessoes, SUM("tempoUsoChat") as tempo, MAX(created_at) as ultimo_chat')
                ->first();

            $quiz = TentativaQuizz::where('usuario_id', $aluno->id)
                ->selectRaw('COUNT(*) as tentativas, SUM("acertos") as acertos, SUM(erros) as erros, MAX(created_at) as ultimo_quiz')
                ->first();

            $acertos = (int) $quiz->acertos;
            $erros   = (int) $quiz->erros;
            $total   = $acertos + $erros;
            $taxaAcerto = $total > 0 ? round(($acertos / $total) * 100, 1) : 0;

            $ultimoChat = $chat->ultimo_chat;
            $ultimoQuiz = $quiz->ultimo_quiz;
            $ultimoAcesso = match (true) {
                $ultimoChat && $ultimoQuiz => max($ultimoChat, $ultimoQuiz),
                (bool) $ultimoChat         => $ultimoChat,
                (bool) $ultimoQuiz         => $ultimoQuiz,
                default                    => null,
            };

            return [
                'id'               => $aluno->id,
                'nome'             => $aluno->nome,
                'email'            => $aluno->email,
                'foto'             => $aluno->foto,
                'sessoes_chat'     => (int) $chat->sessoes,
                'tempo_total_chat' => (int) $chat->tempo,
                'tentativas_quiz'  => (int) $quiz->tentativas,
                'acertos_quiz'     => $acertos,
                'erros_quiz'       => $erros,
                'taxa_acerto'      => $taxaAcerto,
                'ultimo_acesso'    => $ultimoAcesso,
            ];
        });

        return response()->json([
            'message' => 'Alunos listados com sucesso',
            'dado'    => $dado,
        ]);
    }

    public function listarTemasAluno(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        $aluno = Usuario::find($id);
        if (!$aluno) {
            return response()->json(['message' => 'Aluno não encontrado.'], 404);
        }

        $temas = TentativaQuizz::where('usuario_id', $id)
            ->groupBy('conteudoAcessado')
            ->selectRaw('"conteudoAcessado" as tema, COUNT(*) as tentativas, SUM("acertos") as acertos, SUM(erros) as erros, MAX(created_at) as ultimo_acesso')
            ->get();

        $dado = $temas->map(function ($t) {
            $acertos = (int) $t->acertos;
            $erros   = (int) $t->erros;
            $total   = $acertos + $erros;
            $taxaAcerto = $total > 0 ? round(($acertos / $total) * 100, 1) : 0;

            return [
                'tema'          => $t->tema,
                'tentativas'    => (int) $t->tentativas,
                'acertos'       => $acertos,
                'erros'         => $erros,
                'taxa_acerto'   => $taxaAcerto,
                'ultimo_acesso' => $t->ultimo_acesso,
            ];
        });

        return response()->json([
            'message' => 'Temas do aluno listados com sucesso',
            'dado'    => $dado,
        ]);
    }

    // Único caminho de redefinição de senha: só o admin, a pedido do monitor.
    public function redefinirSenhaAluno(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        $dados = $request->validate([
            'novaSenha' => 'required|min:6',
        ]);

        $aluno = Usuario::find($id);
        if (!$aluno) {
            return response()->json(['message' => 'Aluno não encontrado.'], 404);
        }

        $this->senhaService->redefinirSenha($aluno, $dados['novaSenha']);

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
}
