<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;

/**
 * 04-integracao-e-notificacoes.md §2.1: `workflow-admin` cria/edita/publica/arquiva,
 * `workflow-editor` só edita rascunho, `workflow-viewer` só visualiza. O global scope
 * (`OrganizationScope`) já garante "não aparece na lista"; esta policy cobre "não é possível
 * acessar diretamente pela URL/ID mesmo sabendo que existe" (01-modelo-de-dados.md §5.3).
 */
class WorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $user->isPlatformStaff()
            || $user->hasOrganizationRole($workflow->organization_id, OrganizationRole::Admin->value)
            || $user->hasOrganizationRole($workflow->organization_id, OrganizationRole::Editor->value)
            || $user->hasOrganizationRole($workflow->organization_id, OrganizationRole::Viewer->value);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->isPlatformStaff()
            || $user->hasOrganizationRole($organization->id, OrganizationRole::Admin->value);
    }

    /**
     * Editar o rascunho (steps/activities/transitions) — `workflow-editor` ou superior.
     */
    public function update(User $user, Workflow $workflow): bool
    {
        return $user->isPlatformStaff()
            || $user->hasOrganizationRole($workflow->organization_id, OrganizationRole::Admin->value)
            || $user->hasOrganizationRole($workflow->organization_id, OrganizationRole::Editor->value);
    }

    /**
     * Publicar ou reverter (`rollback`) — só `workflow-admin`.
     */
    public function publish(User $user, Workflow $workflow): bool
    {
        return $user->isPlatformStaff()
            || $user->hasOrganizationRole($workflow->organization_id, OrganizationRole::Admin->value);
    }

    /**
     * Excluir workflow — só `workflow-admin`.
     */
    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->isPlatformStaff()
            || $user->hasOrganizationRole($workflow->organization_id, OrganizationRole::Admin->value);
    }
}
