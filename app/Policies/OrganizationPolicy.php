<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

/**
 * `Organization` é a empresa-cliente em si (01-modelo-de-dados.md §5.1) — cadastrá-la não é
 * uma ação que faça sentido para um usuário de dentro de uma organização (isso seria "um
 * cliente criando outros clientes"), só para `platform-staff` (ITS Group), o mesmo override
 * administrativo do isolamento multi-tenant (01-modelo-de-dados.md §5.3).
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('platform-staff');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->hasRole('platform-staff');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('platform-staff');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasRole('platform-staff');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->hasRole('platform-staff');
    }
}
