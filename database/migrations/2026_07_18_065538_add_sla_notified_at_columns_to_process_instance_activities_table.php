<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca "já notificado" pro job de SLA (02-motor-de-execucao.md §5 /
     * 04-integracao-e-notificacoes.md §4, item 3) — evita reenviar o mesmo aviso a cada
     * execução horária do job. Dois campos porque "prazo se aproximando" e "prazo vencido"
     * são eventos distintos, cada um disparado uma única vez.
     */
    public function up(): void
    {
        Schema::table('process_instance_activities', function (Blueprint $table) {
            $table->timestamp('sla_warning_notified_at')->nullable()->after('due_at');
            $table->timestamp('sla_overdue_notified_at')->nullable()->after('sla_warning_notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_instance_activities', function (Blueprint $table) {
            $table->dropColumn(['sla_warning_notified_at', 'sla_overdue_notified_at']);
        });
    }
};
