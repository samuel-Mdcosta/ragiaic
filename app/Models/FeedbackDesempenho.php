<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackDesempenho extends Model
{
    protected $table = 'feedbacks_desempenho';

    protected $fillable = [
        'usuario_id',
        'total_tentativas',
        'conteudo',
        'uso_tokens',
    ];

    protected $casts = [
        'total_tentativas' => 'integer',
        'conteudo'         => 'array',
        'uso_tokens'       => 'array',
    ];
}
