<?php

namespace App\Observers;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Role as DomainRole;
use Spatie\Permission\Models\Role;

class OrganizationObserver
{
    /**
     * Papéis de negócio genéricos o bastante pra fazer sentido na maioria das empresas —
     * ponto de partida editável (renomear/desativar/criar outros em /admin), não uma lista
     * fechada. Ajuda tanto quem está montando um workflow manualmente (já tem o que escolher
     * no dropdown de responsável) quanto a IA (AiWorkflowDraftGenerator tenta casar por nome
     * antes de cadastrar um papel novo — activation com um conjunto padrão reduz a chance de
     * duplicar "Gestor" e "Gestor(a)" em workflows diferentes da mesma organização).
     */
    private const DEFAULT_DOMAIN_ROLES = ['Solicitante', 'Gestor', 'Aprovador', 'Financeiro', 'RH', 'Diretoria'];

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

        foreach (self::DEFAULT_DOMAIN_ROLES as $name) {
            DomainRole::create([
                'organization_id' => $organization->id,
                'name' => $name,
                'active' => true,
            ]);
        }
    }
}
