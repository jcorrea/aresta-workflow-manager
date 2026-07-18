<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * `WorkflowActivity` só tem `workflow_step_id` — isolamento via a cadeia `WorkflowStep` →
 * `WorkflowVersion` → `Workflow` (01-modelo-de-dados.md §5.3, item 1), mesmo padrão de
 * `WorkflowVersionOrganizationScope`.
 */
class WorkflowActivityOrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $builder->whereHas('workflowStep');
    }
}
