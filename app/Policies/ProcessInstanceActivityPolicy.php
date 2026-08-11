<?php

namespace App\Policies;

use App\Enums\AssigneeType;
use App\Enums\OrganizationRole;
use App\Models\ProcessInstanceActivity;
use App\Models\User;

/**
 * 04-integracao-e-notificacoes.md §2.2 — não é RBAC genérico, é resolvido pelo próprio
 * desenho do processo (`assignee_type`/`assignee_role_id`/`assignee_user_id`). Um usuário só
 * completa uma `process_instance_activity` se: já é o responsável, ou está na fila do papel
 * (`assigned_user_id` nulo, elegível pelo `role`), ou tem `workflow-admin`/`platform-staff`
 * daquela organização (override administrativo).
 */
class ProcessInstanceActivityPolicy
{
    public function complete(User $user, ProcessInstanceActivity $activity): bool
    {
        if ($activity->workflowActivity->assignee_type === AssigneeType::External) {
            return false;
        }

        if ($activity->assigned_user_id === $user->id) {
            return true;
        }

        if ($this->isOrganizationOverride($user, $activity)) {
            return true;
        }

        return $activity->assigned_user_id === null && $this->isEligibleForQueue($user, $activity);
    }

    /**
     * "Assumir" uma atividade em fila — só quem é elegível pelo papel, não o override
     * administrativo (admin completa direto via `complete()`, não precisa "assumir" antes).
     */
    public function claim(User $user, ProcessInstanceActivity $activity): bool
    {
        if ($activity->workflowActivity->assignee_type === AssigneeType::External) {
            return false;
        }

        return $activity->assigned_user_id === null && $this->isEligibleForQueue($user, $activity);
    }

    private function isOrganizationOverride(User $user, ProcessInstanceActivity $activity): bool
    {
        return $user->isPlatformStaff()
            || $user->hasOrganizationRole($activity->processInstance->organization_id, OrganizationRole::Admin->value);
    }

    private function isEligibleForQueue(User $user, ProcessInstanceActivity $activity): bool
    {
        $roleId = $activity->workflowActivity->assignee_role_id;

        if (! $roleId) {
            return false;
        }

        return $activity->workflowActivity->assigneeRole
            ?->users()
            ->where('users.id', $user->id)
            ->exists() ?? false;
    }
}
