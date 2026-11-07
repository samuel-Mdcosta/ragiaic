<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Em produção CACHE_STORE=database, e o rate limiting de login, cadastro e
 * perguntas usa esse cache. Os testes rodam com cache em memória, então este
 * teste usa o store "database" explicitamente para garantir que as migrations
 * criam as tabelas que ele precisa.
 */
class CacheBancoTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_criam_as_tabelas_do_cache(): void
    {
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
    }

    public function test_rate_limiting_funciona_com_cache_em_banco(): void
    {
        $limitador = new RateLimiter(Cache::store('database'));

        $limitador->hit('login|127.0.0.1', 60);
        $limitador->hit('login|127.0.0.1', 60);

        $this->assertSame(2, $limitador->attempts('login|127.0.0.1'));
        $this->assertFalse($limitador->tooManyAttempts('login|127.0.0.1', 5));
    }
}
