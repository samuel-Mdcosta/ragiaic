<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avaliação de satisfação que o aluno responde ao concluir cada quiz (perguntas em config/avaliacao.php).
     * Uma por quiz. As notas ficam uma por linha, com o texto da pergunta como estava quando foi respondida,
     * para dar para analisar por pergunta mesmo depois que o questionário mudar.
     */
    public function up(): void
    {
        Schema::create('avaliacoes_quiz', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quiz_id')->unique()->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('versao_questionario', 50);
            $table->text('comentario')->nullable();      // resposta da pergunta aberta (opcional)

            $table->timestamps();
        });

        Schema::create('avaliacoes_quiz_notas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('avaliacao_id')->constrained('avaliacoes_quiz')->cascadeOnDelete();
            $table->string('pergunta', 50);               // chave da pergunta no questionário
            $table->text('texto');                        // texto da pergunta quando foi respondida
            $table->unsignedTinyInteger('nota');          // 0 (muito ruim) a 5 (excelente)

            $table->unique(['avaliacao_id', 'pergunta']);
            $table->index('pergunta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes_quiz_notas');
        Schema::dropIfExists('avaliacoes_quiz');
    }
};
