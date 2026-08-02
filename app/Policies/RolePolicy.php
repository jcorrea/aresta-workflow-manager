<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

/**
 * `Role` de domínio (01-modelo-de-dados.md §2.6) — quem pode gerenciar os papéis usados como
 * responsável de atividade. `viewAny`/`view` ficam abertos a qualquer membro da organização
 * (mesmo critério de leitura já usado no editor visual, que expõe a lista de papéis pra
 * qualquer RBAC tier); mutar (criar/editar/excluir) é só `workflow-admin` — mesmo tier que
 * cria/publica workflow (`WorkflowPolicy`), já que papel é config estrutural do processo, não
 * dado operacional. O global scope (`OrganizationScope`, via `BelongsToOrganization` no
 * model) já garante "não aparece na lista"; esta policy cobre "não é possível
 * acessar/mutar diretamente pela URL/ID mesmo sabendo que existe".
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformStaff() || $user->organizations()->exists();
    }

    public function view(User $user, Role $role): bool
    {
        return $user->isPlatformStaff() || $user->organizations->contains('id', $role->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->isPlatformStaff() || $this->isAdminOfAnyOrganization($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->isPlatformStaff()
            || $user->hasOrganizationRole($role->organization_id, OrganizationRole::Admin->value);
    }

    /**
     * Autorização pra "excluir" cobre só o botão/rota — o bloqueio de excluir um papel em uso
     * (01-modelo-de-dados.md §2.6.1) é responsabilidade do model (`Role::booted()`), não desta
     * policy, porque não depende de quem está pedindo, só do estado do dado.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->isPlatformStaff()
            || $user->hasOrganizationRole($role->organization_id, OrganizationRole::Admin->value);
    }

    private function isAdminOfAnyOrganization(User $user): bool
    {
        return $user->organizations->contains(
            fn (Organization $organization) => $user->hasOrganizationRole($organization->id, OrganizationRole::Admin->value),
        );
    }
}
