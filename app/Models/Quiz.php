<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um quiz sorteado para um aluno. Concluído quando todas as questões têm resposta.
 */
class Quiz extends Model
{
    public const MODOS = ['com_feedback', 'sem_feedback'];

    protected $table = 'quizzes';

    protected $fillable = [
        'usuario_id',
        'tema',
        'modo',
        'finalizado_em',
        'tentativa_id',
    ];

    protected $casts = [
        'finalizado_em' => 'datetime',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(QuizQuestao::class, 'quiz_id')->orderBy('ordem');
    }

    public function tentativa(): BelongsTo
    {
        return $this->belongsTo(TentativaQuizz::class, 'tentativa_id');
    }

    public function comFeedback(): bool
    {
        return $this->modo === 'com_feedback';
    }
}
