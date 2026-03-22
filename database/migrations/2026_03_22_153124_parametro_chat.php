<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('parametros_chat', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade')->index();

            $table->boolean('usoChat')->default(true);
            $table->integer('tempoUsoChat');

            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('parametros_quiz');
    }
};
