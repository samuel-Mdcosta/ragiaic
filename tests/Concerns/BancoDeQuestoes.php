<?php

namespace Tests\Concerns;

use App\Models\Questao;
use App\Models\Usuario;

/**
 * Apoio aos testes do quiz: questões revisadas de um tema e requisições como um usuário.
 */
trait BancoDeQuestoes
{
    /** O guard do Sanctum guarda o usuário entre requisições do mesmo teste: esquece-o antes de trocar. */
    protected function como(Usuario $usuario, ?string $token = null)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . ($token ?? $usuario->createToken('teste')->plainTextToken));
    }

    /** Cria questões revisadas do tema (12 fáceis, 20 médias e 8 difíceis por padrão), com o gabarito variando. */
    protected function criarQuestoes(string $tema, int $faceis = 12, int $medias = 20, int $dificeis = 8): void
    {
        $n = Questao::count();
        foreach (['facil' => $faceis, 'intermediaria' => $medias, 'dificil' => $dificeis] as $dificuldade => $quantas) {
            for ($k = 0; $k < $quantas; $k++, $n++) {
                $gabarito = Questao::LETRAS[$n % 4];
                Questao::create([
                    'codigo'                => "teste:{$tema}:{$n}",
                    'tema'                  => $tema,
                    'subtema'               => "Subtema {$n}",
                    'dificuldade'           => $dificuldade,
                    'objetivo_aprendizagem' => 'Objetivo.',
                    'caso_clinico'          => "Caso clínico {$n}.",
                    'comando'               => 'Qual é a alternativa correta?',
                    'alternativas'          => array_map(fn (string $letra) => [
                        'letra' => $letra, 'texto' => "Opção {$letra} da questão {$n}", 'explicacao' => "Explicação da {$letra}.",
                    ], Questao::LETRAS),
                    'gabarito'              => $gabarito,
                    'justificativa'         => "A correta é a {$gabarito}.",
                    'referencias'           => [[
                        'chunk_id' => sprintf('%024x', $n), 'file_name' => 'Neuroanatomia Funcional.pdf',
                        'titulo' => 'Neuroanatomia Funcional', 'autor' => 'Angelo Machado', 'edicao' => 4,
                        'editora' => 'Atheneu', 'publicacao' => 2021,
                    ]],
                    'status'                => 'revisada',
                    'bruto'                 => [],
                ]);
            }
        }
    }
}
