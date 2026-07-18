<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trilha de auditoria do caminho percorrido no grafo (01-modelo-de-dados.md §3.5) — sem
     * equivalente formal no legado. `from_activity_id`/`to_activity_id` denormalizados para
     * consulta rápida mesmo se a transição for removida numa versão futura.
     */
    public function up(): void
    {
        Schema::create('process_instance_transition_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_transition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_activity_id')->constrained('workflow_activities')->restrictOnDelete();
            $table->foreignId('to_activity_id')->constrained('workflow_activities')->restrictOnDelete();
            $table->foreignId('transitioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transitioned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_instance_transition_logs');
    }
};
