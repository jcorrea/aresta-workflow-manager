<?php

namespace App\Models;

use App\Enums\ConditionType;
use App\Models\Scopes\WorkflowTransitionOrganizationScope;
use Database\Factories\WorkflowTransitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * As "arestas" do grafo (01-modelo-de-dados.md §2.5) — dão nome ao produto. `from_activity_id`
 * e `to_activity_id` devem pertencer à mesma `workflow_version_id`; validado no service, não
 * via FK cross-tabela (constraint de aplicação, não de banco).
 */
class WorkflowTransition extends Model
{
    /** @use HasFactory<WorkflowTransitionFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'from_activity_id',
        'to_activity_id',
        'condition_type',
        'condition_expression',
        'label',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new WorkflowTransitionOrganizationScope);
    }

    protected function casts(): array
    {
        return [
            'condition_type' => ConditionType::class,
            'condition_expression' => 'array',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function fromActivity(): BelongsTo
    {
        return $this->belongsTo(WorkflowActivity::class, 'from_activity_id');
    }

    public function toActivity(): BelongsTo
    {
        return $this->belongsTo(WorkflowActivity::class, 'to_activity_id');
    }
}
