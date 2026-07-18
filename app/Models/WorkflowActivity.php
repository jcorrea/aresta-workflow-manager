<?php

namespace App\Models;

use App\Enums\AssigneeType;
use App\Enums\WorkflowActivityType;
use App\Models\Scopes\WorkflowActivityOrganizationScope;
use Database\Factories\WorkflowActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O nó de fato do grafo (01-modelo-de-dados.md §2.4). `is_end` substitui o magic number
 * `idprocessdestiny === 4` do legado por uma flag explícita nomeada.
 */
class WorkflowActivity extends Model
{
    /** @use HasFactory<WorkflowActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_step_id',
        'name',
        'type',
        'assignee_type',
        'assignee_role_id',
        'assignee_user_id',
        'config',
        'sla_hours',
        'position_x',
        'position_y',
        'is_start',
        'is_end',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new WorkflowActivityOrganizationScope);
    }

    protected function casts(): array
    {
        return [
            'type' => WorkflowActivityType::class,
            'assignee_type' => AssigneeType::class,
            'config' => 'array',
            'is_start' => 'boolean',
            'is_end' => 'boolean',
        ];
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function assigneeRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assignee_role_id');
    }

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_activity_id');
    }

    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'to_activity_id');
    }
}
