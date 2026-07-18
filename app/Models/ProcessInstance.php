<?php

namespace App\Models;

use App\Enums\ProcessInstanceActivityStatus;
use App\Enums\ProcessInstanceStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ProcessInstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma instância em execução (01-modelo-de-dados.md §3.1), sempre amarrada a uma
 * `WorkflowVersion` fixa e imutável — nunca migra de versão, nem por publish nem por
 * rollback do workflow (00-visao-geral.md §2.16 / CLAUDE.md).
 */
class ProcessInstance extends Model
{
    /** @use HasFactory<ProcessInstanceFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'organization_id',
        'code',
        'name',
        'status',
        'started_by',
        'started_by_external_system_id',
        'started_at',
        'completed_at',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProcessInstanceStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * Iniciada via API por um sistema externo em vez de um usuário
     * (04-integracao-e-notificacoes.md §5) — exatamente um dos dois (`startedBy`/
     * `startedByExternalSystem`) é preenchido, nunca os dois.
     */
    public function startedByExternalSystem(): BelongsTo
    {
        return $this->belongsTo(ExternalSystem::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProcessInstanceStep::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProcessInstanceActivity::class);
    }

    public function transitionLogs(): HasMany
    {
        return $this->hasMany(ProcessInstanceTransitionLog::class);
    }

    /**
     * Reaproveita `WorkflowVersion::toGraphPayload()` (mesmo componente Vue Flow do editor,
     * 03-editor-visual.md §7) e anota cada nó/aresta com o estado de execução real desta
     * instância: nó concluído/ativo/não alcançado, aresta percorrida ou não (conforme
     * `process_instance_transition_logs`). Em ciclos, um nó pode ter várias
     * `ProcessInstanceActivity` — usa a mais recente pra representar "onde está agora".
     *
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>, viewport: array<string, mixed>|null}
     */
    public function toGraphPayload(): array
    {
        $payload = $this->workflowVersion->toGraphPayload();

        $latestActivityByWorkflowActivityId = $this->activities()
            ->orderBy('id')
            ->get()
            ->groupBy('workflow_activity_id')
            ->map(fn ($group) => $group->last());

        $traversedTransitionIds = $this->transitionLogs()
            ->pluck('workflow_transition_id')
            ->filter()
            ->unique();

        $payload['nodes'] = collect($payload['nodes'])->map(function (array $node) use ($latestActivityByWorkflowActivityId) {
            if ($node['type'] === 'step') {
                return $node;
            }

            $latest = $latestActivityByWorkflowActivityId->get($node['data']['id']);

            $node['data']['executionStatus'] = match (true) {
                $latest === null => 'not_reached',
                $latest->status === ProcessInstanceActivityStatus::Completed => 'completed',
                default => 'active',
            };

            return $node;
        })->all();

        $payload['edges'] = collect($payload['edges'])->map(function (array $edge) use ($traversedTransitionIds) {
            $edge['data']['traversed'] = $traversedTransitionIds->contains($edge['data']['id']);

            return $edge;
        })->all();

        return $payload;
    }
}
