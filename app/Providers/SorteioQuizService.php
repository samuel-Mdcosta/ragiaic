<?php

namespace App\Providers;

use App\Models\Questao;
use Carbon\CarbonInterface;
use Random\Randomizer;

/**
 * Sorteio das questões de um quiz para um aluno. Regras puras (sem banco), testáveis isoladamente.
 *
 * Ordem de preferência:
 *   1. questões que o aluno nunca respondeu;
 *   2. respondidas há mais tempo que a janela (7 dias), da mais antiga para a mais recente;
 *   3. só se o tema não tiver questões suficientes fora da janela: as respondidas dentro dela, numa fila
 *      (a vista há mais tempo volta primeiro). O quiz sai completo e o aluno é avisado das repetidas.
 * Em cada etapa vale a mistura 30% fáceis / 50% médias / 20% difíceis; se faltar numa dificuldade,
 * completa com as outras.
 *
 * Questões "irmãs" (mesmo conceito, casos clínicos diferentes) fazem o sorteio pensar em conceitos:
 * um quiz evita duas irmãs e, ao rever um conceito, traz o caso visto há mais tempo, nunca o mais recente.
 */
class SorteioQuizService
{
    public const PROPORCAO = ['facil' => 0.30, 'intermediaria' => 0.50, 'dificil' => 0.20];

    // Desempate do maior resto na distribuição: média, depois fácil, depois difícil.
    private const PRIORIDADE_DO_RESTO = ['intermediaria', 'facil', 'dificil'];

    /**
     * Divide n questões em fácil/média/difícil pelo método do maior resto. n=10 -> 3/5/2; n=20 -> 6/10/4.
     *
     * @return array<string, int> na ordem facil, intermediaria, dificil
     */
    public static function distribuicao(int $n): array
    {
        $brutos = [];
        $base = [];
        foreach (Questao::DIFICULDADES as $d) {
            $brutos[$d] = $n * self::PROPORCAO[$d];
            $base[$d] = (int) $brutos[$d];
        }

        $porResto = Questao::DIFICULDADES;
        usort($porResto, fn (string $a, string $b) => [
            -($brutos[$a] - $base[$a]), array_search($a, self::PRIORIDADE_DO_RESTO, true),
        ] <=> [
            -($brutos[$b] - $base[$b]), array_search($b, self::PRIORIDADE_DO_RESTO, true),
        ]);

        foreach (array_slice($porResto, 0, $n - array_sum($base)) as $d) {
            $base[$d]++;
        }

        return $base;
    }

    /**
     * @param  list<array{id: int|string, dificuldade: string}>  $pool  questões elegíveis
     * @param  array<int|string, CarbonInterface>  $respondidas  id => última resposta do aluno (só de quizzes concluídos)
     * @param  array<int|string, list<int|string>>  $irmas  id => ids das questões irmãs
     * @return array{0: list<array>, 1: array<string, mixed>} [questões na ordem fácil -> difícil, informações do sorteio]
     */
    public function sortear(
        array $pool,
        array $respondidas,
        int $n,
        CarbonInterface $agora,
        int $janelaDias = 7,
        array $irmas = [],
        ?Randomizer $acaso = null,
    ): array {
        $acaso ??= new Randomizer();
        $janela = $janelaDias * 86400;

        $vistoEm = fn ($id): int => isset($respondidas[$id]) ? $respondidas[$id]->getTimestamp() : 0;
        $etapa = function ($id) use ($respondidas, $agora, $janela): int {
            if (! isset($respondidas[$id])) {
                return 0;
            }

            return $agora->getTimestamp() - $respondidas[$id]->getTimestamp() >= $janela ? 1 : 2;
        };

        $irmasDe = [];
        foreach ($irmas as $id => $outras) {
            $irmasDe[$id] = array_fill_keys($outras, true);
        }

        // (etapa, última vez que o CONCEITO foi visto, última vez que ESTE caso foi visto, desempate aleatório)
        $ordem = [];
        foreach ($pool as $c) {
            $conceito = $vistoEm($c['id']);
            foreach (array_keys($irmasDe[$c['id']] ?? []) as $irma) {
                $conceito = max($conceito, $vistoEm($irma));
            }
            $ordem[$c['id']] = [$etapa($c['id']), $conceito, $vistoEm($c['id']), $acaso->getInt(0, PHP_INT_MAX)];
        }

        $escolhidas = [];
        $usadas = [];

        $pegar = function (array $candidatas, int $quantas, int $ateEtapa) use (&$escolhidas, &$usadas, $ordem, $irmasDe): void {
            for ($k = 0; $k < $quantas; $k++) {
                $melhor = null;
                $melhorChave = null;
                foreach ($candidatas as $c) {
                    $o = $ordem[$c['id']];
                    if (isset($usadas[$c['id']]) || $o[0] > $ateEtapa) {
                        continue;
                    }
                    $chave = [
                        $o[0],                                                       // etapa
                        count(array_intersect_key($irmasDe[$c['id']] ?? [], $usadas)), // evita irmãs no mesmo quiz
                        $o[1], $o[2], $o[3],                                          // conceito, caso, acaso
                    ];
                    if ($melhorChave === null || $chave < $melhorChave) {
                        [$melhor, $melhorChave] = [$c, $chave];
                    }
                }
                if ($melhor === null) {
                    return;
                }
                $escolhidas[] = $melhor;
                $usadas[$melhor['id']] = true;
            }
        };

        $daDificuldade = fn (string $d): array => array_values(array_filter($pool, fn ($c) => $c['dificuldade'] === $d));
        // Closure comum (não `fn`): precisa ver $escolhidas como está agora, não como estava ao ser criada.
        $quantasDe = function (string $d) use (&$escolhidas): int {
            return count(array_filter($escolhidas, fn ($c) => $c['dificuldade'] === $d));
        };

        // Primeiro só com o que está fora da janela (etapas 0 e 1), mantendo a mistura; depois completa com as
        // outras dificuldades; só então recorre às respondidas dentro da janela (etapa 2), uma por vez, sempre da
        // dificuldade que mais falta para a mistura.
        $alvo = self::distribuicao($n);
        foreach ($alvo as $dificuldade => $quantas) {
            $pegar($daDificuldade($dificuldade), $quantas, 1);
        }
        $pegar($pool, $n - count($escolhidas), 1);

        while (count($escolhidas) < $n) {
            $faltas = [];
            foreach (Questao::DIFICULDADES as $d) {
                $faltas[$d] = $alvo[$d] - $quantasDe($d);
            }
            $porFalta = Questao::DIFICULDADES;
            usort($porFalta, fn (string $a, string $b) => $faltas[$b] <=> $faltas[$a]);

            $antes = count($escolhidas);
            foreach ($porFalta as $d) {
                $pegar($daDificuldade($d), 1, 2);
                if (count($escolhidas) > $antes) {
                    break;
                }
            }
            if (count($escolhidas) === $antes) {
                break; // o tema não tem mais questões
            }
        }

        $posicao = array_flip(Questao::DIFICULDADES);
        usort($escolhidas, fn ($a, $b) => $posicao[$a['dificuldade']] <=> $posicao[$b['dificuldade']]);

        $naJanela = array_values(array_filter($pool, fn ($c) => $etapa($c['id']) === 2));
        $proximaLiberacao = null;
        foreach ($naJanela as $c) {
            $liberacao = $respondidas[$c['id']]->copy()->addSeconds($janela);
            if ($proximaLiberacao === null || $liberacao->lessThan($proximaLiberacao)) {
                $proximaLiberacao = $liberacao;
            }
        }

        $ids = array_column($escolhidas, 'id');
        $paresDeIrmas = 0;
        foreach ($ids as $i => $x) {
            foreach (array_slice($ids, $i + 1) as $y) {
                $paresDeIrmas += isset($irmasDe[$x][$y]) ? 1 : 0;
            }
        }

        return [$escolhidas, [
            'solicitadas'            => $n,
            'entregues'              => count($escolhidas),
            'reaproveitadas'         => count(array_filter($escolhidas, fn ($c) => $etapa($c['id']) === 2)),
            'bloqueadas_na_janela'   => count($naJanela),
            'proxima_liberacao'      => $proximaLiberacao,
            'pares_de_irmas_no_quiz' => $paresDeIrmas,
        ]];
    }
}
