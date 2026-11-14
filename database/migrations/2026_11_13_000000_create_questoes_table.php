<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Banco de questões. É preenchido só pela importação (php artisan questoes:importar), a partir dos
     * arquivos do pipeline de geração. Os textos guardados nas colunas já estão prontos para o aluno
     * (sem os códigos dos trechos de livro); o registro original fica intacto em `bruto`.
     */
    public function up(): void
    {
        Schema::create('questoes', function (Blueprint $table) {
            $table->id();

            // Identificador do conteúdo, pela mesma regra do pipeline: sha256 do caso clínico, comando e tema
            // normalizados. Reimportar a mesma questão atualiza a linha e preserva o histórico dos alunos.
            $table->string('codigo', 80)->unique();

            $table->string('tema');
            $table->string('subtema');
            $table->string('dificuldade', 20);           // facil | intermediaria | dificil
            $table->text('objetivo_aprendizagem');
            $table->text('caso_clinico');
            $table->text('comando');
            $table->json('alternativas');                 // [{letra, texto, explicacao}], de A a D
            $table->string('gabarito', 1);                // letra da alternativa correta
            $table->text('justificativa');                // justificativa do gabarito
            $table->json('referencias');                  // [{chunk_id, file_name, titulo, autor, edicao, editora, publicacao}]

            // Só as revisadas são servidas aos alunos. Arquivar (em vez de apagar) preserva o histórico.
            $table->string('status', 20)->default('pendente_revisao'); // pendente_revisao | revisada | reprovada | arquivada

            $table->string('lote_id')->nullable();
            $table->unsignedInteger('posicao')->nullable();
            $table->timestamp('gerada_em')->nullable();
            $table->json('bruto');

            $table->timestamps();

            $table->index(['tema', 'status', 'dificuldade']);
        });

        // Questões "irmãs": do mesmo tema e escritas a partir de trechos de livro parecidos (semelhança de
        // Jaccard >= 40%), ou seja, o mesmo conceito com casos clínicos diferentes. Recalculada a cada
        // importação; a relação é simétrica (os dois sentidos são gravados).
        Schema::create('questoes_irmas', function (Blueprint $table) {
            $table->foreignId('questao_id')->constrained('questoes')->cascadeOnDelete();
            $table->foreignId('irma_id')->constrained('questoes')->cascadeOnDelete();
            $table->float('similaridade');

            $table->primary(['questao_id', 'irma_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questoes_irmas');
        Schema::dropIfExists('questoes');
    }
};
