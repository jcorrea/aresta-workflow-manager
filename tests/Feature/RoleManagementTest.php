<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Gestão de papéis de negócio (01-modelo-de-dados.md §2.6): isolamento entre organizações,
 * quem pode mutar (só workflow-admin), papéis padrão semeados na criação da organização
 * (OrganizationObserver), e a regra de nunca excluir fisicamente um papel em uso (§2.6.1).
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function assignOrganizationRole(User $user, Organization $organization, string $role): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($organization->id);
        $user->assignRole($role);
        $registrar->setPermissionsTeamId(0);
        $user->unsetRelation('roles');
    }

    private function makeUserWithRole(Organization $organization, string $role): User
    {
        $user = User::factory()->create();
        $organization->users()->attach($user);
        $this->assignOrganizationRole($user, $organization, $role);

        return $user;
    }

    public function test_new_organization_is_seeded_with_default_domain_roles(): void
    {
        $organization = Organization::factory()->create();

        // withoutGlobalScopes(): este teste checa o que o OrganizationObserver gravou, não o
        // que um usuário autenticado enxergaria (OrganizationScope bloqueia tudo sem
        // Auth::user(), e não estamos logando ninguém aqui).
        $names = Role::withoutGlobalScopes()->where('organization_id', $organization->id)->pluck('name')->all();

        foreach (Role::DEFAULT_NAMES as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    public function test_workflow_admin_can_manage_roles_of_their_own_organization(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->makeUserWithRole($organization, OrganizationRole::Admin->value);
        $role = Role::factory()->for($organization)->create();

        $policy = new RolePolicy;

        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $role));
        $this->assertTrue($policy->delete($admin, $role));
    }

    public function test_editor_can_view_but_not_mutate_roles(): void
    {
        $organization = Organization::factory()->create();
        $editor = $this->makeUserWithRole($organization, OrganizationRole::Editor->value);
        $role = Role::factory()->for($organization)->create();

        $policy = new RolePolicy;

        $this->assertTrue($policy->view($editor, $role));
        $this->assertFalse($policy->update($editor, $role));
        $this->assertFalse($policy->delete($editor, $role));
    }

    public function test_admin_of_another_organization_cannot_manage_roles_outside_it(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $adminOfA = $this->makeUserWithRole($orgA, OrganizationRole::Admin->value);
        $roleOfB = Role::factory()->for($orgB)->create();

        $policy = new RolePolicy;

        $this->assertFalse($policy->view($adminOfA, $roleOfB));
        $this->assertFalse($policy->update($adminOfA, $roleOfB));
        $this->assertFalse($policy->delete($adminOfA, $roleOfB));
    }

    public function test_deleting_a_role_referenced_by_a_workflow_activity_is_blocked(): void
    {
        $organization = Organization::factory()->create();
        $role = Role::factory()->for($organization)->create();
        $workflow = Workflow::factory()->for($organization)->create();
        $version = WorkflowVersion::factory()->for($workflow)->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        WorkflowActivity::factory()->for($step, 'workflowStep')->create([
            'assignee_type' => 'role',
            'assignee_role_id' => $role->id,
        ]);

        $this->expectException(\RuntimeException::class);

        $role->delete();
    }

    public function test_deleting_an_unused_role_succeeds(): void
    {
        $organization = Organization::factory()->create();
        $role = Role::factory()->for($organization)->create();

        $role->delete();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
