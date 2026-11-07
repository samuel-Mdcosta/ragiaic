<?php

namespace App\Providers;

use App\Models\TentativaQuizz;
use Illuminate\Support\Facades\DB;

class TentativaService
{
    public function registrarTentativa(int $usuarioId, array $dados)
    {
        return DB::transaction(function () use ($usuarioId, $dados) {
            $numeroDaTentativa = TentativaQuizz::where('usuario_id', $usuarioId)
                ->where('conteudoAcessado', $dados['conteudoAcessado'])
                ->count() + 1;

            $tentativa = TentativaQuizz::create([
                'usuario_id'       => $usuarioId,
                'conteudoAcessado' => $dados['conteudoAcessado'],
                'quantTentativas'  => $numeroDaTentativa,
                'acertos'          => $dados['acertos'],
                'erros'            => $dados['erros'],
            ]);

            // Detalhe por questão (opcional): base do feedback de desempenho por subtema.
            $questoes = $dados['questoes'] ?? [];
            if (!empty($questoes)) {
                $tentativa->respostas()->createMany(array_map(fn (array $questao) => [
                    'usuario_id'         => $usuarioId,
                    'tema'               => $dados['conteudoAcessado'],
                    'subtema'            => $questao['subtema'] ?? null,
                    'pergunta'           => $questao['pergunta'],
                    'resposta_escolhida' => $questao['resposta_escolhida'] ?? null,
                    'resposta_correta'   => $questao['resposta_correta'] ?? null,
                    'acertou'            => (bool) $questao['acertou'],
                    'referencias'        => $questao['referencias'] ?? null,
                ], $questoes));
            }

            return $tentativa;
        });
    }

    public function calcularQuantTentativas(int $usuarioId)
    {
        return TentativaQuizz::where('usuario_id', $usuarioId)->count();
    }

    public function calcularStats(int $usuarioId): array
    {
        $totais = TentativaQuizz::where('usuario_id', $usuarioId)
            ->selectRaw('SUM(acertos) as totalAcertos, SUM(erros) as totalErros')
            ->first();

        $totalAcertos = (int) $totais->totalAcertos;
        $totalErros   = (int) $totais->totalErros;
        $questoesRespondidas = $totalAcertos + $totalErros;

        $taxaAcerto = $questoesRespondidas > 0
            ? round(($totalAcertos / $questoesRespondidas) * 100)
            : 0;

        return [
            'questoesRespondidas' => $questoesRespondidas,
            'taxaAcerto'          => $taxaAcerto,
        ];
    }
}
