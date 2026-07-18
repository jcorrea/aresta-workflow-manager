<?php

namespace App\Models;

use App\Models\Scopes\ProcessInstanceTransitionLogOrganizationScope;
use Database\Factories\ProcessInstanceTransitionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trilha de auditoria do caminho percorrido no grafo (01-modelo-de-dados.md §3.5) — sem
 * equivalente formal no legado.
 */
class ProcessInstanceTransitionLog extends Model
{
    /** @use HasFactory<ProcessInstanceTransitionLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'process_instance_id',
        'workflow_transition_id',
        'from_activity_id',
        'to_activity_id',
        'transitioned_by',
        'transitioned_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ProcessInstanceTransitionLogOrganizationScope);
    }

    protected function casts(): array
    {
        return [
            'transitioned_at' => 'datetime',
        ];
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function workflowTransition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class);
    }

    public function fromActivity(): BelongsTo
    {
        return $this->belongsTo(WorkflowActivity::class, 'from_activity_id');
    }

    public function toActivity(): BelongsTo
    {
        return $this->belongsTo(WorkflowActivity::class, 'to_activity_id');
    }

    public function transitionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transitioned_by');
    }
}
