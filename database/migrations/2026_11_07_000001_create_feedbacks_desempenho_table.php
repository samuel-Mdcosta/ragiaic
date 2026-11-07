<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        // Histórico dos feedbacks gerados pela IA. O último registro funciona como cache:
        // só é gerado um novo quando o aluno tem tentativas novas (total_tentativas mudou).
        Schema::create('feedbacks_desempenho', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');

            $table->unsignedInteger('total_tentativas');
            $table->json('conteudo');
            $table->json('uso_tokens')->nullable();

            $table->timestamps();

            $table->index(['usuario_id', 'id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('feedbacks_desempenho');
    }
};
