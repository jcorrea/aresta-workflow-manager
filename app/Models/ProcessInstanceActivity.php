<?php

namespace App\Models;

use App\Enums\ProcessInstanceActivityStatus;
use App\Models\Scopes\ProcessInstanceActivityOrganizationScope;
use Database\Factories\ProcessInstanceActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A unidade de trabalho real (01-modelo-de-dados.md §3.3) — equivalente a
 * `Processactivities` + `Tasks` fundidos no legado.
 */
class ProcessInstanceActivity extends Model
{
    /** @use HasFactory<ProcessInstanceActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'process_instance_id',
        'workflow_activity_id',
        'assigned_user_id',
        'status',
        'form_data',
        'result',
        'due_at',
        'sla_warning_notified_at',
        'sla_overdue_notified_at',
        'started_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ProcessInstanceActivityOrganizationScope);
    }

    protected function casts(): array
    {
        return [
            'status' => ProcessInstanceActivityStatus::class,
            'form_data' => 'array',
            'result' => 'array',
            'due_at' => 'datetime',
            'sla_warning_notified_at' => 'datetime',
            'sla_overdue_notified_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function workflowActivity(): BelongsTo
    {
        return $this->belongsTo(WorkflowActivity::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
