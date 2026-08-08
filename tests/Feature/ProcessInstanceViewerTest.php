<?php

namespace Tests\Feature;

use App\Enums\AssigneeType;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
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

    public function test_show_exposes_a_task_list_for_the_sidebar_ordered_by_execution(): void
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
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $a = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['name' => 'Primeira', 'is_start' => true]);
        $b = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['name' => 'Segunda']);

        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $org->id,
            'started_by' => $user->id,
        ]);

        // B começou antes de A na linha do tempo (ex.: paralelo) — a ordenação segue
        // `started_at`, não a ordem de criação da linha nem a ordem de desenho no grafo.
        $activityB = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($b, 'workflowActivity')->create([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(5),
        ]);
        $activityA = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($a, 'workflowActivity')->create([
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(6),
        ]);

        $this->actingAs($user)
            ->get(route('process-instances.show', $instance->code))
            ->assertInertia(fn ($page) => $page
                ->component('ProcessInstances/Show')
                ->where('tasks.0.id', $activityA->id)
                ->where('tasks.0.nodeId', "activity-{$a->id}")
                ->where('tasks.0.name', 'Primeira')
                ->where('tasks.0.status', 'completed')
                ->where('tasks.1.id', $activityB->id)
                ->where('tasks.1.nodeId', "activity-{$b->id}")
                ->where('tasks.1.status', 'in_progress')
            );
    }

    /**
     * `canComplete`/`canClaim` (usados pelos botões "Assumir"/"Concluir" da sidebar) refletem
     * a `ProcessInstanceActivityPolicy` já testada em `InboxTest` — aqui só confirma que o
     * `show()` da instância expõe os dois valores corretamente por tarefa.
     */
    public function test_show_exposes_can_complete_and_can_claim_per_task(): void
    {
        $org = Organization::factory()->create();
        $assignee = User::factory()->create();
        $outsider = User::factory()->create();
        $org->users()->attach([$assignee->id, $outsider->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($org->id);
        $assignee->assignRole(OrganizationRole::Viewer->value);
        $outsider->assignRole(OrganizationRole::Viewer->value);
        $registrar->setPermissionsTeamId(0);

        $workflow = Workflow::factory()->for($org)->for($assignee, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($assignee, 'createdBy')->published()->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $workflowActivity = WorkflowActivity::factory()->for($step, 'workflowStep')->create([
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $assignee->id,
        ]);

        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $org->id,
            'started_by' => $assignee->id,
        ]);
        $activity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($workflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => $assignee->id,
            'status' => 'pending',
        ]);

        $this->actingAs($assignee)
            ->get(route('process-instances.show', $instance->code))
            ->assertInertia(fn ($page) => $page
                ->where('tasks.0.id', $activity->id)
                ->where('tasks.0.canComplete', true)
                ->where('tasks.0.canClaim', false)
            );

        $this->actingAs($outsider)
            ->get(route('process-instances.show', $instance->code))
            ->assertInertia(fn ($page) => $page
                ->where('tasks.0.canComplete', false)
                ->where('tasks.0.canClaim', false)
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
