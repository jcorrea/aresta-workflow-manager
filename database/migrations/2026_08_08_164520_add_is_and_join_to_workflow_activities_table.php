<?php

use App\Enums\WorkflowVersionStatus;
use App\Models\WorkflowActivity;
use App\Models\WorkflowVersion;
use App\Services\WorkflowGraphValidator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `WorkflowEngine::isJoin()` passa a ler esta coluna em vez de contar transições de entrada
     * em runtime (02-motor-de-execucao.md §3.3.1) — necessário porque "todo nó com ≥2 entradas é
     * join" também classificava, incorretamente, um loop de retrabalho reconvergindo num nó já
     * alcançado por um caminho direto (sem fork correspondente), travando a instância esperando
     * uma segunda chegada que só pode acontecer depois da primeira já ter passado por ali.
     *
     * Backfill: versões já publicadas nunca mais passam por `WorkflowVersionController::publish()`
     * (imutáveis, 01-modelo-de-dados.md §2.2), então nenhum join real existente ficaria marcado
     * sem recalcular aqui — reaproveita a mesma análise de pós-dominância do validador.
     */
    public function up(): void
    {
        Schema::table('workflow_activities', function (Blueprint $table) {
            $table->boolean('is_and_join')->default(false)->after('is_end');
        });

        $validator = new WorkflowGraphValidator;

        WorkflowVersion::query()
            ->where('status', WorkflowVersionStatus::Published)
            ->each(function (WorkflowVersion $version) use ($validator) {
                $joinIds = $validator->validAndJoinIds($version);

                if ($joinIds->isNotEmpty()) {
                    WorkflowActivity::query()->whereIn('id', $joinIds)->update(['is_and_join' => true]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_activities', function (Blueprint $table) {
            $table->dropColumn('is_and_join');
        });
    }
};
