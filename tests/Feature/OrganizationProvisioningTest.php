<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_organization_provisions_its_scoped_rbac_roles(): void
    {
        $organization = Organization::factory()->create();

        foreach (OrganizationRole::cases() as $role) {
            $this->assertTrue(
                Role::query()
                    ->where('name', $role->value)
                    ->where('organization_id', $organization->id)
                    ->exists(),
                "Papel {$role->value} não foi provisionado para a organização."
            );
        }
    }

    public function test_two_organizations_get_independent_role_instances(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();

        $this->assertSame(
            count(OrganizationRole::cases()),
            Role::query()->where('organization_id', $a->id)->count()
        );
        $this->assertNotSame(
            Role::query()->where('organization_id', $a->id)->pluck('id'),
            Role::query()->where('organization_id', $b->id)->pluck('id'),
        );
    }
}
