<?php

namespace App\Providers;

use App\Models\RespostaQuiz;
use App\Models\TentativaQuizz;
use Illuminate\Support\Collection;

/**
 * Métricas de rendimento do aluno, calculadas de forma determinística.
 * A IA não decide números nem quais áreas são prioritárias: isso sai daqui.
 */
class DesempenhoService
{
    // Faixas iguais às cores do painel do professor (Admin.jsx): >= 70 bom, >= 50 atenção.
    public const TAXA_BOM = 70;
    public const TAXA_ATENCAO = 50;

    // Quantas tentativas recentes de um tema definem o nível atual do aluno nele.
    public const TENTATIVAS_RECENTES = 3;

    // Variação (em pontos percentuais) da última tentativa que conta como tendência.
    public const VARIACAO_TENDENCIA = 10;

    public const LIMITE_AREAS_PRIORITARIAS = 3;
    public const LIMITE_QUESTOES_ERRADAS = 15;
    public const LIMITE_SUBTEMAS_ACERTADOS = 10;
    public const LIMITE_TEMAS = 50;

    /**
     * Desempenho por tema, do pior para o melhor (pela taxa recente).
     */
    public function calcularDesempenhoPorTema(int $usuarioId): array
    {
        $tentativas = TentativaQuizz::where('usuario_id', $usuarioId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'conteudoAcessado', 'acertos', 'erros', 'created_at'])
            ->filter(fn ($t) => ((int) $t->acertos + (int) $t->erros) > 0);

        return $tentativas
            ->groupBy('conteudoAcessado')
            ->map(fn (Collection $doTema, string $tema) => $this->resumirTema($tema, $doTema->values()))
            ->sortBy([
                ['taxa_recente', 'asc'],
                ['erros', 'desc'],
            ])
            ->values()
            ->all();
    }

    /**
     * Visão geral para a tela "Meu Desempenho".
     */
    public function resumoGeral(int $usuarioId): array
    {
        $porTema = $this->calcularDesempenhoPorTema($usuarioId);

        $acertos = array_sum(array_column($porTema, 'acertos'));
        $questoes = array_sum(array_column($porTema, 'questoes'));
        $praticados = array_column($porTema, 'tema');

        return [
            'questoes_respondidas' => $questoes,
            'taxa_acerto'          => $this->taxa($acertos, $questoes),
            'tentativas'           => array_sum(array_column($porTema, 'tentativas')),
            'desempenho_por_tema'  => $porTema,
            'temas_nao_praticados' => $this->temasNaoPraticados($praticados),
        ];
    }

    /**
     * Temas que precisam de atenção (nível crítico ou atenção), no máximo três, do pior para o melhor.
     */
    public function selecionarAreasPrioritarias(array $desempenhoPorTema): array
    {
        return collect($desempenhoPorTema)
            ->filter(fn ($tema) => $tema['nivel'] !== 'bom')
            ->take(self::LIMITE_AREAS_PRIORITARIAS)
            ->values()
            ->all();
    }

    /**
     * Dados enviados ao motor de IA (/feedback): números já calculados, as áreas prioritárias
     * com as questões erradas mais recentes e os temas ainda não praticados.
     */
    public function montarDadosParaIA(int $usuarioId, array $desempenhoPorTema): array
    {
        $areas = array_map(fn (array $tema) => [
            'tema'               => $tema['tema'],
            'questoes_erradas'   => $this->questoesErradasRecentes($usuarioId, $tema['tema']),
            'subtemas_acertados' => $this->subtemasAcertados($usuarioId, $tema['tema']),
        ], $this->selecionarAreasPrioritarias($desempenhoPorTema));

        $campos = ['tema', 'tentativas', 'questoes', 'acertos', 'erros', 'taxa_acerto', 'taxa_recente', 'nivel', 'tendencia'];

        return [
            'desempenho_por_tema' => array_map(
                fn (array $tema) => array_intersect_key($tema, array_flip($campos)),
                array_slice($desempenhoPorTema, 0, self::LIMITE_TEMAS)
            ),
            'areas_prioritarias'   => $areas,
            'temas_nao_praticados' => $this->temasNaoPraticados(array_column($desempenhoPorTema, 'tema')),
        ];
    }

    private function resumirTema(string $tema, Collection $tentativas): array
    {
        $acertos = (int) $tentativas->sum('acertos');
        $erros = (int) $tentativas->sum('erros');

        $recentes = $tentativas->slice(-self::TENTATIVAS_RECENTES);
        $acertosRecentes = (int) $recentes->sum('acertos');
        $questoesRecentes = $acertosRecentes + (int) $recentes->sum('erros');
        $taxaRecente = $this->taxa($acertosRecentes, $questoesRecentes);

        return [
            'tema'          => $tema,
            'tentativas'    => $tentativas->count(),
            'questoes'      => $acertos + $erros,
            'acertos'       => $acertos,
            'erros'         => $erros,
            'taxa_acerto'   => $this->taxa($acertos, $acertos + $erros),
            'taxa_recente'  => $taxaRecente,
            'nivel'         => $this->classificarNivel($taxaRecente),
            'tendencia'     => $this->calcularTendencia($tentativas),
            'ultimo_acesso' => optional($tentativas->last()->created_at)->toIso8601String(),
        ];
    }

    public function classificarNivel(float $taxa): string
    {
        if ($taxa >= self::TAXA_BOM) {
            return 'bom';
        }

        return $taxa >= self::TAXA_ATENCAO ? 'atencao' : 'critico';
    }

    /**
     * Compara a última tentativa com a média das anteriores do mesmo tema.
     */
    private function calcularTendencia(Collection $tentativas): ?string
    {
        if ($tentativas->count() < 2) {
            return null;
        }

        $ultima = $tentativas->last();
        $anteriores = $tentativas->slice(0, -1);

        $taxaUltima = $this->taxa((int) $ultima->acertos, (int) $ultima->acertos + (int) $ultima->erros);
        $acertosAnteriores = (int) $anteriores->sum('acertos');
        $taxaAnteriores = $this->taxa($acertosAnteriores, $acertosAnteriores + (int) $anteriores->sum('erros'));

        $variacao = $taxaUltima - $taxaAnteriores;

        return match (true) {
            $variacao >= self::VARIACAO_TENDENCIA  => 'melhorando',
            $variacao <= -self::VARIACAO_TENDENCIA => 'piorando',
            default                                => 'estavel',
        };
    }

    private function questoesErradasRecentes(int $usuarioId, string $tema): array
    {
        return RespostaQuiz::where('usuario_id', $usuarioId)
            ->where('tema', $tema)
            ->where('acertou', false)
            ->orderByDesc('id')
            ->limit(self::LIMITE_QUESTOES_ERRADAS)
            ->get(['pergunta', 'subtema', 'resposta_escolhida', 'resposta_correta'])
            ->map(fn (RespostaQuiz $resposta) => [
                'pergunta'           => $resposta->pergunta,
                'subtema'            => $resposta->subtema,
                'resposta_escolhida' => $resposta->resposta_escolhida,
                'resposta_correta'   => $resposta->resposta_correta,
            ])
            ->all();
    }

    private function subtemasAcertados(int $usuarioId, string $tema): array
    {
        return RespostaQuiz::where('usuario_id', $usuarioId)
            ->where('tema', $tema)
            ->where('acertou', true)
            ->whereNotNull('subtema')
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('subtema')
            ->map(fn ($subtema) => trim($subtema))
            ->filter()
            ->unique(fn ($subtema) => mb_strtolower($subtema))
            ->take(self::LIMITE_SUBTEMAS_ACERTADOS)
            ->values()
            ->all();
    }

    private function temasNaoPraticados(array $praticados): array
    {
        return array_values(array_diff(config('temas.quiz', []), $praticados));
    }

    private function taxa(int $acertos, int $total): float
    {
        return $total > 0 ? round(($acertos / $total) * 100, 1) : 0.0;
    }
}
