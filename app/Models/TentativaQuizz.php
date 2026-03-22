<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
