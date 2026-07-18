<?php

namespace App\Observers;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Spatie\Permission\Models\Role;

class OrganizationObserver
{
    /**
     * Toda organização nova precisa dos 3 papéis RBAC escopados a ela
     * (workflow-admin/editor/viewer) para que haja algo a atribuir a usuários — provisionado
     * aqui em vez de deixado como passo manual.
     */
    public function created(Organization $organization): void
    {
        foreach (OrganizationRole::cases() as $role) {
            Role::create([
                'name' => $role->value,
                'organization_id' => $organization->id,
            ]);
        }
    }
}
