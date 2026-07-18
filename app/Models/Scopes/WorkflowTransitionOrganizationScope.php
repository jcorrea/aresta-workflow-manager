<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * `WorkflowTransition` tem `workflow_version_id` denormalizado (01-modelo-de-dados.md §2.5)
 * mas não `organization_id` próprio — isolamento via a cadeia até `Workflow`, mesmo padrão
 * de `WorkflowVersionOrganizationScope`.
 */
class WorkflowTransitionOrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $builder->whereHas('workflowVersion');
    }
}
