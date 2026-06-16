<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // foto passa de string(255) para longText para comportar imagens em base64 (data URL).
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->longText('foto')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('foto')->nullable()->change();
        });
    }
};
