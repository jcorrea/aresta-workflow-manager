<?php

namespace Tests\Feature;

use App\Enums\AssigneeType;
use App\Enums\ConditionType;
use App\Enums\GraphIssueSeverity;
use App\Enums\WorkflowActivityType;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\WorkflowGraphValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 03-editor-visual.md §6 e 02-motor-de-execucao.md §3.3.1 — grafos montados via factories,
 * exercitando tanto as regras simples quanto o pareamento fork/join por pós-dominância.
 */
class WorkflowGraphValidatorTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowGraphValidator $validator;

    private Organization $org;

    private WorkflowVersion $version;

    private WorkflowStep $step;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(WorkflowGraphValidator::class);

        $this->org = Organization::factory()->create();
        $org = $this->org;
        $user = User::factory()->create();
        $org->users()->attach($user);
        $this->actingAs($user);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $this->version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->create();
        $this->step = WorkflowStep::factory()->for($this->version, 'workflowVersion')->create();
    }

    private function activity(array $attributes = []): WorkflowActivity
    {
        return WorkflowActivity::factory()->for($this->step, 'workflowStep')->create(array_merge([
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => User::factory(),
        ], $attributes));
    }

    private function transition(WorkflowActivity $from, WorkflowActivity $to, array $attributes = []): WorkflowTransition
    {
        return WorkflowTransition::factory()->for($this->version, 'workflowVersion')->create(array_merge([
            'from_activity_id' => $from->id,
            'to_activity_id' => $to->id,
        ], $attributes));
    }

    public function test_a_simple_linear_graph_is_publishable(): void
    {
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $c = $this->activity(['is_end' => true]);
        $this->transition($a, $b);
        $this->transition($b, $c);

        $this->assertTrue($this->validator->isPublishable($this->version));
        $this->assertCount(0, $this->validator->validate($this->version));
    }

    public function test_missing_start_node_is_an_error(): void
    {
        $a = $this->activity(['is_end' => true]);

        $issues = $this->validator->validate($this->version);

        $this->assertTrue($issues->contains(fn ($i) => $i->code === 'missing_start'));
        $this->assertFalse($this->validator->isPublishable($this->version));
    }

    public function test_missing_end_node_is_an_error(): void
    {
        $this->activity(['is_start' => true]);

        $issues = $this->validator->validate($this->version);

        $this->assertTrue($issues->contains(fn ($i) => $i->code === 'missing_end'));
        $this->assertFalse($this->validator->isPublishable($this->version));
    }

    public function test_start_that_cannot_reach_any_end_is_an_error(): void
    {
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $end = $this->activity(['is_end' => true]);
        // `end` existe, mas não é alcançável a partir de A (sem transição nenhuma saindo de A).
        $this->transition($b, $end);

        $issues = $this->validator->validate($this->version);

        $this->assertTrue($issues->contains(fn ($i) => $i->code === 'start_cannot_reach_end'));
    }

    public function test_condition_node_without_outgoing_transition_is_an_error(): void
    {
        $a = $this->activity(['is_start' => true]);
        $condition = $this->activity(['type' => WorkflowActivityType::Condition, 'assignee_type' => null, 'assignee_user_id' => null]);
        $this->transition($a, $condition);
        // `condition` não é `is_end` e não tem nenhuma transição de saída.

        $issues = $this->validator->validate($this->version);

        $this->assertTrue($issues->contains(fn ($i) => $i->code === 'condition_without_outgoing'));
    }

    /**
     * Achado testando a app de verdade num browser: um nó condition com is_end=true termina
     * o processo assim que ativado (WorkflowEngine::completeActivity() retorna antes de
     * chamar advance()) — não é um "condition preso no meio do grafo" e não deveria ser
     * sinalizado como tal.
     */
    public function test_condition_node_that_is_also_is_end_does_not_need_an_outgoing_transition(): void
    {
        $a = $this->activity(['is_start' => true]);
        $end = $this->activity(['type' => WorkflowActivityType::Condition, 'is_end' => true, 'assignee_type' => null, 'assignee_user_id' => null]);
        $this->transition($a, $end);

        $issues = $this->validator->validate($this->version);

        $this->assertFalse($issues->contains(fn ($i) => $i->code === 'condition_without_outgoing'));
        $this->assertTrue($this->validator->isPublishable($this->version));
    }

    public function test_task_without_assignee_type_is_an_error(): void
    {
        $a = $this->activity(['is_start' => true, 'is_end' => true, 'assignee_type' => null, 'assignee_user_id' => null]);

        $issues = $this->validator->validate($this->version);

        $this->assertTrue($issues->contains(fn ($i) => $i->code === 'missing_assignee'));
    }

    public function test_task_assigned_to_an_inactive_role_is_an_error(): void
    {
        $role = Role::factory()->for($this->org)->inactive()->create();
        $a = $this->activity([
            'is_start' => true,
            'is_end' => true,
            'assignee_type' => AssigneeType::Role,
            'assignee_role_id' => $role->id,
            'assignee_user_id' => null,
        ]);

        $issues = $this->validator->validate($this->version);

        $this->assertTrue($issues->contains(fn ($i) => $i->code === 'inactive_assignee_role'));
    }

    public function test_task_assigned_to_an_active_role_has_no_assignee_issue(): void
    {
        $role = Role::factory()->for($this->org)->create(['active' => true]);
        $this->activity([
            'is_start' => true,
            'is_end' => true,
            'assignee_type' => AssigneeType::Role,
            'assignee_role_id' => $role->id,
            'assignee_user_id' => null,
        ]);

        $issues = $this->validator->validate($this->version);

        $this->assertFalse($issues->contains(fn ($i) => $i->code === 'inactive_assignee_role'));
        $this->assertFalse($issues->contains(fn ($i) => $i->code === 'missing_assignee'));
    }

    public function test_node_without_incoming_connection_is_only_a_warning(): void
    {
        $a = $this->activity(['is_start' => true, 'is_end' => true]);
        $orphan = $this->activity(); // sem transição de entrada, não é is_start.

        $issues = $this->validator->validate($this->version);
        $unreachable = $issues->first(fn ($i) => $i->code === 'unreachable_node');

        $this->assertNotNull($unreachable);
        $this->assertSame(GraphIssueSeverity::Warning, $unreachable->severity);
        // Um warning sozinho não bloqueia a publicação.
        $this->assertTrue($this->validator->isPublishable($this->version));
    }

    public function test_well_formed_fork_join_block_has_no_pairing_issues(): void
    {
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $c = $this->activity();
        $d = $this->activity(['is_end' => true]);
        $this->transition($a, $b, ['sort_order' => 0]);
        $this->transition($a, $c, ['sort_order' => 1]);
        $this->transition($b, $d);
        $this->transition($c, $d);

        $this->assertTrue($this->validator->isPublishable($this->version));
        $this->assertCount(0, $this->validator->validate($this->version));
    }

    public function test_fork_branch_that_bypasses_the_join_is_a_pairing_error(): void
    {
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $c = $this->activity();
        $d = $this->activity(['is_end' => true]);
        $this->transition($a, $b, ['sort_order' => 0]);
        $this->transition($a, $c, ['sort_order' => 1]);
        $this->transition($b, $d);
        // C pula direto pro fim, sem passar pelo join D — bloco mal formado.
        $this->transition($c, $d);
        $bypassEnd = $this->activity(['is_end' => true]);
        $this->transition($c, $bypassEnd);

        $issues = $this->validator->validate($this->version);

        $this->assertFalse($this->validator->isPublishable($this->version));
        $this->assertTrue($issues->contains(fn ($i) => str_starts_with($i->code, 'fork_')));
    }

    public function test_decision_inside_a_fork_branch_that_does_not_reconverge_before_the_outer_join_is_an_error(): void
    {
        // Exemplo motivador da spec (02-motor-de-execucao.md §3.3.1): fork A->(B,C); dentro do
        // ramo B, uma decisão (XOR) manda para D (o join externo de B/C) OU para um fim
        // separado, sem nunca reconvergir num join próprio antes de D — D acaba recebendo
        // uma quantidade de chegadas que não bate com o número de ramos de A.
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $c = $this->activity();
        $d = $this->activity(['is_end' => true]);
        $separateEnd = $this->activity(['is_end' => true]);

        $this->transition($a, $b, ['sort_order' => 0]);
        $this->transition($a, $c, ['sort_order' => 1]);

        $decision = $this->activity(['type' => WorkflowActivityType::Condition, 'assignee_type' => null, 'assignee_user_id' => null]);
        $this->transition($b, $decision);
        $this->transition($decision, $d, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'result.ok', 'operator' => '=', 'value' => true],
            'sort_order' => 0,
        ]);
        $this->transition($decision, $separateEnd, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'result.ok', 'operator' => '=', 'value' => false],
            'sort_order' => 1,
        ]);
        $this->transition($c, $d);

        $issues = $this->validator->validate($this->version);

        $this->assertFalse($this->validator->isPublishable($this->version));
        $this->assertTrue($issues->contains(fn ($i) => str_starts_with($i->code, 'fork_')));
    }

    public function test_nested_well_formed_fork_join_inside_a_branch_is_valid(): void
    {
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $c = $this->activity();
        $outerJoin = $this->activity(['is_end' => true]);

        $this->transition($a, $b, ['sort_order' => 0]);
        $this->transition($a, $c, ['sort_order' => 1]);

        // Bloco interno bem formado dentro do ramo B.
        $innerB1 = $this->activity();
        $innerB2 = $this->activity();
        $innerJoin = $this->activity();
        $this->transition($b, $innerB1, ['sort_order' => 0]);
        $this->transition($b, $innerB2, ['sort_order' => 1]);
        $this->transition($innerB1, $innerJoin);
        $this->transition($innerB2, $innerJoin);
        $this->transition($innerJoin, $outerJoin);

        $this->transition($c, $outerJoin);

        $this->assertTrue($this->validator->isPublishable($this->version));
        $this->assertCount(0, $this->validator->validate($this->version));
    }
}
