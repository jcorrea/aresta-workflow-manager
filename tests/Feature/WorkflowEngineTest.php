<?php

namespace Tests\Feature;

use App\Enums\AssigneeType;
use App\Enums\ConditionType;
use App\Enums\OrganizationRole;
use App\Enums\ProcessInstanceActivityStatus;
use App\Enums\ProcessInstanceStatus;
use App\Enums\WorkflowActivityType;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceTransitionLog;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Notifications\ActivityAssignedNotification;
use App\Notifications\AutomatedActionFailedNotification;
use App\Notifications\ProcessInstanceCompletedNotification;
use App\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 02-motor-de-execucao.md §6 — grafos montados só via factories, sem depender do canvas nem
 * do `WorkflowGraphValidator` (fase 3, §6.1): o motor confia que o grafo é bem formado.
 */
class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowEngine $engine;

    private Organization $org;

    private Workflow $workflow;

    private WorkflowVersion $version;

    private WorkflowStep $step;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(WorkflowEngine::class);

        $this->org = Organization::factory()->create();
        $org = $this->org;
        $this->user = User::factory()->create();
        $org->users()->attach($this->user);
        $this->actingAs($this->user);

        $this->workflow = Workflow::factory()->for($org)->for($this->user, 'createdBy')->create();
        $this->version = WorkflowVersion::factory()->for($this->workflow)->for($this->user, 'createdBy')->published()->create();
        $this->workflow->update(['current_published_version_id' => $this->version->id]);
        $this->step = WorkflowStep::factory()->for($this->version, 'workflowVersion')->create();
    }

    private function activity(array $attributes = []): WorkflowActivity
    {
        return WorkflowActivity::factory()->for($this->step, 'workflowStep')->create($attributes);
    }

    private function transition(WorkflowActivity $from, WorkflowActivity $to, array $attributes = []): WorkflowTransition
    {
        return WorkflowTransition::factory()->for($this->version, 'workflowVersion')->create(array_merge([
            'from_activity_id' => $from->id,
            'to_activity_id' => $to->id,
        ], $attributes));
    }

    /**
     * `is_and_join` não é `$fillable` (calculado só na publicação, ver
     * `WorkflowActivity::class`) — os testes deste arquivo montam o grafo direto via factory,
     * sem passar pelo `WorkflowGraphValidator`, então precisam marcar explicitamente qual nó é
     * um join de verdade, do jeito que `WorkflowVersionController::publish()` faria.
     */
    private function markAsAndJoin(WorkflowActivity $activity): void
    {
        WorkflowActivity::query()->whereKey($activity->id)->update(['is_and_join' => true]);
    }

    private function latestActivityFor(ProcessInstance $instance, WorkflowActivity $workflowActivity)
    {
        return $instance->activities()
            ->where('workflow_activity_id', $workflowActivity->id)
            ->orderByDesc('id')
            ->first();
    }

    public function test_linear_flow_completes_the_instance_at_the_end_node(): void
    {
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $c = $this->activity(['is_end' => true]);
        $this->transition($a, $b);
        $this->transition($b, $c);

        $instance = $this->engine->start($this->workflow, [], $this->user);

        $activityA = $this->latestActivityFor($instance, $a);
        $this->assertNotNull($activityA);
        $this->assertSame(ProcessInstanceActivityStatus::Pending, $activityA->status);
        $this->assertSame(ProcessInstanceStatus::Running, $instance->fresh()->status);

        $this->engine->completeActivity($activityA);
        $activityB = $this->latestActivityFor($instance, $b);
        $this->assertNotNull($activityB);
        $this->assertSame(ProcessInstanceStatus::Running, $instance->fresh()->status);

        $this->engine->completeActivity($activityB);
        $activityC = $this->latestActivityFor($instance, $c);
        $this->assertNotNull($activityC);
        $this->assertSame(ProcessInstanceStatus::Running, $instance->fresh()->status);

        $this->engine->completeActivity($activityC);
        $this->assertSame(ProcessInstanceStatus::Completed, $instance->fresh()->status);
        $this->assertNotNull($instance->fresh()->completed_at);
    }

    public function test_sla_hours_sets_due_at_on_activation(): void
    {
        $a = $this->activity(['is_start' => true, 'is_end' => true, 'sla_hours' => 48]);

        $instance = $this->engine->start($this->workflow, [], $this->user);
        $activityA = $this->latestActivityFor($instance, $a);

        $this->assertNotNull($activityA->due_at);
        $this->assertEqualsWithDelta(
            $activityA->started_at->addHours(48)->timestamp,
            $activityA->due_at->timestamp,
            1,
        );
    }

    public function test_fork_activates_all_branches_from_multiple_always_transitions(): void
    {
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $c = $this->activity();
        $this->transition($a, $b, ['sort_order' => 0]);
        $this->transition($a, $c, ['sort_order' => 1]);

        $instance = $this->engine->start($this->workflow, [], $this->user);
        $activityA = $this->latestActivityFor($instance, $a);

        $this->engine->completeActivity($activityA);

        $this->assertNotNull($this->latestActivityFor($instance, $b));
        $this->assertNotNull($this->latestActivityFor($instance, $c));
        $this->assertSame(3, $instance->activities()->count());
    }

    public function test_decision_follows_the_matching_expression_branch(): void
    {
        $a = $this->activity(['is_start' => true, 'type' => WorkflowActivityType::Condition]);
        $highValue = $this->activity(['is_end' => true, 'type' => WorkflowActivityType::Condition]);
        $lowValue = $this->activity(['is_end' => true, 'type' => WorkflowActivityType::Condition]);

        $this->transition($a, $highValue, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'context.valor_proposta', 'operator' => '>', 'value' => 50000],
            'sort_order' => 0,
        ]);
        $this->transition($a, $lowValue, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'context.valor_proposta', 'operator' => '<=', 'value' => 50000],
            'sort_order' => 1,
        ]);

        $instanceHigh = $this->engine->start($this->workflow, ['valor_proposta' => 100000], $this->user);
        $this->assertSame(ProcessInstanceStatus::Completed, $instanceHigh->fresh()->status);
        $this->assertNotNull($this->latestActivityFor($instanceHigh, $highValue));
        $this->assertNull($this->latestActivityFor($instanceHigh, $lowValue));

        $instanceLow = $this->engine->start($this->workflow, ['valor_proposta' => 1000], $this->user);
        $this->assertSame(ProcessInstanceStatus::Completed, $instanceLow->fresh()->status);
        $this->assertNotNull($this->latestActivityFor($instanceLow, $lowValue));
        $this->assertNull($this->latestActivityFor($instanceLow, $highValue));
    }

    /**
     * Um nó `condition` se autocompleta com `result = []` (ver `activateActivity()`) — só
     * enxerga `context`. Sem mesclar o `result` de quem o antecede de volta em `context`, um
     * nó de decisão logo depois de um `form`/`task` nunca teria como saber o que a pessoa
     * respondeu. Cobre exatamente esse desenho: form → decisão dedicada → aprovado/reprovado.
     */
    public function test_completing_an_activity_merges_its_result_into_context_for_a_downstream_decision(): void
    {
        $form = $this->activity(['is_start' => true]);
        $decision = $this->activity(['type' => WorkflowActivityType::Condition]);
        $approved = $this->activity(['is_end' => true, 'type' => WorkflowActivityType::Condition]);
        $rejected = $this->activity(['is_end' => true, 'type' => WorkflowActivityType::Condition]);

        $this->transition($form, $decision);
        $this->transition($decision, $approved, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'context.decisao', 'operator' => '=', 'value' => 'aprovado'],
            'sort_order' => 0,
        ]);
        $this->transition($decision, $rejected, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'context.decisao', 'operator' => '=', 'value' => 'recusado'],
            'sort_order' => 1,
        ]);

        $instance = $this->engine->start($this->workflow, [], $this->user);
        $formActivity = $this->latestActivityFor($instance, $form);

        $this->engine->completeActivity($formActivity, ['decisao' => 'recusado']);

        $this->assertSame(['decisao' => 'recusado'], $instance->fresh()->context);
        $this->assertSame(ProcessInstanceStatus::Completed, $instance->fresh()->status);
        $this->assertNotNull($this->latestActivityFor($instance, $rejected));
        $this->assertNull($this->latestActivityFor($instance, $approved));
    }

    public function test_decision_with_no_matching_branch_and_no_fallback_leaves_the_instance_stuck(): void
    {
        $a = $this->activity(['is_start' => true, 'type' => WorkflowActivityType::Condition]);
        $b = $this->activity(['is_end' => true]);

        $this->transition($a, $b, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'context.valor_proposta', 'operator' => '>', 'value' => 50000],
        ]);

        $instance = $this->engine->start($this->workflow, ['valor_proposta' => 1], $this->user);

        $this->assertSame(ProcessInstanceStatus::Running, $instance->fresh()->status);
        $this->assertNull($this->latestActivityFor($instance, $b));
    }

    public function test_join_activates_only_after_both_branches_complete_regardless_of_order(): void
    {
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $c = $this->activity();
        $d = $this->activity();
        $this->transition($a, $b, ['sort_order' => 0]);
        $this->transition($a, $c, ['sort_order' => 1]);
        $this->transition($b, $d, ['sort_order' => 0]);
        $this->transition($c, $d, ['sort_order' => 0]);
        $this->markAsAndJoin($d);

        // Ordem 1: B antes de C.
        $instance1 = $this->engine->start($this->workflow, [], $this->user);
        $this->engine->completeActivity($this->latestActivityFor($instance1, $a));
        $b1 = $this->latestActivityFor($instance1, $b);
        $c1 = $this->latestActivityFor($instance1, $c);

        $this->engine->completeActivity($b1);
        $this->assertNull($this->latestActivityFor($instance1, $d), 'D não deveria ativar só com B completo');

        $this->engine->completeActivity($c1);
        $this->assertNotNull($this->latestActivityFor($instance1, $d));

        // Ordem 2: C antes de B (instância nova, independente).
        $instance2 = $this->engine->start($this->workflow, [], $this->user);
        $this->engine->completeActivity($this->latestActivityFor($instance2, $a));
        $b2 = $this->latestActivityFor($instance2, $b);
        $c2 = $this->latestActivityFor($instance2, $c);

        $this->engine->completeActivity($c2);
        $this->assertNull($this->latestActivityFor($instance2, $d), 'D não deveria ativar só com C completo');

        $this->engine->completeActivity($b2);
        $this->assertNotNull($this->latestActivityFor($instance2, $d));
    }

    public function test_cycle_creates_a_new_activity_and_logs_each_pass(): void
    {
        $a = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $end = $this->activity(['is_end' => true, 'type' => WorkflowActivityType::Condition]);

        $this->transition($a, $b, ['sort_order' => 0]);
        $this->transition($b, $a, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'result.retry', 'operator' => '=', 'value' => true],
            'sort_order' => 0,
        ]);
        $this->transition($b, $end, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'result.retry', 'operator' => '=', 'value' => false],
            'sort_order' => 1,
        ]);

        $instance = $this->engine->start($this->workflow, [], $this->user);

        $a1 = $this->latestActivityFor($instance, $a);
        $this->engine->completeActivity($a1);
        $b1 = $this->latestActivityFor($instance, $b);
        $this->engine->completeActivity($b1, ['retry' => true]);

        $a2 = $this->latestActivityFor($instance, $a);
        $this->assertNotSame($a1->id, $a2->id, 'segunda passagem por A deve criar uma nova ProcessInstanceActivity');
        $this->assertSame(ProcessInstanceStatus::Running, $instance->fresh()->status);

        $this->engine->completeActivity($a2);
        $b2 = $this->latestActivityFor($instance, $b);
        $this->assertNotSame($b1->id, $b2->id);

        $this->engine->completeActivity($b2, ['retry' => false]);
        $this->assertSame(ProcessInstanceStatus::Completed, $instance->fresh()->status);
        $this->assertNotNull($this->latestActivityFor($instance, $end));

        $logsAtoB = ProcessInstanceTransitionLog::query()
            ->where('process_instance_id', $instance->id)
            ->where('from_activity_id', $a->id)
            ->where('to_activity_id', $b->id)
            ->count();
        $this->assertSame(2, $logsAtoB, 'cada passagem A->B deve gerar seu próprio log, não sobrescrever');
    }

    public function test_cycle_with_fork_join_does_not_activate_the_join_early_on_the_second_pass(): void
    {
        $f = $this->activity(['is_start' => true]);
        $b = $this->activity();
        $c = $this->activity();
        $j = $this->activity();
        $end = $this->activity(['is_end' => true, 'type' => WorkflowActivityType::Condition]);

        $this->transition($f, $b, ['sort_order' => 0]);
        $this->transition($f, $c, ['sort_order' => 1]);
        $this->transition($b, $j, ['sort_order' => 0]);
        $this->transition($c, $j, ['sort_order' => 0]);
        $this->transition($j, $f, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'result.retry', 'operator' => '=', 'value' => true],
            'sort_order' => 0,
        ]);
        $this->transition($j, $end, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'result.retry', 'operator' => '=', 'value' => false],
            'sort_order' => 1,
        ]);
        $this->markAsAndJoin($j);

        $instance = $this->engine->start($this->workflow, [], $this->user);

        // Primeira passagem.
        $this->engine->completeActivity($this->latestActivityFor($instance, $f));
        $b1 = $this->latestActivityFor($instance, $b);
        $c1 = $this->latestActivityFor($instance, $c);
        $this->engine->completeActivity($b1);
        $this->assertNull($this->latestActivityFor($instance, $j));
        $this->engine->completeActivity($c1);
        $j1 = $this->latestActivityFor($instance, $j);
        $this->assertNotNull($j1, 'join deve ativar quando as duas chegadas da primeira passagem completam');

        $this->engine->completeActivity($j1, ['retry' => true]);
        $f2 = $this->latestActivityFor($instance, $f);
        $this->assertSame(ProcessInstanceStatus::Running, $instance->fresh()->status);

        // A janela de contagem do join (§3.3.2) filtra por `transitioned_at` com granularidade
        // de segundo — avança o relógio para não colidir com o timestamp de conclusão de `j1`
        // (que já aconteceu no mesmo segundo, na prática irrelevante fora de um teste correndo
        // em microssegundos).
        $this->travel(1)->second();

        // Segunda passagem — não pode reaproveitar as chegadas antigas da primeira.
        $this->engine->completeActivity($f2);
        $b2 = $this->latestActivityFor($instance, $b);
        $c2 = $this->latestActivityFor($instance, $c);
        $this->assertNotSame($b1->id, $b2->id);
        $this->assertNotSame($c1->id, $c2->id);

        $this->engine->completeActivity($b2);
        $this->assertSame(
            $j1->id,
            $this->latestActivityFor($instance, $j)->id,
            'join não deve reativar contando só 1 chegada nova + chegadas antigas da primeira passagem',
        );

        $this->engine->completeActivity($c2);
        $j2 = $this->latestActivityFor($instance, $j);
        $this->assertNotSame($j1->id, $j2->id, 'join deve ativar de novo só quando as duas chegadas da segunda passagem completarem');

        $this->engine->completeActivity($j2, ['retry' => false]);
        $this->assertSame(ProcessInstanceStatus::Completed, $instance->fresh()->status);
    }

    /**
     * Bug relatado em produção (instância 2026-000015, "Processo de reajuste"): um nó de
     * formulário recebia tanto a entrada direta quanto o retorno de um loop de reprovação — sem
     * nenhum fork correspondente. Antes do fix, `isJoin()` contava 2 transições de entrada e
     * classificava o nó como AND-join, travando a instância na primeira chegada à espera de uma
     * segunda chegada que só existe depois da primeira já ter passado por ali.
     */
    public function test_rework_loop_merging_into_an_already_reached_node_activates_immediately_and_can_repeat(): void
    {
        $a = $this->activity(['is_start' => true]);
        $sendEmail = $this->activity(['type' => WorkflowActivityType::AutomatedAction]);
        $propose = $this->activity(); // alvo do merge (loop de reprovação) — não é AND-join.
        $evaluate = $this->activity();
        $decision = $this->activity(['type' => WorkflowActivityType::Condition]);
        $end = $this->activity(['is_end' => true, 'type' => WorkflowActivityType::Condition]);

        $this->transition($a, $sendEmail);
        $this->transition($sendEmail, $propose);
        $this->transition($propose, $evaluate);
        $this->transition($evaluate, $decision);
        $this->transition($decision, $propose, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'context.status', 'operator' => '=', 'value' => 'reprovado'],
            'sort_order' => 0,
        ]);
        $this->transition($decision, $end, [
            'condition_type' => ConditionType::Expression,
            'condition_expression' => ['field' => 'context.status', 'operator' => '=', 'value' => 'aprovado'],
            'sort_order' => 1,
        ]);
        // $propose intencionalmente não é marcado is_and_join — replica o grafo do bug relatado.

        $instance = $this->engine->start($this->workflow, [], $this->user);
        $this->engine->completeActivity($this->latestActivityFor($instance, $a));

        $propose1 = $this->latestActivityFor($instance, $propose);
        $this->assertNotNull($propose1, 'primeira chegada no nó de merge deve ativar direto, sem esperar o loop');

        $this->engine->completeActivity($propose1);
        $this->engine->completeActivity($this->latestActivityFor($instance, $evaluate), ['status' => 'reprovado']);

        $propose2 = $this->latestActivityFor($instance, $propose);
        $this->assertNotSame($propose1->id, $propose2->id, 'reprovação deve reativar o mesmo nó, gerando uma nova passagem');

        $this->engine->completeActivity($propose2);
        $this->engine->completeActivity($this->latestActivityFor($instance, $evaluate), ['status' => 'aprovado']);

        $this->assertSame(ProcessInstanceStatus::Completed, $instance->fresh()->status);
    }

    public function test_automated_action_webhook_completes_without_human_intervention(): void
    {
        Http::fake([
            'https://example.test/hook' => Http::response(['ok' => true], 200),
        ]);

        $a = $this->activity(['is_start' => true]);
        $hook = $this->activity([
            'type' => WorkflowActivityType::AutomatedAction,
            'config' => ['action' => 'webhook', 'url' => 'https://example.test/hook', 'method' => 'POST'],
        ]);
        $end = $this->activity(['is_end' => true, 'type' => WorkflowActivityType::Condition]);

        $this->transition($a, $hook);
        $this->transition($hook, $end);

        $instance = $this->engine->start($this->workflow, [], $this->user);
        $this->engine->completeActivity($this->latestActivityFor($instance, $a));

        Http::assertSent(fn ($request) => $request->url() === 'https://example.test/hook');

        $hookActivity = $this->latestActivityFor($instance, $hook);
        $this->assertNotNull($hookActivity);
        $this->assertSame(ProcessInstanceActivityStatus::Completed, $hookActivity->status);
        $this->assertSame(200, $hookActivity->result['status']);

        $this->assertSame(ProcessInstanceStatus::Completed, $instance->fresh()->status);
    }

    public function test_automated_action_failure_does_not_complete_and_notifies_workflow_admins(): void
    {
        Notification::fake();
        Http::fake([
            'https://example.test/hook' => Http::response(['error' => 'boom'], 500),
        ]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->org->id);
        $admin = User::factory()->create();
        $this->org->users()->attach($admin);
        $admin->assignRole(OrganizationRole::Admin->value);
        $registrar->setPermissionsTeamId(0);

        $a = $this->activity(['is_start' => true]);
        $hook = $this->activity([
            'type' => WorkflowActivityType::AutomatedAction,
            'config' => ['action' => 'webhook', 'url' => 'https://example.test/hook', 'method' => 'POST'],
        ]);
        $end = $this->activity(['is_end' => true]);
        $this->transition($a, $hook);
        $this->transition($hook, $end);

        $instance = $this->engine->start($this->workflow, [], $this->user);
        $this->engine->completeActivity($this->latestActivityFor($instance, $a));

        $hookActivity = $this->latestActivityFor($instance, $hook);
        $this->assertSame(ProcessInstanceActivityStatus::InProgress, $hookActivity->status);
        $this->assertSame('HTTP 500', $hookActivity->result['error']);
        $this->assertNull($this->latestActivityFor($instance, $end));
        $this->assertSame(ProcessInstanceStatus::Running, $instance->fresh()->status);

        Notification::assertSentTo($admin, AutomatedActionFailedNotification::class);
        Notification::assertNotSentTo($this->user, AutomatedActionFailedNotification::class);
    }

    public function test_activating_a_task_with_a_fixed_user_assignee_notifies_that_user(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $this->org->users()->attach($assignee);

        $a = $this->activity([
            'is_start' => true,
            'is_end' => true,
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $assignee->id,
        ]);

        $this->engine->start($this->workflow, [], $this->user);

        Notification::assertSentTo($assignee, ActivityAssignedNotification::class);
    }

    public function test_activating_a_queued_role_task_notifies_every_eligible_user(): void
    {
        Notification::fake();

        $role = Role::factory()->for($this->org)->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $this->org->users()->attach([$memberA->id, $memberB->id]);
        $role->users()->attach([$memberA->id, $memberB->id]);

        $this->activity([
            'is_start' => true,
            'is_end' => true,
            'assignee_type' => AssigneeType::Role,
            'assignee_role_id' => $role->id,
        ]);

        $this->engine->start($this->workflow, [], $this->user);

        Notification::assertSentTo($memberA, ActivityAssignedNotification::class);
        Notification::assertSentTo($memberB, ActivityAssignedNotification::class);
    }

    public function test_process_completion_notifies_the_user_who_started_it(): void
    {
        Notification::fake();

        $a = $this->activity(['is_start' => true, 'is_end' => true]);

        $instance = $this->engine->start($this->workflow, [], $this->user);
        $this->engine->completeActivity($this->latestActivityFor($instance, $a));

        Notification::assertSentTo($this->user, ProcessInstanceCompletedNotification::class);
    }
}
