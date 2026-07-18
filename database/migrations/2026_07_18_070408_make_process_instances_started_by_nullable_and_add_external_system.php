<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uma instância pode ser iniciada por um sistema GIITS externo via API, não só por um
     * usuário humano (04-integracao-e-notificacoes.md §5, `POST /api/workflows/{slug}/
     * instances`) — `started_by` vira opcional e ganha um par `started_by_external_system_id`
     * (exatamente um dos dois preenchido, validado na aplicação, não no banco). Drop+recreate
     * em vez de `->change()` para não depender de `doctrine/dbal`.
     */
    public function up(): void
    {
        Schema::table('process_instances', function (Blueprint $table) {
            $table->dropForeign(['started_by']);
            $table->dropColumn('started_by');
        });

        Schema::table('process_instances', function (Blueprint $table) {
            $table->foreignId('started_by')->nullable()->after('organization_id')->constrained('users');
            $table->foreignId('started_by_external_system_id')->nullable()->after('started_by')->constrained('external_systems');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_instances', function (Blueprint $table) {
            $table->dropForeign(['started_by_external_system_id']);
            $table->dropColumn('started_by_external_system_id');
            $table->dropForeign(['started_by']);
            $table->dropColumn('started_by');
        });

        Schema::table('process_instances', function (Blueprint $table) {
            $table->foreignId('started_by')->after('organization_id')->constrained('users');
        });
    }
};
