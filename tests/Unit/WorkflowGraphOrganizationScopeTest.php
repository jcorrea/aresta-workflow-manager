<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowVersionActivation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as PermissionRole;
use Tests\TestCase;

/**
 * `Workflow` e `Role` de domínio já são cobertos por `BelongsToOrganization` (ver
 * `OrganizationScopeTest`). Este teste cobre o resto da cadeia — `WorkflowVersion`,
 * `WorkflowStep`, `WorkflowActivity`, `WorkflowTransition`, `WorkflowVersionActivation` —
 * nenhum dos quais tem `organization_id` próprio, só isolamento via os scopes que
 * atravessam a cadeia até `Workflow` (01-modelo-de-dados.md §5.3). Vazamento aqui é bug de
 * segurança, não detalhe a refinar depois (CLAUDE.md, "Pontos de atenção do domínio").
 */
class WorkflowGraphOrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{workflow: Workflow, version: WorkflowVersion, step: WorkflowStep, start: WorkflowActivity, end: WorkflowActivity, transition: WorkflowTransition, activation: WorkflowVersionActivation}
     */
    private function buildGraph(Organization $org, User $creator): array
    {
        $workflow = Workflow::factory()->for($org)->for($creator, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($creator, 'createdBy')->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $start = WorkflowActivity::factory()->for($step, 'workflowStep')->start()->create();
        $end = WorkflowActivity::factory()->for($step, 'workflowStep')->end()->create();
        $transition = WorkflowTransition::factory()->for($version, 'workflowVersion')->create([
            'from_activity_id' => $start->id,
            'to_activity_id' => $end->id,
        ]);
        $activation = WorkflowVersionActivation::factory()->create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
        ]);

        return compact('workflow', 'version', 'step', 'start', 'end', 'transition', 'activation');
    }

    public function test_the_full_chain_is_scoped_to_the_users_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create();
        $orgA->users()->attach($userA);

        $graphA = $this->buildGraph($orgA, $userA);
        $graphB = $this->buildGraph($orgB, User::factory()->create());

        $this->actingAs($userA);

        $this->assertSame(1, WorkflowVersion::count());
        $this->assertSame(1, WorkflowStep::count());
        $this->assertSame(2, WorkflowActivity::count());
        $this->assertSame(1, WorkflowTransition::count());
        $this->assertSame(1, WorkflowVersionActivation::count());

        $this->assertNull(WorkflowVersion::find($graphB['version']->id));
        $this->assertNull(WorkflowStep::find($graphB['step']->id));
        $this->assertNull(WorkflowActivity::find($graphB['start']->id));
        $this->assertNull(WorkflowTransition::find($graphB['transition']->id));
        $this->assertNull(WorkflowVersionActivation::find($graphB['activation']->id));

        $this->assertNotNull(WorkflowVersion::find($graphA['version']->id));
        $this->assertNotNull(WorkflowStep::find($graphA['step']->id));
        $this->assertNotNull(WorkflowActivity::find($graphA['start']->id));
        $this->assertNotNull(WorkflowTransition::find($graphA['transition']->id));
        $this->assertNotNull(WorkflowVersionActivation::find($graphA['activation']->id));
    }

    public function test_platform_staff_sees_the_chain_of_every_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $this->buildGraph($orgA, User::factory()->create());
        $this->buildGraph($orgB, User::factory()->create());

        PermissionRole::create(['name' => 'platform-staff', 'organization_id' => 0]);
        $staff = User::factory()->create();
        $staff->assignRole('platform-staff');

        $this->actingAs($staff);

        $this->assertSame(2, WorkflowVersion::count());
        $this->assertSame(2, WorkflowStep::count());
        $this->assertSame(4, WorkflowActivity::count());
        $this->assertSame(2, WorkflowTransition::count());
        $this->assertSame(2, WorkflowVersionActivation::count());
    }

    public function test_guest_sees_no_chain_at_all(): void
    {
        $this->buildGraph(Organization::factory()->create(), User::factory()->create());

        $this->assertSame(0, WorkflowVersion::count());
        $this->assertSame(0, WorkflowStep::count());
        $this->assertSame(0, WorkflowActivity::count());
        $this->assertSame(0, WorkflowTransition::count());
        $this->assertSame(0, WorkflowVersionActivation::count());
    }
}
