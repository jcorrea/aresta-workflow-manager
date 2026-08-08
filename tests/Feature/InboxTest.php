<?php

namespace Tests\Feature;

use App\Enums\AssigneeType;
use App\Enums\OrganizationRole;
use App\Enums\ProcessInstanceActivityStatus;
use App\Enums\ProcessInstanceStatus;
use App\Enums\WorkflowActivityType;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Notifications\ActivityClaimedByAnotherNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 04-integracao-e-notificacoes.md §2.2/§3 — tela "Minhas tarefas", "assumir" atômico e
 * autorização de conclusão (responsável direto, fila do papel, ou override
 * workflow-admin/platform-staff).
 */
class InboxTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Workflow $workflow;

    private WorkflowVersion $version;

    private WorkflowStep $step;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $creator = User::factory()->create();
        $this->org->users()->attach($creator);

        $this->workflow = Workflow::factory()->for($this->org)->for($creator, 'createdBy')->create();
        $this->version = WorkflowVersion::factory()->for($this->workflow)->for($creator, 'createdBy')->published()->create();
        $this->step = WorkflowStep::factory()->for($this->version, 'workflowVersion')->create();
    }

    private function makeInstance(): ProcessInstance
    {
        $starter = User::factory()->create();
        $this->org->users()->attach($starter);

        return ProcessInstance::factory()->for($this->version, 'workflowVersion')->create([
            'organization_id' => $this->org->id,
            'started_by' => $starter->id,
        ]);
    }

    private function makeRoleWithMembers(array $members): Role
    {
        $role = Role::factory()->for($this->org)->create();
        $this->org->users()->syncWithoutDetaching(collect($members)->pluck('id'));
        $role->users()->attach(collect($members)->pluck('id'));

        return $role;
    }

    public function test_inbox_shows_directly_assigned_and_queued_activities_but_not_others(): void
    {
        $me = User::factory()->create();
        $this->org->users()->attach($me);

        $instance = $this->makeInstance();

        $directWorkflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $me->id,
        ]);
        $direct = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($directWorkflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => $me->id,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $role = $this->makeRoleWithMembers([$me]);
        $queuedWorkflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'assignee_type' => AssigneeType::Role,
            'assignee_role_id' => $role->id,
        ]);
        $queued = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($queuedWorkflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => null,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $otherWorkflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create();
        ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($otherWorkflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => null,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $this->actingAs($me)
            ->get(route('inbox.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Inbox/Index')
                ->has('activities', 2)
                ->where('activities.0.id', $direct->id)
                ->where('activities.0.isQueued', false)
                ->where('activities.1.id', $queued->id)
                ->where('activities.1.isQueued', true)
            );
    }

    public function test_eligible_user_can_claim_a_queued_activity_and_notifies_other_members(): void
    {
        Notification::fake();

        $claimer = User::factory()->create();
        $bystander = User::factory()->create();
        $role = $this->makeRoleWithMembers([$claimer, $bystander]);

        $instance = $this->makeInstance();
        $workflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'assignee_type' => AssigneeType::Role,
            'assignee_role_id' => $role->id,
        ]);
        $activity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($workflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => null,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $this->actingAs($claimer)
            ->post(route('process-instance-activities.claim', $activity))
            ->assertRedirect(route('inbox.index'));

        $this->assertSame($claimer->id, $activity->fresh()->assigned_user_id);
        Notification::assertSentTo($bystander, ActivityClaimedByAnotherNotification::class);
        Notification::assertNotSentTo($claimer, ActivityClaimedByAnotherNotification::class);
    }

    public function test_ineligible_user_cannot_claim(): void
    {
        $role = $this->makeRoleWithMembers([User::factory()->create()]);
        $outsider = User::factory()->create();
        $this->org->users()->attach($outsider);

        $instance = $this->makeInstance();
        $workflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'assignee_type' => AssigneeType::Role,
            'assignee_role_id' => $role->id,
        ]);
        $activity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($workflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => null,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $this->actingAs($outsider)
            ->post(route('process-instance-activities.claim', $activity))
            ->assertForbidden();

        $this->assertNull($activity->fresh()->assigned_user_id);
    }

    public function test_a_second_claim_attempt_on_an_already_claimed_activity_is_forbidden(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $role = $this->makeRoleWithMembers([$first, $second]);

        $instance = $this->makeInstance();
        $workflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'assignee_type' => AssigneeType::Role,
            'assignee_role_id' => $role->id,
        ]);
        $activity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($workflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => null,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $this->actingAs($first)->post(route('process-instance-activities.claim', $activity))->assertRedirect();
        $this->actingAs($second)->post(route('process-instance-activities.claim', $activity))->assertForbidden();

        $this->assertSame($first->id, $activity->fresh()->assigned_user_id);
    }

    public function test_assigned_user_can_complete_and_it_advances_the_graph(): void
    {
        $me = User::factory()->create();
        $this->org->users()->attach($me);

        $instance = $this->makeInstance();
        $workflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'is_end' => true,
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $me->id,
        ]);
        $activity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($workflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => $me->id,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $this->actingAs($me)
            ->post(route('process-instance-activities.complete', $activity), ['result' => ['ok' => true]])
            ->assertRedirect(route('inbox.index'));

        $this->assertSame(ProcessInstanceActivityStatus::Completed, $activity->fresh()->status);
        $this->assertSame(['ok' => true], $activity->fresh()->result);
        $this->assertSame(ProcessInstanceStatus::Completed, $instance->fresh()->status);
    }

    /**
     * A sidebar de tarefas do acompanhamento de instância (`ProcessInstances/Show.vue`) usa a
     * mesma rota de conclusão do Inbox — `back()` faz o usuário voltar pra instância em vez de
     * ser jogado pro Inbox quando a ação partiu de lá.
     */
    public function test_completing_from_the_instance_viewer_redirects_back_to_it_instead_of_the_inbox(): void
    {
        $me = User::factory()->create();
        $this->org->users()->attach($me);

        $instance = $this->makeInstance();
        $workflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'is_end' => true,
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $me->id,
        ]);
        $activity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($workflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => $me->id,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $instanceUrl = route('process-instances.show', $instance->code);

        $this->actingAs($me)
            ->from($instanceUrl)
            ->post(route('process-instance-activities.complete', $activity), ['result' => ['ok' => true]])
            ->assertRedirect($instanceUrl);

        $this->assertSame(ProcessInstanceActivityStatus::Completed, $activity->fresh()->status);
    }

    public function test_workflow_admin_can_complete_on_behalf_of_the_assignee(): void
    {
        $assignee = User::factory()->create();
        $this->org->users()->attach($assignee);

        $admin = User::factory()->create();
        $this->org->users()->attach($admin);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->org->id);
        $admin->assignRole(OrganizationRole::Admin->value);
        $registrar->setPermissionsTeamId(0);

        $instance = $this->makeInstance();
        $workflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'is_end' => true,
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $assignee->id,
        ]);
        $activity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($workflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => $assignee->id,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->post(route('process-instance-activities.complete', $activity))
            ->assertRedirect(route('inbox.index'));

        $this->assertSame(ProcessInstanceActivityStatus::Completed, $activity->fresh()->status);
    }

    public function test_unrelated_user_cannot_complete(): void
    {
        $assignee = User::factory()->create();
        $this->org->users()->attach($assignee);
        $outsider = User::factory()->create();
        $this->org->users()->attach($outsider);

        $instance = $this->makeInstance();
        $workflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $assignee->id,
        ]);
        $activity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($workflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => $assignee->id,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $this->actingAs($outsider)
            ->post(route('process-instance-activities.complete', $activity))
            ->assertForbidden();

        $this->assertSame(ProcessInstanceActivityStatus::Pending, $activity->fresh()->status);
    }

    public function test_completing_a_form_activity_persists_form_data(): void
    {
        $me = User::factory()->create();
        $this->org->users()->attach($me);

        $instance = $this->makeInstance();
        $workflowActivity = WorkflowActivity::factory()->for($this->step, 'workflowStep')->create([
            'type' => WorkflowActivityType::Form,
            'is_end' => true,
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $me->id,
            'config' => ['fields' => [['key' => 'valor', 'label' => 'Valor', 'type' => 'number', 'required' => true]]],
        ]);
        $activity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($workflowActivity, 'workflowActivity')->create([
            'assigned_user_id' => $me->id,
            'status' => ProcessInstanceActivityStatus::Pending,
        ]);

        $this->actingAs($me)->post(route('process-instance-activities.complete', $activity), [
            'form_data' => ['valor' => '1500'],
        ]);

        $this->assertSame(['valor' => '1500'], $activity->fresh()->form_data);
        $this->assertSame(ProcessInstanceActivityStatus::Completed, $activity->fresh()->status);
    }
}
