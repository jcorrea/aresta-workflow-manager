<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * TEMPORÁRIO — dados pra testar a UI localmente via /dev-login (routes/web.php), sem
 * depender de um App Registration Azure real. Só roda em app()->isLocal() (DatabaseSeeder).
 */
class DevDataSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::firstOrCreate(
            ['name' => 'Empresa Demo'],
            ['active' => true],
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.test'],
            ['name' => 'Ana Admin', 'password' => Hash::make('password')],
        );
        $editor = User::firstOrCreate(
            ['email' => 'editor@demo.test'],
            ['name' => 'Eduardo Editor', 'password' => Hash::make('password')],
        );
        $viewer = User::firstOrCreate(
            ['email' => 'viewer@demo.test'],
            ['name' => 'Vera Viewer', 'password' => Hash::make('password')],
        );

        $organization->users()->syncWithoutDetaching([$admin->id, $editor->id, $viewer->id]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($organization->id);
        $admin->syncRoles([OrganizationRole::Admin->value]);
        $editor->syncRoles([OrganizationRole::Editor->value]);
        $viewer->syncRoles([OrganizationRole::Viewer->value]);
        $registrar->setPermissionsTeamId(0);

        $role = Role::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Aprovador Financeiro'],
            ['active' => true],
        );
        $role->users()->syncWithoutDetaching([$admin->id]);
    }
}
