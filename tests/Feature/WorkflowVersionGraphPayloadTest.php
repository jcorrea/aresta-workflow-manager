<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 03-editor-visual.md §3 — transformação do grafo para o formato de nós/arestas do Vue Flow.
 */
class WorkflowVersionGraphPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_transforms_steps_activities_and_transitions_into_vue_flow_nodes_and_edges(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);
        $this->actingAs($user);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->create([
            'canvas_json' => ['zoom' => 1, 'x' => 0, 'y' => 0],
        ]);
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create([
            'name' => 'Aprovação',
            'position_x' => 10,
            'position_y' => 20,
            'width' => 300,
            'height' => 200,
        ]);
        $a = WorkflowActivity::factory()->for($step, 'workflowStep')->create([
            'name' => 'Solicitar aprovação',
            'is_start' => true,
            'position_x' => 5,
            'position_y' => 5,
        ]);
        $b = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_end' => true]);
        $transition = WorkflowTransition::factory()->for($version, 'workflowVersion')->create([
            'from_activity_id' => $a->id,
            'to_activity_id' => $b->id,
            'label' => 'Aprovado',
        ]);

        $payload = $version->toGraphPayload();

        $this->assertSame(['zoom' => 1, 'x' => 0, 'y' => 0], $payload['viewport']);
        $this->assertCount(3, $payload['nodes']); // 1 step + 2 activities
        $this->assertCount(1, $payload['edges']);

        $stepNode = collect($payload['nodes'])->firstWhere('id', "step-{$step->id}");
        $this->assertSame('step', $stepNode['type']);
        $this->assertSame(['x' => 10, 'y' => 20], $stepNode['position']);
        $this->assertSame('Aprovação', $stepNode['data']['name']);

        $activityNode = collect($payload['nodes'])->firstWhere('id', "activity-{$a->id}");
        $this->assertSame("step-{$step->id}", $activityNode['parentNode']);
        $this->assertSame('parent', $activityNode['extent']);
        $this->assertTrue($activityNode['data']['isStart']);
        $this->assertSame('Solicitar aprovação', $activityNode['data']['name']);

        $edge = $payload['edges'][0];
        $this->assertSame("transition-{$transition->id}", $edge['id']);
        $this->assertSame("activity-{$a->id}", $edge['source']);
        $this->assertSame("activity-{$b->id}", $edge['target']);
        $this->assertSame('Aprovado', $edge['label']);
    }
}
