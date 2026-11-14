<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nota de 0 a 5 dada a uma pergunta da avaliação de satisfação.
 */
class AvaliacaoQuizNota extends Model
{
    protected $table = 'avaliacoes_quiz_notas';

    public $timestamps = false;

    protected $fillable = [
        'avaliacao_id',
        'pergunta',
        'texto',
        'nota',
    ];

    protected $casts = [
        'nota' => 'integer',
    ];
}
