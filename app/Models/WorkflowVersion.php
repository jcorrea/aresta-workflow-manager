<?php

namespace App\Models;

use App\Enums\WorkflowVersionStatus;
use App\Models\Scopes\WorkflowVersionOrganizationScope;
use Database\Factories\WorkflowVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma versão congelável do desenho (01-modelo-de-dados.md §2.2) — só `draft` é editável;
 * `published` é imutável para sempre. Não tem `organization_id` próprio, o isolamento é
 * via o `Workflow` pai (ver `WorkflowVersionOrganizationScope`).
 */
class WorkflowVersion extends Model
{
    /** @use HasFactory<WorkflowVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'version_number',
        'status',
        'canvas_json',
        'published_at',
        'created_by',
        'draft_lock_workflow_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new WorkflowVersionOrganizationScope);
    }

    protected function casts(): array
    {
        return [
            'status' => WorkflowVersionStatus::class,
            'canvas_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(WorkflowVersionActivation::class);
    }

    /**
     * Transforma o grafo (`workflow_steps`/`workflow_activities`/`workflow_transitions`) no
     * formato de nós/arestas do Vue Flow (03-editor-visual.md §3) — `workflow_steps` viram
     * "parent nodes" (grupo visual), `workflow_activities` viram nós filhos presos ao grupo
     * (`parentNode`/`extent: 'parent'`), `workflow_transitions` viram `edges`. Só a lógica do
     * grafo + layout (`position_x`/`position_y`/`width`/`height`/`canvas_json`) — nunca
     * mistura com dado de execução (`ProcessInstance*`).
     *
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>, viewport: array<string, mixed>|null}
     */
    public function toGraphPayload(): array
    {
        $this->loadMissing(['steps.activities.assigneeRole', 'steps.activities.assigneeUser', 'transitions']);

        $stepNodes = $this->steps->map(fn (WorkflowStep $step) => [
            'id' => "step-{$step->id}",
            'type' => 'step',
            'position' => ['x' => $step->position_x, 'y' => $step->position_y],
            'style' => ['width' => "{$step->width}px", 'height' => "{$step->height}px"],
            'data' => [
                'id' => $step->id,
                'name' => $step->name,
                'slaDays' => $step->sla_days,
                'sortOrder' => $step->sort_order,
            ],
        ]);

        $activityNodes = $this->steps->flatMap(
            fn (WorkflowStep $step) => $step->activities->map(fn (WorkflowActivity $activity) => [
                'id' => "activity-{$activity->id}",
                'type' => $activity->type->value,
                'position' => ['x' => $activity->position_x, 'y' => $activity->position_y],
                'parentNode' => "step-{$step->id}",
                'extent' => 'parent',
                'data' => [
                    'id' => $activity->id,
                    'workflowStepId' => $step->id,
                    'name' => $activity->name,
                    'type' => $activity->type->value,
                    'assigneeType' => $activity->assignee_type?->value,
                    'assigneeRoleId' => $activity->assignee_role_id,
                    'assigneeRoleName' => $activity->assigneeRole?->name,
                    'assigneeUserId' => $activity->assignee_user_id,
                    'assigneeUserName' => $activity->assigneeUser?->name,
                    'config' => $activity->config,
                    'slaHours' => $activity->sla_hours,
                    'isStart' => $activity->is_start,
                    'isEnd' => $activity->is_end,
                ],
            ]),
        );

        $edges = $this->transitions->map(fn (WorkflowTransition $transition) => [
            'id' => "transition-{$transition->id}",
            'source' => "activity-{$transition->from_activity_id}",
            'target' => "activity-{$transition->to_activity_id}",
            'label' => $transition->label,
            'data' => [
                'id' => $transition->id,
                'conditionType' => $transition->condition_type->value,
                'conditionExpression' => $transition->condition_expression,
                'sortOrder' => $transition->sort_order,
            ],
        ]);

        return [
            'nodes' => $stepNodes->concat($activityNodes)->values()->all(),
            'edges' => $edges->values()->all(),
            'viewport' => $this->canvas_json,
        ];
    }
}
