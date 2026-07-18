<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * `WorkflowVersionActivation` denormaliza `workflow_id` (01-modelo-de-dados.md §2.2.2) mas
 * não tem `organization_id` próprio — mesmo padrão de `WorkflowVersionOrganizationScope`,
 * reaproveitando o `OrganizationScope` do `Workflow` via `whereHas`.
 */
class WorkflowVersionActivationOrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $builder->whereHas('workflow');
    }
}
