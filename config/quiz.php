<?php

/*
|--------------------------------------------------------------------------
| Quiz com o banco de questões
|--------------------------------------------------------------------------
|
| As questões vêm prontas e revisadas da tabela `questoes` (importadas com
| `php artisan questoes:importar`). O sorteio de cada quiz segue as regras
| de App\Providers\SorteioQuizService: mistura 30% fáceis / 50% médias /
| 20% difíceis, primeiro as nunca respondidas e nenhuma repetida dentro
| da janela abaixo (só se o tema se esgotar, voltando pelas mais antigas).
|
*/

return [
    'questoes_por_quiz' => (int) env('QUIZ_QUESTOES', 10),

    // Dias em que uma questão respondida não volta para o mesmo aluno.
    'janela_sem_repeticao_dias' => (int) env('QUIZ_JANELA_DIAS', 7),

    // Semelhança mínima (Jaccard dos trechos de livro) para duas questões do mesmo tema serem "irmãs".
    'similaridade_irmas' => 0.40,

    // Arquivo importado por padrão.
    'arquivo_questoes' => database_path('data/questoes_40_por_tema.json'),
    'arquivo_livros' => database_path('data/livros.json'),
];
