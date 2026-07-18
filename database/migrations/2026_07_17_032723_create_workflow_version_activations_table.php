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
        Schema::create('workflow_version_activations', function (Blueprint $table) {
            $table->id();
            // Denormalizado (evita join por workflow_version pra listar histórico) —
            // 01-modelo-de-dados.md §2.2.2.
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('activated_by')->constrained('users');
            $table->timestamp('activated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_version_activations');
    }
};
