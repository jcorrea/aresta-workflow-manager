<?php

namespace Tests\Feature\Api;

use App\Enums\AssigneeType;
use App\Enums\OrganizationRole;
use App\Enums\WorkflowActivityType;
use App\Models\ExternalSystem;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Notifications\ExternalAttributionNeedsReviewNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 04-integracao-e-notificacoes.md §5 — API pública Sanctum para sistemas GIITS externos.
 * Autenticação por token de sistema (não usuário), `organization_id` sempre explícito no
 * request e validado, isolamento entre organizações reforçado mesmo o ator sendo confiável
 * (`ExternalSystem` bypassa o global scope, mas o controller ainda confere explicitamente).
 */
class ProcessInstanceApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Workflow $workflow;

    private WorkflowActivity $activity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $creator = User::factory()->create();
        $this->org->users()->attach($creator);

        $this->workflow = Workflow::factory()->for($this->org)->for($creator, 'createdBy')->create(['slug' => 'aprovacao']);
        $version = WorkflowVersion::factory()->for($this->workflow)->for($creator, 'createdBy')->published()->create();
        $this->workflow->update(['current_published_version_id' => $version->id]);
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $this->activity = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_start' => true, 'is_end' => true]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ])->assertUnauthorized();
    }

    public function test_external_system_can_start_an_instance_via_a_real_bearer_token(): void
    {
        $system = ExternalSystem::factory()->create();
        $token = $system->createToken('giits-propostas')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/workflows/{$this->workflow->slug}/instances", [
                'organization_id' => $this->org->id,
                'context' => ['valor' => 1000],
            ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'code', 'status']);

        $instance = ProcessInstance::withoutGlobalScopes()->findOrFail($response->json('id'));
        $this->assertNull($instance->started_by);
        $this->assertSame($system->id, $instance->started_by_external_system_id);
        $this->assertSame(['valor' => 1000], $instance->context);
    }

    public function test_starting_for_an_inactive_organization_is_rejected(): void
    {
        $this->org->update(['active' => false]);
        Sanctum::actingAs(ExternalSystem::factory()->create());

        $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ])->assertNotFound();
    }

    /**
     * Token pessoal (self-service, `ApiTokenController`) nasce escopado a UMA organização via
     * ability `org:{id}` — só ele tem essa restrição checada; `ExternalSystem` (token por
     * aplicação cliente) continua livre, como sempre foi.
     */
    public function test_user_token_scoped_to_the_organization_can_start_an_instance(): void
    {
        $user = User::factory()->create();
        $this->org->users()->attach($user);
        Sanctum::actingAs($user, ["org:{$this->org->id}"]);

        $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ])->assertCreated();
    }

    public function test_user_token_scoped_to_a_different_organization_cannot_reach_this_one(): void
    {
        $otherOrg = Organization::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user, ["org:{$otherOrg->id}"]);

        $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ])->assertForbidden();
    }

    public function test_user_token_without_any_organization_ability_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, []);

        $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ])->assertForbidden();
    }

    public function test_external_system_token_is_not_restricted_by_organization_ability(): void
    {
        Sanctum::actingAs(ExternalSystem::factory()->create(), []);

        $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ])->assertCreated();
    }

    public function test_starting_a_workflow_slug_that_does_not_belong_to_the_organization_is_rejected(): void
    {
        $otherOrg = Organization::factory()->create();
        Sanctum::actingAs(ExternalSystem::factory()->create());

        $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $otherOrg->id,
        ])->assertNotFound();
    }

    public function test_show_and_activities_require_a_matching_organization_id(): void
    {
        Sanctum::actingAs(ExternalSystem::factory()->create());

        $start = $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ]);
        $code = $start->json('code');

        $this->getJson("/api/instances/{$code}?organization_id={$this->org->id}")
            ->assertOk()
            ->assertJsonPath('code', $code)
            ->assertJsonPath('status', 'running'); // nó task (padrão do factory) espera conclusão humana.

        $otherOrg = Organization::factory()->create();
        $this->getJson("/api/instances/{$code}?organization_id={$otherOrg->id}")
            ->assertNotFound();
    }

    public function test_activities_endpoint_lists_the_instance_activities(): void
    {
        Sanctum::actingAs(ExternalSystem::factory()->create());

        $start = $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ]);
        $code = $start->json('code');

        $this->getJson("/api/instances/{$code}/activities?organization_id={$this->org->id}")
            ->assertOk()
            ->assertJsonCount(1, 'activities')
            ->assertJsonPath('activities.0.status', 'pending')
            ->assertJsonPath('activities.0.workflow_activity_id', $this->activity->id);
    }

    public function test_completing_an_api_confirmation_activity_advances_the_graph(): void
    {
        $creator = $this->workflow->createdBy;
        $version = WorkflowVersion::factory()->for($this->workflow)->for($creator, 'createdBy')->create();
        $this->workflow->update(['current_published_version_id' => $version->id]);
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        // Condition auto-completa ao ativar, cascateando direto pro nó automated_action sem
        // precisar de uma conclusão humana no meio do teste.
        $a = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_start' => true, 'type' => WorkflowActivityType::Condition]);
        // `action = api_confirmation`: a "execução" é o sistema externo confirmar via API
        // (04-integracao-e-notificacoes.md §5) — fica in_progress até o POST .../complete.
        $automated = WorkflowActivity::factory()->for($step, 'workflowStep')->create([
            'type' => WorkflowActivityType::AutomatedAction,
            'config' => ['action' => 'api_confirmation'],
        ]);
        $end = WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_end' => true, 'type' => WorkflowActivityType::Condition]);
        WorkflowTransition::factory()->for($version, 'workflowVersion')->create(['from_activity_id' => $a->id, 'to_activity_id' => $automated->id]);
        WorkflowTransition::factory()->for($version, 'workflowVersion')->create(['from_activity_id' => $automated->id, 'to_activity_id' => $end->id]);

        Sanctum::actingAs(ExternalSystem::factory()->create());
        $start = $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ]);
        $code = $start->json('code');
        $instance = ProcessInstance::withoutGlobalScopes()->findOrFail($start->json('id'));

        $pending = $instance->activities()->where('workflow_activity_id', $automated->id)->firstOrFail();
        $this->assertSame('in_progress', $pending->status->value);

        $this->postJson("/api/instances/{$code}/activities/{$pending->id}/complete?organization_id={$this->org->id}", [
            'result' => ['confirmed' => true],
        ])->assertOk()->assertJsonPath('status', 'completed');

        $this->assertSame('completed', $instance->fresh()->status->value);

        // Segunda confirmação (retry do sistema externo) é rejeitada, não reprocessa o grafo.
        $this->postJson("/api/instances/{$code}/activities/{$pending->id}/complete?organization_id={$this->org->id}", [])
            ->assertStatus(422);
    }

    /**
     * `task`/`form` passaram a ser completáveis via API (04-integracao-e-notificacoes.md §5,
     * para sistemas externos já conectados reportando conclusão de um humano) — mas exigem
     * `completed_by`, diferente de `automated_action`.
     */
    public function test_completing_a_task_activity_via_api_without_completed_by_is_rejected(): void
    {
        Sanctum::actingAs(ExternalSystem::factory()->create());

        $start = $this->postJson("/api/workflows/{$this->workflow->slug}/instances", [
            'organization_id' => $this->org->id,
        ]);
        $code = $start->json('code');
        $instance = ProcessInstance::withoutGlobalScopes()->findOrFail($start->json('id'));
        $activityId = $instance->activities()->firstOrFail()->id;

        $this->postJson("/api/instances/{$code}/activities/{$activityId}/complete?organization_id={$this->org->id}", [])
            ->assertStatus(422);
    }

    public function test_condition_activity_cannot_be_completed_via_api(): void
    {
        $creator = $this->workflow->createdBy;
        $version = WorkflowVersion::factory()->for($this->workflow)->for($creator, 'createdBy')->create();
        $this->workflow->update(['current_published_version_id' => $version->id]);
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();
        $condition = WorkflowActivity::factory()->for($step, 'workflowStep')->condition()->create(['is_start' => true, 'is_end' => true]);

        Sanctum::actingAs(ExternalSystem::factory()->create());
        $start = $this->postJson("/api/workflows/{$this->workflow->slug}/instances", ['organization_id' => $this->org->id]);
        $code = $start->json('code');
        $instance = ProcessInstance::withoutGlobalScopes()->findOrFail($start->json('id'));

        // Condition auto-completa ao ativar (is_end) — instância já terminou; tenta completar
        // de novo via API mesmo assim pra confirmar que o tipo nunca é aceito.
        $activityId = $instance->activities()->firstOrFail()->id;
        $this->postJson("/api/instances/{$code}/activities/{$activityId}/complete?organization_id={$this->org->id}", [])
            ->assertStatus(422);
    }

    /**
     * @return array{instance: ProcessInstance, activity: WorkflowActivity, code: string}
     */
    private function startInstanceWithHumanActivity(AssigneeType $assigneeType, ?Role $role = null, ?User $fixedUser = null): array
    {
        $creator = $this->workflow->createdBy;
        $version = WorkflowVersion::factory()->for($this->workflow)->for($creator, 'createdBy')->create();
        $this->workflow->update(['current_published_version_id' => $version->id]);
        $step = WorkflowStep::factory()->for($version, 'workflowVersion')->create();

        $activity = WorkflowActivity::factory()->for($step, 'workflowStep')->create([
            'is_start' => true,
            'is_end' => true,
            'type' => WorkflowActivityType::Form,
            'assignee_type' => $assigneeType,
            'assignee_role_id' => $role?->id,
            'assignee_user_id' => $fixedUser?->id,
        ]);

        Sanctum::actingAs(ExternalSystem::factory()->create());
        $start = $this->postJson("/api/workflows/{$this->workflow->slug}/instances", ['organization_id' => $this->org->id]);
        $instance = ProcessInstance::withoutGlobalScopes()->findOrFail($start->json('id'));

        return ['instance' => $instance, 'activity' => $activity, 'code' => $start->json('code')];
    }

    public function test_completing_a_human_activity_via_api_with_an_eligible_role_member_sets_assigned_user_id(): void
    {
        $role = Role::factory()->for($this->org)->create();
        $eligibleUser = User::factory()->create(['email' => 'eligivel@example.com']);
        $role->users()->attach($eligibleUser);

        ['instance' => $instance, 'code' => $code] = $this->startInstanceWithHumanActivity(AssigneeType::Role, role: $role);
        $piActivity = $instance->activities()->firstOrFail();

        $this->postJson("/api/instances/{$code}/activities/{$piActivity->id}/complete?organization_id={$this->org->id}", [
            'completed_by' => ['name' => 'Pessoa Elegível', 'email' => 'eligivel@example.com'],
            'result' => ['ok' => true],
        ])->assertOk()->assertJsonPath('status', 'completed');

        $piActivity->refresh();
        $this->assertSame($eligibleUser->id, $piActivity->assigned_user_id);
        $this->assertNull($piActivity->completed_by_external_name);
        $this->assertFalse($piActivity->external_attribution_needs_review);
    }

    public function test_completing_a_human_activity_via_api_with_an_existing_but_ineligible_user_flags_for_review(): void
    {
        Notification::fake();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->org->id);
        $admin = User::factory()->create();
        $this->org->users()->attach($admin);
        $admin->assignRole(OrganizationRole::Admin->value);
        $registrar->setPermissionsTeamId(0);

        $role = Role::factory()->for($this->org)->create();
        $notLinked = User::factory()->create(['email' => 'nao-vinculado@example.com']);
        // $notLinked nunca é anexado a $role.

        ['instance' => $instance, 'code' => $code] = $this->startInstanceWithHumanActivity(AssigneeType::Role, role: $role);
        $piActivity = $instance->activities()->firstOrFail();

        $this->postJson("/api/instances/{$code}/activities/{$piActivity->id}/complete?organization_id={$this->org->id}", [
            'completed_by' => ['name' => 'Não Vinculado', 'email' => 'nao-vinculado@example.com'],
        ])->assertOk()->assertJsonPath('status', 'completed');

        $piActivity->refresh();
        $this->assertNull($piActivity->assigned_user_id);
        $this->assertSame('Não Vinculado', $piActivity->completed_by_external_name);
        $this->assertSame('nao-vinculado@example.com', $piActivity->completed_by_external_email);
        $this->assertTrue($piActivity->external_attribution_needs_review);
        $this->assertSame($notLinked->id, User::where('email', 'nao-vinculado@example.com')->sole()->id);

        Notification::assertSentTo($admin, ExternalAttributionNeedsReviewNotification::class);
    }

    /**
     * `WorkflowEngine::resolveAssignee` auto-atribui na ativação quando há exatamente um
     * elegível no papel — se quem completa de fato via API for outra pessoa, o
     * `assigned_user_id` antigo não pode sobreviver junto com a atribuição externa (senão a UI
     * mostraria o nome de quem nunca completou nada).
     */
    public function test_completing_via_api_clears_a_stale_engine_auto_assignment_when_the_actual_completer_differs(): void
    {
        Notification::fake();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->org->id);
        $admin = User::factory()->create();
        $this->org->users()->attach($admin);
        $admin->assignRole(OrganizationRole::Admin->value);
        $registrar->setPermissionsTeamId(0);

        $role = Role::factory()->for($this->org)->create();
        $onlyMember = User::factory()->create(['email' => 'unico-membro@example.com']);
        $role->users()->attach($onlyMember);

        ['instance' => $instance, 'code' => $code] = $this->startInstanceWithHumanActivity(AssigneeType::Role, role: $role);
        $piActivity = $instance->activities()->firstOrFail();

        // Único elegível no papel -> o motor já auto-atribuiu na ativação.
        $this->assertSame($onlyMember->id, $piActivity->assigned_user_id);

        $this->postJson("/api/instances/{$code}/activities/{$piActivity->id}/complete?organization_id={$this->org->id}", [
            'completed_by' => ['name' => 'Outra Pessoa', 'email' => 'outra-pessoa@example.com'],
        ])->assertOk()->assertJsonPath('status', 'completed');

        $piActivity->refresh();
        $this->assertNull($piActivity->assigned_user_id);
        $this->assertSame('Outra Pessoa', $piActivity->completed_by_external_name);
        $this->assertTrue($piActivity->external_attribution_needs_review);
    }

    public function test_completing_a_human_activity_via_api_with_a_brand_new_email_creates_the_user_and_flags_for_review(): void
    {
        Notification::fake();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->org->id);
        $admin = User::factory()->create();
        $this->org->users()->attach($admin);
        $admin->assignRole(OrganizationRole::Admin->value);
        $registrar->setPermissionsTeamId(0);

        $role = Role::factory()->for($this->org)->create();

        ['instance' => $instance, 'code' => $code] = $this->startInstanceWithHumanActivity(AssigneeType::Role, role: $role);
        $piActivity = $instance->activities()->firstOrFail();

        $this->assertNull(User::where('email', 'novo@example.com')->first());

        $this->postJson("/api/instances/{$code}/activities/{$piActivity->id}/complete?organization_id={$this->org->id}", [
            'completed_by' => ['name' => 'Pessoa Nova', 'email' => 'novo@example.com'],
        ])->assertOk()->assertJsonPath('status', 'completed');

        $newUser = User::where('email', 'novo@example.com')->sole();
        $this->assertSame('Pessoa Nova', $newUser->name);

        $piActivity->refresh();
        $this->assertNull($piActivity->assigned_user_id);
        $this->assertTrue($piActivity->external_attribution_needs_review);

        Notification::assertSentTo($admin, ExternalAttributionNeedsReviewNotification::class);
    }

    public function test_completing_an_external_assignee_activity_via_api_never_touches_a_user(): void
    {
        Notification::fake();

        ['instance' => $instance, 'code' => $code] = $this->startInstanceWithHumanActivity(AssigneeType::External);
        $piActivity = $instance->activities()->firstOrFail();

        $this->postJson("/api/instances/{$code}/activities/{$piActivity->id}/complete?organization_id={$this->org->id}", [
            'completed_by' => ['name' => 'Candidato Externo', 'email' => 'candidato@example.com'],
            'result' => ['decisao' => 'aceito'],
        ])->assertOk()->assertJsonPath('status', 'completed');

        $piActivity->refresh();
        $this->assertNull($piActivity->assigned_user_id);
        $this->assertSame('Candidato Externo', $piActivity->completed_by_external_name);
        $this->assertFalse($piActivity->external_attribution_needs_review);
        $this->assertNull(User::where('email', 'candidato@example.com')->first());

        Notification::assertNothingSent();
    }
}
