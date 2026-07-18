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
            $table->string('azure_id')->nullable()->unique()->after('email');
            $table->string('avatar_url')->nullable()->after('azure_id');
            // Login é só via SSO (04-integracao-e-notificacoes.md §1) — não há tela de
            // cadastro com senha própria.
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['azure_id', 'avatar_url']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
