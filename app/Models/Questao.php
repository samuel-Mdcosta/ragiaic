<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Questão do banco (importada do pipeline de geração). Os textos já estão prontos para o aluno.
 */
class Questao extends Model
{
    public const LETRAS = ['A', 'B', 'C', 'D'];
    public const DIFICULDADES = ['facil', 'intermediaria', 'dificil'];
    public const STATUS = ['pendente_revisao', 'revisada', 'reprovada', 'arquivada'];

    // Só as questões revisadas pelo docente chegam aos alunos.
    public const STATUS_SERVIDO = 'revisada';

    protected $table = 'questoes';

    protected $fillable = [
        'codigo',
        'tema',
        'subtema',
        'dificuldade',
        'objetivo_aprendizagem',
        'caso_clinico',
        'comando',
        'alternativas',
        'gabarito',
        'justificativa',
        'referencias',
        'status',
        'lote_id',
        'posicao',
        'gerada_em',
        'bruto',
    ];

    protected $casts = [
        'alternativas' => 'array',
        'referencias'  => 'array',
        'bruto'        => 'array',
        'posicao'      => 'integer',
        'gerada_em'    => 'datetime',
    ];

    public function scopeServiveis(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SERVIDO);
    }

    /** Caso clínico e comando, como o aluno lê a questão. */
    public function enunciado(): string
    {
        return trim($this->caso_clinico) . "\n\n" . trim($this->comando);
    }

    /** Textos das alternativas na ordem A-D (o índice 0..3 é o que o front envia e recebe). */
    public function opcoes(): array
    {
        return array_column($this->alternativas, 'texto');
    }

    public function indiceCorreto(): int
    {
        return self::indiceDaLetra($this->gabarito);
    }

    public static function indiceDaLetra(string $letra): int
    {
        return (int) array_search($letra, self::LETRAS, true);
    }
}
