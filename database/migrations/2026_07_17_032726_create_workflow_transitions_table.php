<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `workflow_version_id` denormalizado para consultas/validação de grafo sem join extra
     * (01-modelo-de-dados.md §2.5). Constraint "from/to pertencem à mesma versão" é de
     * aplicação, não de banco.
     */
    public function up(): void
    {
        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_activity_id')->constrained('workflow_activities')->cascadeOnDelete();
            $table->foreignId('to_activity_id')->constrained('workflow_activities')->cascadeOnDelete();
            $table->string('condition_type');
            $table->json('condition_expression')->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};
