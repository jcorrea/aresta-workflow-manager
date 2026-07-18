<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `is_end` substitui o magic number `idprocessdestiny === 4` do legado por uma flag
     * explícita nomeada (00-visao-geral.md §3.3 / 01-modelo-de-dados.md §2.4).
     */
    public function up(): void
    {
        Schema::create('workflow_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('assignee_type')->nullable();
            // `restrictOnDelete()`: rede de segurança de banco para a regra de "nunca apagar
            // um role em uso" (01-modelo-de-dados.md §2.6.1) — a aplicação já bloqueia isso
            // antes (RolePolicy::delete), mas a constraint garante mesmo se algo pular essa
            // checagem.
            $table->foreignId('assignee_role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('config');
            $table->unsignedInteger('sla_hours')->nullable();
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->boolean('is_start')->default(false);
            $table->boolean('is_end')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_activities');
    }
};
