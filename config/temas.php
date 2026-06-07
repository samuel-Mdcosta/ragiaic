<?php

/*
|--------------------------------------------------------------------------
| Temas do Quiz (lista canônica)
|--------------------------------------------------------------------------
|
| Fonte única dos temas aceitos pelo endpoint de geração de questões.
| DEVE ser idêntica, byte a byte, à lista no front em
| FrontIaIC-teste/src/data/temasQuiz.js. É o texto exato repassado ao
| motor de IA (/quizz) e usado como `conteudoAcessado` nas tentativas.
|
*/

return [
    'quiz' => [
        'Sistemas Sensoriais e Vias Aferentes',
        'Sensação, Percepção e Atenção',
        'Organização Cortical e Áreas Funcionais',
        'Hipocampo e Sistema Límbico',
        'Memória Espacial, Temporal e Cognitiva',
        'Linguagem, Leitura e Escrita',
        'Cognição e Funções Executivas',
        'Memória e Aprendizagem',
        'Neuroplasticidade e Estimulação Cognitiva',
        'Desenvolvimento Neurocognitivo Infantil',
    ],
];
