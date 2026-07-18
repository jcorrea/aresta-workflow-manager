<?php

namespace Tests\Feature\Api;

use App\Enums\WorkflowActivityType;
use App\Models\ExternalSystem;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
        WorkflowActivity::factory()->for($step, 'workflowStep')->create(['is_start' => true, 'is_end' => true]);
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
            ->assertJsonPath('activities.0.status', 'pending');
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

    public function test_completing_a_task_activity_via_api_is_rejected(): void
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
}
