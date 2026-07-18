<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `organization_id` denormalizado de `workflow_version.workflow.organization_id`
     * (01-modelo-de-dados.md §3.1) — é o campo mais consultado para isolamento (§5), por
     * isso vive como coluna própria em vez de só via join. `workflow_version_id` nunca muda
     * depois de criada (00-visao-geral.md §2.16 / CLAUDE.md), então `restrictOnDelete()`:
     * uma versão referenciada por instância não pode ser apagada.
     */
    public function up(): void
    {
        Schema::create('process_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status')->default('running');
            $table->foreignId('started_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('context');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_instances');
    }
};
