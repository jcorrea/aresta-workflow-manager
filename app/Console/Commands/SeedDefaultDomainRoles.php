<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Role;
use Illuminate\Console\Command;

/**
 * OrganizationObserver semeia Role::DEFAULT_NAMES só na CRIAÇÃO de uma organização — não
 * retroativo. Este comando aplica o mesmo conjunto padrão a uma organização que já existia
 * antes dessa mudança (idempotente via firstOrCreate: só cria o que ainda falta).
 */
class SeedDefaultDomainRoles extends Command
{
    protected $signature = 'app:seed-default-domain-roles {organization : ID ou nome exato da organização}';

    protected $description = 'Cria os papéis de negócio padrão (Role::DEFAULT_NAMES) que ainda não existirem numa organização já existente';

    public function handle(): int
    {
        $identifier = $this->argument('organization');

        $organization = is_numeric($identifier)
            ? Organization::find((int) $identifier)
            : Organization::where('name', $identifier)->first();

        if (! $organization) {
            $this->error("Organização '{$identifier}' não encontrada.");

            return self::FAILURE;
        }

        $created = [];

        foreach (Role::DEFAULT_NAMES as $name) {
            $role = Role::firstOrCreate(
                ['organization_id' => $organization->id, 'name' => $name],
                ['active' => true],
            );

            if ($role->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        $this->info($created === []
            ? "Nenhum papel novo — todos os padrão já existiam em '{$organization->name}'."
            : "Criados em '{$organization->name}': ".implode(', ', $created).'.');

        return self::SUCCESS;
    }
}
