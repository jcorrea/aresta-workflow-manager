<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `roles` de domínio (01-modelo-de-dados.md §2.6) — papel de negócio usado para atribuir
     * responsabilidade por uma atividade (ex.: "Aprovador Financeiro"), distinto do RBAC
     * administrativo (`permission_roles`, spatie/laravel-permission). Nunca apagado
     * fisicamente quando em uso — `active = false` no lugar de DELETE (§2.6.1).
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('roles');
    }
};
