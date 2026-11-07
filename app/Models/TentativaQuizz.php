<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TentativaQuizz extends Model
{
    protected $table = 'parametros_quiz';

    protected $fillable = [
        'usuario_id',
        'conteudoAcessado',
        'quantTentativas',
        'acertos',
        'erros'
    ];

    public function respostas(): HasMany
    {
        return $this->hasMany(RespostaQuiz::class, 'tentativa_id');
    }
}
