<?php

namespace App\Models;

use App\Enums\ProcessInstanceStepStatus;
use App\Models\Scopes\ProcessInstanceStepOrganizationScope;
use Database\Factories\ProcessInstanceStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espelha `WorkflowStep` no momento do início da instância (01-modelo-de-dados.md §3.2) —
 * candidato a simplificação na fase 2 (item em aberto §7).
 */
class ProcessInstanceStep extends Model
{
    /** @use HasFactory<ProcessInstanceStepFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'process_instance_id',
        'workflow_step_id',
        'status',
        'started_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ProcessInstanceStepOrganizationScope);
    }

    protected function casts(): array
    {
        return [
            'status' => ProcessInstanceStepStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }
}
