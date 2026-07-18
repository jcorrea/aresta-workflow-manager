<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * `WorkflowVersion` não tem `organization_id` próprio, só `workflow_id` — o isolamento é "via
 * o Workflow pai" (01-modelo-de-dados.md §5.3, item 1). `whereHas('workflow')` já reaproveita
 * o `OrganizationScope` do `Workflow` (global scopes do model relacionado são respeitados
 * dentro de `whereHas`), sem duplicar a lógica de "é membro da organização ou platform-staff"
 * aqui.
 */
class WorkflowVersionOrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $builder->whereHas('workflow');
    }
}
