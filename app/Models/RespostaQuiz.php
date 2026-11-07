<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RespostaQuiz extends Model
{
    protected $table = 'respostas_quiz';

    protected $fillable = [
        'tentativa_id',
        'usuario_id',
        'tema',
        'subtema',
        'pergunta',
        'resposta_escolhida',
        'resposta_correta',
        'acertou',
        'referencias',
    ];

    protected $casts = [
        'acertou' => 'boolean',
    ];
}
