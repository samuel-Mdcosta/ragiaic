<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        // Uma linha por questão respondida: é o que permite ao feedback de desempenho
        // enxergar em quais subtemas (conceitos) de cada tema o aluno mais erra.
        Schema::create('respostas_quiz', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tentativa_id')->constrained('parametros_quiz')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');

            $table->string('tema', 255);
            $table->string('subtema', 255)->nullable();
            $table->text('pergunta');
            $table->text('resposta_escolhida')->nullable();
            $table->text('resposta_correta')->nullable();
            $table->boolean('acertou');
            $table->string('referencias', 500)->nullable();

            $table->timestamps();

            $table->index(['usuario_id', 'tema', 'acertou']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('respostas_quiz');
    }
};
