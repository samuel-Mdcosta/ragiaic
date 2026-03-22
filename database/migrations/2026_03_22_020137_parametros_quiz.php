<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('parametros_quiz', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');

            $table->string('conteudoAcessado', 255);
            $table->integer('quantTentativas'); #1 tentativa tem 10 questoes
            $table->integer('acertos');
            $table->integer('erros');

            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('parametros_quiz');
    }
};
