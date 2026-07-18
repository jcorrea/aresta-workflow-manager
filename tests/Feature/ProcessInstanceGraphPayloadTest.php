<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\ProcessInstanceTransitionLog;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 03-editor-visual.md §7 — o mesmo grafo do editor, anotado com o progresso real da
 * instância: nó concluído/ativo/não alcançado, aresta percorrida ou não.
 */
class ProcessInstanceGraphPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_annotates_nodes_and_edges_with_execution_state(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);
        $this->actingAs($user);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $a = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_start' => true]);
        $b = WorkflowActivity::factory()->for($step, 'workflowStep')->create();
        $c = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_end' => true]);
        $transitionAB = WorkflowTransition::factory()->for($version, 'workflowVersion')->create(['from_activity_id' => $a->id, 'to_activity_id' => $b->id]);
        WorkflowTransition::factory()->for($version, 'workflowVersion')->create(['from_activity_id' => $b->id, 'to_activity_id' => $c->id]);

        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $org->id,
            'started_by' => $user->id,
        ]);

        // A: completa. B: ativa (pending). C: nunca alcançada.
        ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($a, 'workflowActivity')->create(['status' => 'completed']);
        ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($b, 'workflowActivity')->create(['status' => 'pending']);
        ProcessInstanceTransitionLog::factory()->for($instance, 'processInstance')->create([
            'workflow_transition_id' => $transitionAB->id,
            'from_activity_id' => $a->id,
            'to_activity_id' => $b->id,
        ]);

        $payload = $instance->toGraphPayload();

        $nodeA = collect($payload['nodes'])->firstWhere('id', "activity-{$a->id}");
        $nodeB = collect($payload['nodes'])->firstWhere('id', "activity-{$b->id}");
        $nodeC = collect($payload['nodes'])->firstWhere('id', "activity-{$c->id}");

        $this->assertSame('completed', $nodeA['data']['executionStatus']);
        $this->assertSame('active', $nodeB['data']['executionStatus']);
        $this->assertSame('not_reached', $nodeC['data']['executionStatus']);

        $edgeAB = collect($payload['edges'])->firstWhere('data.id', $transitionAB->id);
        $edgeBC = collect($payload['edges'])->first(fn ($e) => $e['data']['id'] !== $transitionAB->id);

        $this->assertTrue($edgeAB['data']['traversed']);
        $this->assertFalse($edgeBC['data']['traversed']);
    }

    public function test_a_revisited_node_in_a_cycle_reflects_only_the_latest_pass(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);
        $this->actingAs($user);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $a = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_start' => true]);

        $instance = ProcessInstance::factory()->for($version, 'workflowVersion')->create([
            'organization_id' => $org->id,
            'started_by' => $user->id,
        ]);

        // Primeira passagem por A: completa. Segunda passagem (ciclo): ainda pendente.
        ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($a, 'workflowActivity')->create(['status' => 'completed']);
        ProcessInstanceActivity::factory()->for($instance, 'processInstance')->for($a, 'workflowActivity')->create(['status' => 'pending']);

        $payload = $instance->toGraphPayload();
        $nodeA = collect($payload['nodes'])->firstWhere('id', "activity-{$a->id}");

        $this->assertSame('active', $nodeA['data']['executionStatus']);
    }
}
