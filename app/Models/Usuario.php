<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Model
{
    use HasApiTokens;

    protected $table = 'usuarios';
    public $timestamps = false;

    protected $fillable = [
        'nome',
        'email',
        'senha'
    ];

    protected $hidden = [
        'senha',
    ];
}
