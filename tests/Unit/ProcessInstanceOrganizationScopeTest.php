<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\ProcessInstanceStep;
use App\Models\ProcessInstanceTransitionLog;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as PermissionRole;
use Tests\TestCase;

/**
 * `ProcessInstance` tem `organization_id` próprio (denormalizado); `ProcessInstanceStep`,
 * `ProcessInstanceActivity` e `ProcessInstanceTransitionLog` não têm — isolam via o
 * `ProcessInstance` pai (01-modelo-de-dados.md §5.3). Mesmo tratamento de segurança que
 * `WorkflowGraphOrganizationScopeTest` dá ao lado config (CLAUDE.md, "Pontos de atenção do
 * domínio").
 */
class ProcessInstanceOrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{instance: ProcessInstance, step: ProcessInstanceStep, activity: ProcessInstanceActivity, log: ProcessInstanceTransitionLog}
     */
    private function buildInstanceGraph(Organization $org, User $creator): array
    {
        $workflow = Workflow::factory()->for($org)->for($creator, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($creator, 'createdBy')->published()->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $start = WorkflowActivity::factory()->for($step, 'workflowStep')->start()->create();
        $end = WorkflowActivity::factory()->for($step, 'workflowStep')->end()->create();
        $transition = WorkflowTransition::factory()->for($version, 'workflowVersion')->create([
            'from_activity_id' => $start->id,
            'to_activity_id' => $end->id,
        ]);

        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $org->id,
            'started_by' => $creator->id,
        ]);
        $piStep = ProcessInstanceStep::factory()->for($instance, 'processInstance')->for($step, 'workflowStep')->create();
        $piActivity = ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($start, 'workflowActivity')->create();
        $piLog = ProcessInstanceTransitionLog::factory()->for($instance, 'processInstance')->create([
            'workflow_transition_id' => $transition->id,
            'from_activity_id' => $start->id,
            'to_activity_id' => $end->id,
            'transitioned_by' => $creator->id,
        ]);

        return ['instance' => $instance, 'step' => $piStep, 'activity' => $piActivity, 'log' => $piLog];
    }

    public function test_the_execution_chain_is_scoped_to_the_users_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create();
        $orgA->users()->attach($userA);

        $graphA = $this->buildInstanceGraph($orgA, $userA);
        $graphB = $this->buildInstanceGraph($orgB, User::factory()->create());

        $this->actingAs($userA);

        $this->assertSame(1, ProcessInstance::count());
        $this->assertSame(1, ProcessInstanceStep::count());
        $this->assertSame(1, ProcessInstanceActivity::count());
        $this->assertSame(1, ProcessInstanceTransitionLog::count());

        $this->assertNull(ProcessInstance::find($graphB['instance']->id));
        $this->assertNull(ProcessInstanceStep::find($graphB['step']->id));
        $this->assertNull(ProcessInstanceActivity::find($graphB['activity']->id));
        $this->assertNull(ProcessInstanceTransitionLog::find($graphB['log']->id));

        $this->assertNotNull(ProcessInstance::find($graphA['instance']->id));
        $this->assertNotNull(ProcessInstanceStep::find($graphA['step']->id));
        $this->assertNotNull(ProcessInstanceActivity::find($graphA['activity']->id));
        $this->assertNotNull(ProcessInstanceTransitionLog::find($graphA['log']->id));
    }

    public function test_platform_staff_sees_the_execution_chain_of_every_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $this->buildInstanceGraph($orgA, User::factory()->create());
        $this->buildInstanceGraph($orgB, User::factory()->create());

        PermissionRole::create(['name' => 'platform-staff', 'organization_id' => 0]);
        $staff = User::factory()->create();
        $staff->assignRole('platform-staff');

        $this->actingAs($staff);

        $this->assertSame(2, ProcessInstance::count());
        $this->assertSame(2, ProcessInstanceStep::count());
        $this->assertSame(2, ProcessInstanceActivity::count());
        $this->assertSame(2, ProcessInstanceTransitionLog::count());
    }

    public function test_guest_sees_no_execution_chain_at_all(): void
    {
        $this->buildInstanceGraph(Organization::factory()->create(), User::factory()->create());

        $this->assertSame(0, ProcessInstance::count());
        $this->assertSame(0, ProcessInstanceStep::count());
        $this->assertSame(0, ProcessInstanceActivity::count());
        $this->assertSame(0, ProcessInstanceTransitionLog::count());
    }
}
