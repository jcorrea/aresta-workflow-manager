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
}
