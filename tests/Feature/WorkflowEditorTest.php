<?php

namespace Tests\Feature;

use App\Enums\ActivationAction;
use App\Enums\AssigneeType;
use App\Enums\ConditionType;
use App\Enums\OrganizationRole;
use App\Enums\WorkflowVersionStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowVersionActivation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Fluxo do editor visual (03-editor-visual.md) de ponta a ponta: criar workflow, editar
 * rascunho (steps/activities/transitions), bloquear publicação com problemas, publicar,
 * "editar de novo", rollback — e a autorização por papel (04-integracao-e-notificacoes.md
 * §2.1) e isolamento entre organizações em cada endpoint.
 */
class WorkflowEditorTest extends TestCase
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

    public function test_workflow_admin_can_create_a_workflow_and_lands_on_the_draft_editor(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);

        $response = $this->actingAs($admin)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Aprovação de Proposta',
        ]);

        $workflow = Workflow::firstWhere('name', 'Aprovação de Proposta');
        $this->assertNotNull($workflow);
        $draft = WorkflowVersion::where('workflow_id', $workflow->id)->first();
        $this->assertSame(WorkflowVersionStatus::Draft, $draft->status);

        $response->assertRedirect(route('workflows.versions.edit', [$workflow, $draft]));
    }

    public function test_workflow_viewer_cannot_create_a_workflow(): void
    {
        $org = Organization::factory()->create();
        $viewer = $this->makeUserWithRole($org, OrganizationRole::Viewer->value);

        $this->actingAs($viewer)
            ->post(route('workflows.store'), ['organization_id' => $org->id, 'name' => 'X'])
            ->assertForbidden();
    }

    public function test_editor_can_mutate_the_draft_graph(): void
    {
        $org = Organization::factory()->create();
        $editor = $this->makeUserWithRole($org, OrganizationRole::Editor->value);
        $workflow = Workflow::factory()->for($org)->for($editor, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($editor, 'createdBy')->create();

        $this->actingAs($editor);

        $stepResponse = $this->postJson(route('workflow-steps.store', [$workflow, $version]), [
            'name' => 'Análise',
            'position_x' => 0,
            'position_y' => 0,
            'width' => 300,
            'height' => 200,
            'sort_order' => 0,
        ])->assertOk();
        $stepId = $stepResponse->json('id');

        $activityResponse = $this->postJson(route('workflow-activities.store', [$workflow, $version]), [
            'workflow_step_id' => $stepId,
            'name' => 'Solicitar aprovação',
            'type' => 'task',
            'assignee_type' => null,
            'config' => [],
            'is_start' => true,
        ])->assertOk();
        $activityAId = $activityResponse->json('id');

        $activityBResponse = $this->postJson(route('workflow-activities.store', [$workflow, $version]), [
            'workflow_step_id' => $stepId,
            'name' => 'Fim',
            'type' => 'task',
            'config' => [],
            'is_end' => true,
        ])->assertOk();
        $activityBId = $activityBResponse->json('id');

        $transitionResponse = $this->postJson(route('workflow-transitions.store', [$workflow, $version]), [
            'from_activity_id' => $activityAId,
            'to_activity_id' => $activityBId,
            'condition_type' => 'always',
            'sort_order' => 0,
        ])->assertOk();

        $this->assertSame(1, WorkflowStep::count());
        $this->assertSame(2, WorkflowActivity::count());
        $this->assertSame(1, WorkflowTransition::count());

        $this->patchJson(route('workflow-activities.update', WorkflowActivity::find($activityAId)), [
            'name' => 'Solicitar aprovação financeira',
        ])->assertOk();
        $this->assertSame('Solicitar aprovação financeira', WorkflowActivity::find($activityAId)->name);

        $this->deleteJson(route('workflow-transitions.destroy', $transitionResponse->json('id')));
        $this->assertSame(0, WorkflowTransition::count());
    }

    public function test_viewer_can_view_but_cannot_mutate_the_draft(): void
    {
        $org = Organization::factory()->create();
        $viewer = $this->makeUserWithRole($org, OrganizationRole::Viewer->value);
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->create();

        $this->actingAs($viewer)
            ->get(route('workflows.versions.edit', [$workflow, $version]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson(route('workflow-steps.store', [$workflow, $version]), ['name' => 'X', 'position_x' => 0, 'position_y' => 0, 'width' => 1, 'height' => 1, 'sort_order' => 0])
            ->assertForbidden();
    }

    public function test_user_from_another_organization_cannot_reach_the_workflow_at_all(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $adminA = $this->makeUserWithRole($orgA, OrganizationRole::Admin->value);
        $outsider = $this->makeUserWithRole($orgB, OrganizationRole::Admin->value);

        $workflow = Workflow::factory()->for($orgA)->for($adminA, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($adminA, 'createdBy')->create();

        $this->actingAs($outsider)
            ->get(route('workflows.versions.edit', [$workflow, $version]))
            ->assertNotFound();
    }

    public function test_publish_is_blocked_when_the_graph_has_errors(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->create();
        // Nenhum nó is_start/is_end criado — grafo claramente inválido.

        $this->actingAs($admin)
            ->post(route('workflows.versions.publish', [$workflow, $version]))
            ->assertSessionHasErrors('graph');

        $this->assertSame(WorkflowVersionStatus::Draft, $version->fresh()->status);
        $this->assertNull($workflow->fresh()->current_published_version_id);
    }

    public function test_editor_cannot_publish_even_with_a_valid_graph(): void
    {
        $org = Organization::factory()->create();
        $editor = $this->makeUserWithRole($org, OrganizationRole::Editor->value);
        $workflow = Workflow::factory()->for($org)->for($editor, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($editor, 'createdBy')->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        WorkflowActivity::factory()->for($step, 'workflowStep')->create([
            'is_start' => true,
            'is_end' => true,
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $editor->id,
        ]);

        $this->actingAs($editor)
            ->post(route('workflows.versions.publish', [$workflow, $version]))
            ->assertForbidden();
    }

    public function test_admin_can_publish_a_valid_graph_and_it_becomes_the_current_version(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        WorkflowActivity::factory()->for($step, 'workflowStep')->create([
            'is_start' => true,
            'is_end' => true,
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('workflows.versions.publish', [$workflow, $version]))
            ->assertRedirect(route('workflows.show', $workflow));

        $version->refresh();
        $workflow->refresh();

        $this->assertSame(WorkflowVersionStatus::Published, $version->status);
        $this->assertSame(1, $version->version_number);
        $this->assertNotNull($version->published_at);
        $this->assertNull($version->draft_lock_workflow_id);
        $this->assertSame($version->id, $workflow->current_published_version_id);

        $activation = WorkflowVersionActivation::where('workflow_version_id', $version->id)->first();
        $this->assertSame(ActivationAction::Publish, $activation->action);
    }

    /**
     * `WorkflowEngine::isJoin()` passa a confiar em `is_and_join` em vez de contar transições de
     * entrada em runtime (02-motor-de-execucao.md §3.3.1) — só a publicação, que já roda o
     * `WorkflowGraphValidator`, tem a análise de pós-dominância necessária pra saber quais nós
     * são join de verdade.
     */
    public function test_publish_marks_the_real_join_of_a_well_formed_fork_join_block_as_and_join(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->create();
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();

        $activity = fn (array $attributes = []) => WorkflowActivity::factory()->for($step, 'workflowStep')->create(array_merge([
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => $admin->id,
        ], $attributes));
        $transition = fn ($from, $to, array $attributes = []) => WorkflowTransition::factory()->for($version, 'workflowVersion')->create(array_merge([
            'from_activity_id' => $from->id,
            'to_activity_id' => $to->id,
        ], $attributes));

        $a = $activity(['is_start' => true]);
        $b = $activity();
        $c = $activity();
        $join = $activity(['is_end' => true]);
        $transition($a, $b, ['sort_order' => 0]);
        $transition($a, $c, ['sort_order' => 1]);
        $transition($b, $join);
        $transition($c, $join);

        $this->actingAs($admin)
            ->post(route('workflows.versions.publish', [$workflow, $version]))
            ->assertRedirect(route('workflows.show', $workflow));

        $this->assertTrue($join->fresh()->is_and_join);
        $this->assertFalse($a->fresh()->is_and_join);
        $this->assertFalse($b->fresh()->is_and_join);
    }

    public function test_edit_again_clones_the_published_version_into_a_new_draft(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->published()->create(['version_number' => 1]);
        $workflow->update(['current_published_version_id' => $version->id]);
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create(['name' => 'Etapa original']);
        $a = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_start' => true, 'name' => 'A']);
        $b = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_end' => true, 'name' => 'B']);
        WorkflowTransition::factory()->for($version, 'workflowVersion')->create([
            'from_activity_id' => $a->id,
            'to_activity_id' => $b->id,
            'condition_type' => ConditionType::Always,
        ]);

        $response = $this->actingAs($admin)->post(route('workflows.versions.store', $workflow));

        $newDraft = WorkflowVersion::where('workflow_id', $workflow->id)->where('status', WorkflowVersionStatus::Draft)->first();
        $this->assertNotNull($newDraft);
        $response->assertRedirect(route('workflows.versions.edit', [$workflow, $newDraft]));

        $this->assertSame(1, $newDraft->steps()->count());
        $this->assertSame('Etapa original', $newDraft->steps()->first()->name);
        $this->assertSame(2, WorkflowActivity::whereHas('workflowStep', fn ($q) => $q->where('workflow_version_id', $newDraft->id))->count());
        $this->assertSame(1, WorkflowTransition::where('workflow_version_id', $newDraft->id)->count());

        // Chamar de novo enquanto o draft já existe simplesmente reaproveita o mesmo draft.
        $second = $this->actingAs($admin)->post(route('workflows.versions.store', $workflow));
        $second->assertRedirect(route('workflows.versions.edit', [$workflow, $newDraft]));
        $this->assertSame(1, WorkflowVersion::where('workflow_id', $workflow->id)->where('status', WorkflowVersionStatus::Draft)->count());
    }

    public function test_rollback_repoints_the_current_published_version_and_logs_it(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $v1 = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->published()->create(['version_number' => 1]);
        $v2 = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->published()->create(['version_number' => 2]);
        $workflow->update(['current_published_version_id' => $v2->id]);

        $this->actingAs($admin)
            ->post(route('workflows.rollback', $workflow), ['workflow_version_id' => $v1->id])
            ->assertRedirect(route('workflows.show', $workflow));

        $this->assertSame($v1->id, $workflow->fresh()->current_published_version_id);
        $this->assertSame(WorkflowVersionStatus::Published, $v1->fresh()->status);
        $this->assertSame(WorkflowVersionStatus::Published, $v2->fresh()->status);

        $activation = WorkflowVersionActivation::where('workflow_version_id', $v1->id)
            ->where('action', ActivationAction::Rollback)
            ->first();
        $this->assertNotNull($activation);
    }

    public function test_cannot_mutate_a_published_version(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->published()->create(['version_number' => 1]);
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();

        $this->actingAs($admin)
            ->patchJson(route('workflow-steps.update', $step), ['name' => 'Nova tentativa'])
            ->assertStatus(422);
    }

    public function test_edit_page_exposes_the_graph_payload_and_validation_issues(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->create();

        $this->actingAs($admin)
            ->get(route('workflows.versions.edit', [$workflow, $version]))
            ->assertInertia(fn ($page) => $page
                ->component('Workflows/Versions/Edit')
                ->where('version.id', $version->id)
                ->has('graph.nodes')
                ->has('graph.edges')
                ->where('issues.0.code', 'missing_start')
                ->where('workflow.currentPublishedVersionNumber', null)
            );
    }

    /**
     * O botão "Iniciar instância (teste)" do editor visual só faz sentido quando já existe uma
     * versão publicada pra rodar (a instância nunca é criada a partir do rascunho em edição) —
     * ver comentário em ProcessInstanceController::store.
     */
    public function test_edit_page_exposes_the_published_version_number_when_one_exists(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $published = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->published()->create(['version_number' => 3]);
        $workflow->update(['current_published_version_id' => $published->id]);
        $draft = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->create();

        $this->actingAs($admin)
            ->get(route('workflows.versions.edit', [$workflow, $draft]))
            ->assertInertia(fn ($page) => $page
                ->component('Workflows/Versions/Edit')
                ->where('workflow.currentPublishedVersionNumber', 3)
            );
    }
}
