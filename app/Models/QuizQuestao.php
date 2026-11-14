<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Questão entregue em um quiz, com a resposta inicial do aluno (nula até ele responder).
 */
class QuizQuestao extends Model
{
    protected $table = 'quiz_questoes';

    public $timestamps = false;

    protected $fillable = [
        'quiz_id',
        'questao_id',
        'ordem',
        'letra_escolhida',
        'acertou',
        'respondida_em',
    ];

    protected $casts = [
        'ordem'         => 'integer',
        'acertou'       => 'boolean',
        'respondida_em' => 'datetime',
    ];

    public function questao(): BelongsTo
    {
        return $this->belongsTo(Questao::class, 'questao_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function respondida(): bool
    {
        return $this->letra_escolhida !== null;
    }
}
