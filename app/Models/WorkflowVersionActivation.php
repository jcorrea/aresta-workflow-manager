<?php

namespace App\Models;

use App\Enums\ActivationAction;
use App\Models\Scopes\WorkflowVersionActivationOrganizationScope;
use Database\Factories\WorkflowVersionActivationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log de publish/rollback (01-modelo-de-dados.md §2.2.2) — substitui a necessidade de um
 * status "archived" em `WorkflowVersion`.
 */
class WorkflowVersionActivation extends Model
{
    /** @use HasFactory<WorkflowVersionActivationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'workflow_id',
        'workflow_version_id',
        'action',
        'activated_by',
        'activated_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new WorkflowVersionActivationOrganizationScope);
    }

    protected function casts(): array
    {
        return [
            'action' => ActivationAction::class,
            'activated_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }
}
