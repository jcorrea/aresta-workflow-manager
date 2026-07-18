<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `draft_lock_workflow_id` nullable+unique é o truque de "no máximo 1 draft por
     * workflow" no MySQL (sem índice único parcial nativo) — preenchido com o próprio
     * `workflow_id` quando `status = draft`, `NULL` quando `published` (múltiplos NULL não
     * colidem num índice único). 01-modelo-de-dados.md §2.2.
     */
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number')->nullable();
            $table->string('status');
            $table->json('canvas_json')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedBigInteger('draft_lock_workflow_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
