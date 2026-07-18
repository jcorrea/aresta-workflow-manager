<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * `platform-staff` (ITS Group) é o único papel RBAC global, sem organização
     * (00-visao-geral.md §6, item 2) — os papéis escopados por organização
     * (workflow-admin/editor/viewer) são provisionados por `OrganizationObserver` quando
     * cada `Organization` é criada, não aqui. `organization_id = 0` é o sentinel de "sem
     * organização" (AppServiceProvider::boot()) — a coluna é NOT NULL nas tabelas pivô do
     * spatie/laravel-permission, então `null` não é uma opção válida aqui.
     */
    public function run(): void
    {
        Role::query()->firstOrCreate([
            'name' => 'platform-staff',
            'guard_name' => 'web',
            'organization_id' => 0,
        ]);
    }
}
