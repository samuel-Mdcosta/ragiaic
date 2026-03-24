<?php

namespace App\Providers;

use App\Models\TentativaQuizz;

class TentativaService
{
    public function registrarTentativa(int $usuarioId, array $dados)
    {
        $numeroDaTentativa = TentativaQuizz::where('usuario_id', $usuarioId)
            ->where('conteudoAcessado', $dados['conteudoAcessado'])
            ->count() + 1;

        return TentativaQuizz::create([
            'usuario_id'       => $usuarioId,
            'conteudoAcessado' => $dados['conteudoAcessado'],
            'quantTentativas'  => $numeroDaTentativa,
            'acertos'          => $dados['acertos'],
            'erros'            => $dados['erros'],
        ]);
    }

    public function calcularQuantTentativas(int $usuarioId)
    {
        return TentativaQuizz::where('usuario_id', $usuarioId)->count();
    }
}
