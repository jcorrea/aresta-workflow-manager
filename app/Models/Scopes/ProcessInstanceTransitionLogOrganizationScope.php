<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * `ProcessInstanceTransitionLog` não tem `organization_id` próprio — isolamento via o
 * `ProcessInstance` pai (01-modelo-de-dados.md §5.3, item 1).
 */
class ProcessInstanceTransitionLogOrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $builder->whereHas('processInstance');
    }
}
