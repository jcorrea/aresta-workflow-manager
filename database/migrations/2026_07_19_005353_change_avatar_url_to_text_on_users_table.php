<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A foto de perfil do Microsoft Graph vem como URL assinada com querystring longa
            // (SAS token) — passa fácil dos 255 caracteres de um `string`, estourando a coluna
            // no primeiro login SSO real (VARCHAR truncado pelo MySQL em modo estrito rejeita o
            // insert em vez de truncar silenciosamente).
            $table->text('avatar_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->change();
        });
    }
};
