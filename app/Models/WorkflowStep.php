<?php

namespace App\Models;

use App\Models\Scopes\WorkflowStepOrganizationScope;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agrupamento visual de atividades dentro de uma versão (01-modelo-de-dados.md §2.3) — no
 * MVP tratar como "raia"/grupo, não necessariamente sequencial.
 */
class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'name',
        'sla_days',
        'position_x',
        'position_y',
        'width',
        'height',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new WorkflowStepOrganizationScope);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WorkflowActivity::class);
    }
}
