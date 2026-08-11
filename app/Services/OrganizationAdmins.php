<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role as PermissionRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resolve os usuários com o papel administrativo (`spatie/laravel-permission`, RBAC
 * administrativo — distinto do `Role` de negócio usado em `workflow_activities`) de uma
 * organização, via o recurso de *teams* mapeado pra `organization_id`
 * (04-integracao-e-notificacoes.md §2). Extraído de `WorkflowEngine::notifyWorkflowAdmins`
 * pra ser reaproveitado por qualquer chamador que precise notificar admins.
 */
class OrganizationAdmins
{
    /**
     * @return Collection<int, User>
     */
    public static function for(int $organizationId): Collection
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($organizationId);

        $admins = PermissionRole::query()
            ->where('name', OrganizationRole::Admin->value)
            ->where('organization_id', $organizationId)
            ->first()
            ?->users ?? collect();

        $registrar->setPermissionsTeamId($previousTeamId);

        return $admins;
    }
}
