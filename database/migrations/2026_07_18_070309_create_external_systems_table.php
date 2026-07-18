<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identifica **qual sistema GIITS** está chamando a API (giits-propostas, giits-api) —
     * não é uma organização (04-integracao-e-notificacoes.md §5). Token Sanctum por sistema,
     * não por usuário final; nenhum vínculo com `organizations` aqui, porque o mesmo sistema
     * atende várias organizações e a organização alvo vem explícita em cada request (§5,
     * item em aberto sobre escopo fino de token por organização — não implementado no MVP).
     */
    public function up(): void
    {
        Schema::create('external_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_systems');
    }
};
