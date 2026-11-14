<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada quiz sorteado para um aluno, as questões entregues (na ordem) e a resposta INICIAL de cada uma.
     * É o histórico individual usado no sorteio: uma questão respondida não volta antes de 7 dias.
     */
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('tema');
            $table->string('modo', 20);                    // com_feedback | sem_feedback

            // Preenchido quando a última questão é respondida. Quiz abandonado no meio vale como não feito.
            $table->timestamp('finalizado_em')->nullable();
            // Tentativa (parametros_quiz) criada ao concluir o quiz, com acertos e erros calculados aqui.
            $table->foreignId('tentativa_id')->nullable()->constrained('parametros_quiz')->nullOnDelete();

            $table->timestamps();

            $table->index(['usuario_id', 'finalizado_em']);
        });

        Schema::create('quiz_questoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            // Sem cascade: uma questão com histórico não pode ser apagada (arquive-a).
            $table->foreignId('questao_id')->constrained('questoes');
            $table->unsignedSmallInteger('ordem');

            // Resposta inicial do aluno: gravada uma vez e nunca alterada.
            $table->string('letra_escolhida', 1)->nullable();
            $table->boolean('acertou')->nullable();
            $table->timestamp('respondida_em')->nullable();

            $table->unique(['quiz_id', 'questao_id']);
            $table->unique(['quiz_id', 'ordem']);
            $table->index('questao_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questoes');
        Schema::dropIfExists('quizzes');
    }
};
