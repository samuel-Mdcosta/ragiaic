<?php

namespace Tests\Unit;

use App\Providers\SorteioQuizService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Regras do sorteio, sem banco. Portados dos testes do teste local (tests/test_regras.py).
 */
class SorteioQuizServiceTest extends TestCase
{
    private Carbon $agora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agora = Carbon::create(2026, 11, 13, 12, 0, 0, 'UTC');
    }

    // ---------- distribuição 30/50/20 ----------

    #[DataProvider('distribuicoes')]
    public function test_distribuicao_30_50_20_pelo_maior_resto(int $n, array $esperado): void
    {
        $this->assertSame($esperado, SorteioQuizService::distribuicao($n));
    }

    public static function distribuicoes(): array
    {
        return [
            '10 questões' => [10, ['facil' => 3, 'intermediaria' => 5, 'dificil' => 2]],
            '20 questões' => [20, ['facil' => 6, 'intermediaria' => 10, 'dificil' => 4]],
            '30 questões' => [30, ['facil' => 9, 'intermediaria' => 15, 'dificil' => 6]],
            '15 questões' => [15, ['facil' => 4, 'intermediaria' => 8, 'dificil' => 3]],
            '1 questão'   => [1, ['facil' => 0, 'intermediaria' => 1, 'dificil' => 0]],
            'nenhuma'     => [0, ['facil' => 0, 'intermediaria' => 0, 'dificil' => 0]],
        ];
    }

    public function test_distribuicao_soma_sempre_o_pedido(): void
    {
        foreach (range(0, 40) as $n) {
            $this->assertSame($n, array_sum(SorteioQuizService::distribuicao($n)));
        }
    }

    // ---------- sorteio ----------

    public function test_respeita_3_5_2_e_nao_repete_no_quiz(): void
    {
        [$quiz, $info] = $this->sortear($this->pool());

        $this->assertSame(['facil' => 3, 'intermediaria' => 5, 'dificil' => 2], $this->dificuldades($quiz));
        $this->assertCount(10, array_unique($this->ids($quiz)));
        $this->assertSame(['facil', 'facil', 'facil'], array_column(array_slice($quiz, 0, 3), 'dificuldade')); // fácil -> difícil
        $this->assertSame(10, $info['entregues']);
        $this->assertSame(0, $info['bloqueadas_na_janela']);
        $this->assertNull($info['proxima_liberacao']);
    }

    public function test_enquanto_ha_questoes_fora_da_janela_nenhuma_repete_nem_as_erradas(): void
    {
        $pool = $this->pool();
        $respondidas = [];
        $todas = [];
        for ($k = 0; $k < 3; $k++) {
            $quando = $this->agora->copy()->addHours($k);
            [$quiz, $info] = $this->sortear($pool, $respondidas, agora: $quando, semente: $k);
            $this->assertSame(0, $info['reaproveitadas']);
            $this->responder($respondidas, $quiz, $quando);
            $todas = [...$todas, ...$this->ids($quiz)];
        }

        $this->assertCount(30, array_unique($todas)); // 3 quizzes sem nenhuma repetição
    }

    public function test_esgotado_o_tema_volta_pelas_mais_antigas_mantendo_a_mistura(): void
    {
        $pool = $this->pool();
        $respondidas = [];
        $quizzes = [];
        for ($k = 0; $k < 3; $k++) {
            $quando = $this->agora->copy()->addHours($k);
            [$quiz] = $this->sortear($pool, $respondidas, agora: $quando, semente: $k);
            $this->responder($respondidas, $quiz, $quando);
            $quizzes[] = $this->conjunto($quiz);
        }

        // 4º quiz, 3 dias depois (tudo ainda dentro dos 7 dias): repete as do 1º, as respondidas há mais tempo.
        $depois = $this->agora->copy()->addDays(3);
        [$quiz4, $info] = $this->sortear($pool, $respondidas, agora: $depois, semente: 9);
        $this->assertSame($quizzes[0], $this->conjunto($quiz4));
        $this->assertSame(10, $info['entregues']);
        $this->assertSame(10, $info['reaproveitadas']);
        $this->assertSame(['facil' => 3, 'intermediaria' => 5, 'dificil' => 2], $this->dificuldades($quiz4));
        $this->assertTrue($info['proxima_liberacao']->equalTo($this->agora->copy()->addDays(7)));

        // Respondido o 4º, a fila anda: o 5º repete as do 2º quiz.
        $this->responder($respondidas, $quiz4, $depois);
        [$quiz5] = $this->sortear($pool, $respondidas, agora: $depois->copy()->addHour(), semente: 10);
        $this->assertSame($quizzes[1], $this->conjunto($quiz5));
    }

    public function test_so_recorre_as_respondidas_recentes_para_completar_o_que_falta(): void
    {
        $pool = $this->pool();
        $respondidas = [];
        foreach (array_slice($pool, 0, 25) as $i => $q) {
            $respondidas[$q['id']] = $this->agora->copy()->subDay()->addMinutes($i);
        }
        $novas = $this->ids(array_slice($pool, 25));

        [$quiz, $info] = $this->sortear($pool, $respondidas, semente: 4);

        $this->assertEmpty(array_diff($novas, $this->ids($quiz))); // as 5 novas entram sempre
        $this->assertSame(10, $info['entregues']);
        $this->assertSame(5, $info['reaproveitadas']);
        $this->assertCount(10, array_unique($this->ids($quiz)));
    }

    public function test_depois_da_janela_voltam_as_respondidas_mais_antigas_primeiro(): void
    {
        $pool = $this->pool();
        $respondidas = [];
        $primeiro = null;
        for ($k = 0; $k < 3; $k++) {
            $quando = $this->agora->copy()->addHours($k);
            [$quiz] = $this->sortear($pool, $respondidas, agora: $quando, semente: $k);
            $this->responder($respondidas, $quiz, $quando);
            $primeiro ??= $this->conjunto($quiz);
        }

        // Só o 1º quiz já passou dos 7 dias.
        [$quiz4, $info] = $this->sortear($pool, $respondidas, agora: $this->agora->copy()->addDays(7)->addMinute(), semente: 4);

        $this->assertSame($primeiro, $this->conjunto($quiz4));
        $this->assertSame(20, $info['bloqueadas_na_janela']);
        $this->assertSame(0, $info['reaproveitadas']);
    }

    public function test_prefere_nunca_respondidas_a_respondidas_ha_mais_de_7_dias(): void
    {
        $pool = $this->pool();
        // Respondidas há 30 dias: 4 fáceis, 4 médias e 3 difíceis. Sobram 5/11/3 nunca respondidas,
        // o suficiente para o quiz (3/5/2): nenhuma antiga deve entrar.
        $respondidas = [];
        foreach (['facil' => 4, 'intermediaria' => 4, 'dificil' => 3] as $dificuldade => $quantas) {
            foreach (array_slice($this->daDificuldade($pool, $dificuldade), 0, $quantas) as $q) {
                $respondidas[$q['id']] = $this->agora->copy()->subDays(30);
            }
        }

        foreach (range(0, 9) as $semente) {
            [$quiz] = $this->sortear($pool, $respondidas, semente: $semente);
            $this->assertEmpty(array_intersect($this->ids($quiz), array_keys($respondidas)));
        }
    }

    public function test_mistura_tem_precedencia_sobre_trazer_primeiro_as_nunca_respondidas(): void
    {
        // Todas as fáceis respondidas há mais de 7 dias: o quiz ainda traz 3 fáceis (as respondidas há mais
        // tempo), em vez de trocá-las por médias novas.
        $pool = $this->pool();
        $respondidas = [];
        foreach ($this->daDificuldade($pool, 'facil') as $q) {
            $respondidas[$q['id']] = $this->agora->copy()->subDays(30);
        }

        [$quiz] = $this->sortear($pool, $respondidas, semente: 3);

        $this->assertSame(['facil' => 3, 'intermediaria' => 5, 'dificil' => 2], $this->dificuldades($quiz));
        $this->assertCount(3, array_intersect($this->ids($quiz), array_keys($respondidas)));
    }

    public function test_completa_com_outras_dificuldades_quando_falta_numa(): void
    {
        [$quiz] = $this->sortear($this->pool(9, 15, 1));

        $this->assertCount(10, array_unique($this->ids($quiz)));
        $this->assertSame(1, $this->dificuldades($quiz)['dificil']);
    }

    public function test_tema_pequeno_entrega_o_que_tem_e_vazio_nao_entrega_nada(): void
    {
        [$quiz, $info] = $this->sortear($this->pool(2, 2, 1));
        $this->assertCount(5, $quiz);
        $this->assertSame(5, $info['entregues']);

        [$vazio, $infoVazio] = $this->sortear([]);
        $this->assertSame([], $vazio);
        $this->assertSame(0, $infoVazio['entregues']);
    }

    public function test_alunos_diferentes_recebem_quizzes_diferentes(): void
    {
        $quizzes = [];
        foreach (range(0, 19) as $semente) {
            $ids = $this->ids($this->sortear($this->pool(), semente: $semente)[0]);
            sort($ids);
            $quizzes[implode(',', $ids)] = true;
        }

        $this->assertGreaterThan(15, count($quizzes));
    }

    // ---------- questões irmãs (mesmo conceito, casos diferentes) ----------

    public function test_quiz_evita_duas_irmas_e_traz_um_caso_de_cada_conceito(): void
    {
        [$pool, $irmas] = $this->poolDeConceitos();

        foreach (range(0, 14) as $semente) {
            [$quiz, $info] = $this->sortear($pool, irmas: $irmas, semente: $semente);
            $this->assertCount(10, array_unique(array_column($quiz, 'conceito')));
            $this->assertSame(0, $info['pares_de_irmas_no_quiz']);
        }

        // Sem as irmãs, o acaso põe casos do mesmo conceito juntos com frequência.
        $repetidos = 0;
        foreach (range(0, 14) as $semente) {
            $repetidos += 10 - count(array_unique(array_column($this->sortear($pool, semente: $semente)[0], 'conceito')));
        }
        $this->assertGreaterThan(0, $repetidos);
    }

    public function test_ao_rever_um_conceito_vem_o_caso_visto_ha_mais_tempo(): void
    {
        [$pool, $irmas] = $this->poolDeConceitos();
        $respondidas = [];
        $vistosPorConceito = array_fill(0, 10, []);
        for ($k = 0; $k < 3; $k++) {
            $quando = $this->agora->copy()->addHours($k);
            [$quiz] = $this->sortear($pool, $respondidas, agora: $quando, irmas: $irmas, semente: $k);
            $this->responder($respondidas, $quiz, $quando);
            foreach ($quiz as $q) {
                $vistosPorConceito[$q['conceito']][] = $q['id'];
            }
        }
        foreach ($vistosPorConceito as $vistos) {
            $this->assertCount(3, array_unique($vistos)); // cada conceito visto com 3 casos diferentes
        }

        // 4º quiz (tudo respondido, dentro da janela): volta o caso visto há mais tempo de cada conceito.
        [$quiz4, $info] = $this->sortear($pool, $respondidas, agora: $this->agora->copy()->addDays(2), irmas: $irmas, semente: 9);

        $this->assertSame(10, $info['reaproveitadas']);
        $this->assertSame(0, $info['pares_de_irmas_no_quiz']);
        foreach ($quiz4 as $q) {
            $this->assertSame($vistosPorConceito[$q['conceito']][0], $q['id']);
        }
    }

    // ---------- apoio ----------

    private function sortear(
        array $pool,
        array $respondidas = [],
        int $n = 10,
        ?Carbon $agora = null,
        array $irmas = [],
        int $semente = 0,
    ): array {
        return (new SorteioQuizService())->sortear(
            $pool, $respondidas, $n, $agora ?? $this->agora, 7, $irmas, new Randomizer(new Mt19937($semente)),
        );
    }

    /** 9 fáceis, 15 médias e 6 difíceis por padrão. */
    private function pool(int $faceis = 9, int $medias = 15, int $dificeis = 6, string $tema = 'T'): array
    {
        $pool = [];
        foreach (['facil' => $faceis, 'intermediaria' => $medias, 'dificil' => $dificeis] as $dificuldade => $quantas) {
            for ($k = 0; $k < $quantas; $k++) {
                $pool[] = ['id' => "{$tema}-{$dificuldade}-{$k}", 'dificuldade' => $dificuldade, 'tema' => $tema];
            }
        }

        return $pool;
    }

    /** 10 conceitos x 3 casos; cada conceito numa dificuldade (3 fáceis, 5 médias, 2 difíceis). */
    private function poolDeConceitos(): array
    {
        $dificuldades = [...array_fill(0, 3, 'facil'), ...array_fill(0, 5, 'intermediaria'), ...array_fill(0, 2, 'dificil')];
        $pool = [];
        $irmas = [];
        for ($c = 0; $c < 10; $c++) {
            $ids = array_map(fn ($v) => "c{$c}v{$v}", range(0, 2));
            foreach ($ids as $id) {
                $pool[] = ['id' => $id, 'dificuldade' => $dificuldades[$c], 'conceito' => $c];
                $irmas[$id] = array_values(array_diff($ids, [$id]));
            }
        }

        return [$pool, $irmas];
    }

    private function responder(array &$respondidas, array $quiz, Carbon $quando): void
    {
        foreach ($quiz as $q) {
            $respondidas[$q['id']] = $quando->copy();
        }
    }

    private function daDificuldade(array $pool, string $dificuldade): array
    {
        return array_values(array_filter($pool, fn ($q) => $q['dificuldade'] === $dificuldade));
    }

    private function ids(array $quiz): array
    {
        return array_column($quiz, 'id');
    }

    private function conjunto(array $quiz): array
    {
        $ids = $this->ids($quiz);
        sort($ids);

        return $ids;
    }

    private function dificuldades(array $quiz): array
    {
        $contagem = ['facil' => 0, 'intermediaria' => 0, 'dificil' => 0];
        foreach ($quiz as $q) {
            $contagem[$q['dificuldade']]++;
        }

        return $contagem;
    }
}
