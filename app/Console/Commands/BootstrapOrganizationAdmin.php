<?php

namespace App\Console\Commands;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bootstrap manual de produção: cria (ou reaproveita) uma organização, atribui um usuário
 * já existente (login via SSO) como workflow-admin dela, e também como platform-staff.
 * `php artisan tinker` depende de proc_open, desabilitado em hosts compartilhados
 * (Hostinger) por segurança — este comando existe pra fazer a mesma coisa que o
 * DevDataSeeder faz localmente, sem precisar de tinker. Idempotente: pode rodar de novo
 * sem duplicar nada.
 */
class BootstrapOrganizationAdmin extends Command
{
    protected $signature = 'app:bootstrap-organization-admin {name : Nome da organização} {email : E-mail do usuário (já deve existir, via login SSO)}';

    protected $description = 'Cria uma organização e torna um usuário existente workflow-admin dela + platform-staff';

    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Nenhum usuário encontrado com o e-mail '{$email}' — faça login via SSO primeiro.");

            return self::FAILURE;
        }

        $organization = Organization::firstOrCreate(['name' => $name], ['active' => true]);
        $organization->users()->syncWithoutDetaching([$user->id]);

        $user->assignRole('platform-staff');

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($organization->id);
        $user->syncRoles([OrganizationRole::Admin->value]);
        $registrar->setPermissionsTeamId(0);

        $this->info("OK: organização #{$organization->id} ('{$organization->name}') — usuário #{$user->id} ({$user->email}) agora é workflow-admin dela e platform-staff.");

        return self::SUCCESS;
    }
}
