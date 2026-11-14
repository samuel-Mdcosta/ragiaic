<?php

/*
|--------------------------------------------------------------------------
| Avaliação de satisfação do quiz
|--------------------------------------------------------------------------
|
| Ao concluir um quiz, o aluno responde estas perguntas antes de ver o
| resultado e o feedback (obrigatório: o backend só libera o gabarito do quiz
| e a análise do tutor depois da avaliação). O front monta o questionário a
| partir daqui (GET /api/users/login/avaliacao/questionario), então trocar
| as perguntas não exige mudar o front.
|
| PERGUNTAS DE EXEMPLO: ainda serão definidas.
| Ao mudar as perguntas, mude também a `versao`. Cada resposta guarda a
| versão e o texto da pergunta como estava quando foi respondida
| (tabelas avaliacoes_quiz e avaliacoes_quiz_notas). Não reaproveite a
| `chave` de uma pergunta para outra pergunta diferente.
|
*/

return [
    'versao' => 'exemplo-1',

    // Notas de 0 a 5, todas obrigatórias.
    'escala' => [
        'min'        => 0,
        'max'        => 5,
        'rotulo_min' => 'Muito ruim',
        'rotulo_max' => 'Excelente',
    ],

    'perguntas' => [
        ['chave' => 'clareza', 'texto' => 'As questões estavam claras e fáceis de entender?'],
        ['chave' => 'relacao_com_o_tema', 'texto' => 'As questões tinham relação com o tema que você escolheu?'],
        ['chave' => 'dificuldade', 'texto' => 'O nível de dificuldade foi adequado para você?'],
        ['chave' => 'avaliacao_geral', 'texto' => 'De modo geral, como você avalia este quiz?'],
    ],

    // Pergunta aberta, opcional.
    'comentario' => [
        'texto' => 'Quer deixar algum comentário ou sugestão sobre o quiz?',
        'max'   => 2000,
    ],
];
