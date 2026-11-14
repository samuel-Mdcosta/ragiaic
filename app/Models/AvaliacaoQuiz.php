<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Avaliação de satisfação de um quiz concluído (uma por quiz).
 */
class AvaliacaoQuiz extends Model
{
    protected $table = 'avaliacoes_quiz';

    protected $fillable = [
        'quiz_id',
        'usuario_id',
        'versao_questionario',
        'comentario',
    ];

    public function notas(): HasMany
    {
        return $this->hasMany(AvaliacaoQuizNota::class, 'avaliacao_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }
}
