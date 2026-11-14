<?php

/*
|--------------------------------------------------------------------------
| Temas do Quiz (lista canônica)
|--------------------------------------------------------------------------
|
| Fonte única dos temas aceitos pelo endpoint que inicia o quiz. São os
| temas do banco de questões (coluna `questoes.tema`, vinda do pipeline de
| geração) e DEVEM ser idênticos, byte a byte, à lista no front em
| FrontIaIC-teste/src/data/temasQuiz.js. O texto também é gravado como
| `conteudoAcessado` nas tentativas.
|
| A importação (php artisan questoes:importar) avisa quando o arquivo
| traz um tema que não está aqui.
|
*/

return [
    'quiz' => [
        'Vias visuais',
        'Organização cerebral',
        'Hipocampo e memória espacial',
        'Sistemas e processos da memória',
        'Percepção visual: vias dorsal e ventral',
        'Hemisférios cerebrais e linguagem',
        'Redes neurais da leitura e da escrita',
        'Neuroplasticidade e desenvolvimento infantil',
    ],
];
