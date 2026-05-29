<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\parametroChat;
use App\Models\TentativaQuizz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
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
}
