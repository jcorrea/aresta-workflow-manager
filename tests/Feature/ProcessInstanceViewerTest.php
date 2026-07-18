<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 00-visao-geral.md §8 fase 6 — lista e acompanhamento somente-leitura de instâncias.
 */
class ProcessInstanceViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_instances_of_the_users_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create(['name' => 'Aprovação']);
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->published()->create();
        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $org->id,
            'started_by' => $user->id,
            'name' => 'Proposta #42',
        ]);

        $this->actingAs($user)
            ->get(route('process-instances.index'))
            ->assertInertia(fn ($page) => $page
                ->component('ProcessInstances/Index')
                ->where('instances.0.id', $instance->id)
                ->where('instances.0.workflowName', 'Aprovação')
            );
    }

    public function test_show_renders_the_readonly_graph(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($org->id);
        $user->assignRole(OrganizationRole::Viewer->value);
        $registrar->setPermissionsTeamId(0);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->published()->create();
        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $org->id,
            'started_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('process-instances.show', $instance->code))
            ->assertInertia(fn ($page) => $page
                ->component('ProcessInstances/Show')
                ->where('instance.code', $instance->code)
                ->has('graph.nodes')
                ->has('graph.edges')
            );
    }

    public function test_user_from_another_organization_cannot_view_the_instance(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $userA = User::factory()->create();
        $orgA->users()->attach($userA);
        $outsider = User::factory()->create();
        $orgB->users()->attach($outsider);

        $workflow = Workflow::factory()->for($orgA)->for($userA, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($userA, 'createdBy')->published()->create();
        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $orgA->id,
            'started_by' => $userA->id,
        ]);

        $this->actingAs($outsider)
            ->get(route('process-instances.show', $instance->code))
            ->assertNotFound();
    }
}
