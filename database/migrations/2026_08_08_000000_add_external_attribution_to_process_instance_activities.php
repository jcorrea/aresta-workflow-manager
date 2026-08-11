<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Atribuição de humano completado via API por um sistema externo (04-integracao-e-
     * notificacoes.md §5) — separado de `assigned_user_id` porque a pessoa pode não ter
     * (ou ainda não ter) usuário no Aresta; `needs_review` sinaliza pro admin da organização
     * quando o e-mail reportado não bate com um usuário elegível pro papel da atividade.
     */
    public function up(): void
    {
        Schema::table('process_instance_activities', function (Blueprint $table) {
            $table->string('completed_by_external_name')->nullable()->after('assigned_user_id');
            $table->string('completed_by_external_email')->nullable()->after('completed_by_external_name');
            $table->boolean('external_attribution_needs_review')->default(false)->after('completed_by_external_email');
        });
    }

    public function down(): void
    {
        Schema::table('process_instance_activities', function (Blueprint $table) {
            $table->dropColumn(['completed_by_external_name', 'completed_by_external_email', 'external_attribution_needs_review']);
        });
    }
};
