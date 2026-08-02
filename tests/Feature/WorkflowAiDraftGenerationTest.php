<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowAiDraftGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const OLLAMA_URL = 'http://test-ollama:11434/*';

    private const AZURE_URL = 'https://test-resource.openai.azure.com/*';

    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/*';

    private const OPENROUTER_URL = 'https://openrouter.ai/*';

    private function configureOllama(): void
    {
        config([
            'services.ollama.url' => 'http://test-ollama:11434',
            'services.ollama.model' => 'llama3.2:1b',
        ]);
    }

    private function configureAzure(): void
    {
        config([
            'services.azure_openai.endpoint' => 'https://test-resource.openai.azure.com',
            'services.azure_openai.key' => 'test-azure-key',
            'services.azure_openai.deployment' => 'gpt-4o-mini',
        ]);
    }

    public function test_store_with_description_generates_draft_graph_from_ai(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        [$org, $user, $role] = $this->createAdminUserWithRole();

        Http::fake([
            self::GEMINI_URL => $this->geminiResponse($this->validAiPayload($role->id)),
        ]);

        $response = $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Reembolso de despesas',
            'description' => 'Processo de aprovação de reembolso de despesas, do envio do recibo até o pagamento.',
        ]);

        $workflow = Workflow::where('name', 'Reembolso de despesas')->firstOrFail();
        $version = WorkflowVersion::where('workflow_id', $workflow->id)->firstOrFail();

        $response->assertRedirect(route('workflows.versions.edit', [$workflow, $version]));
        $this->assertSame(2, WorkflowStep::where('workflow_version_id', $version->id)->count());
        $this->assertSame(2, WorkflowActivity::whereHas('workflowStep', fn ($q) => $q->where('workflow_version_id', $version->id))->count());
        $this->assertSame(1, WorkflowTransition::where('workflow_version_id', $version->id)->count());

        $taskActivity = WorkflowActivity::where('name', 'Enviar recibo')->firstOrFail();
        $this->assertSame('role', $taskActivity->assignee_type->value);
        $this->assertSame($role->id, $taskActivity->assignee_role_id);
    }

    public function test_store_with_off_topic_description_creates_nothing_and_returns_validation_error(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        [$org, $user] = $this->createAdminUserWithRole();

        Http::fake([
            self::GEMINI_URL => $this->geminiResponse([
                'is_workflow_description' => false,
                'refusal_reason' => 'Isso não descreve um processo de trabalho.',
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Piada',
            'description' => 'Me conte uma piada engraçada.',
        ]);

        $response->assertSessionHasErrors('description');
        $this->assertSame(0, Workflow::count());
        $this->assertSame(0, WorkflowVersion::count());
    }

    public function test_store_falls_back_to_openrouter_when_gemini_fails(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        config(['services.openrouter.key' => 'test-openrouter-key']);
        [$org, $user, $role] = $this->createAdminUserWithRole();

        Http::fake([
            self::GEMINI_URL => Http::response('erro interno', 500),
            self::OPENROUTER_URL => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($this->validAiPayload($role->id))]],
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Reembolso de despesas',
            'description' => 'Processo de aprovação de reembolso de despesas, do envio do recibo até o pagamento.',
        ]);

        $workflow = Workflow::where('name', 'Reembolso de despesas')->firstOrFail();
        $response->assertRedirect(route('workflows.versions.edit', [$workflow, WorkflowVersion::where('workflow_id', $workflow->id)->firstOrFail()]));

        Http::assertSentCount(2);
    }

    public function test_store_prefers_ollama_when_configured(): void
    {
        $this->configureOllama();
        $this->configureAzure();
        [$org, $user, $role] = $this->createAdminUserWithRole();

        Http::fake([
            self::OLLAMA_URL => Http::response([
                'message' => ['content' => json_encode($this->validAiPayload($role->id))],
            ]),
            self::AZURE_URL => Http::response('não deveria ser chamado', 500),
        ]);

        $response = $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Reembolso de despesas',
            'description' => 'Processo de aprovação de reembolso de despesas, do envio do recibo até o pagamento.',
        ]);

        $workflow = Workflow::where('name', 'Reembolso de despesas')->firstOrFail();
        $response->assertRedirect(route('workflows.versions.edit', [$workflow, WorkflowVersion::where('workflow_id', $workflow->id)->firstOrFail()]));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://test-ollama:11434'));
    }

    public function test_store_prefers_azure_openai_when_configured(): void
    {
        $this->configureAzure();
        config(['services.gemini.key' => 'test-gemini-key']);
        [$org, $user, $role] = $this->createAdminUserWithRole();

        Http::fake([
            self::AZURE_URL => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($this->validAiPayload($role->id))]],
                ],
            ]),
            self::GEMINI_URL => Http::response('não deveria ser chamado', 500),
        ]);

        $response = $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Reembolso de despesas',
            'description' => 'Processo de aprovação de reembolso de despesas, do envio do recibo até o pagamento.',
        ]);

        $workflow = Workflow::where('name', 'Reembolso de despesas')->firstOrFail();
        $response->assertRedirect(route('workflows.versions.edit', [$workflow, WorkflowVersion::where('workflow_id', $workflow->id)->firstOrFail()]));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://test-resource.openai.azure.com'));
    }

    public function test_store_falls_back_from_azure_through_gemini_to_openrouter(): void
    {
        $this->configureAzure();
        config(['services.gemini.key' => 'test-gemini-key']);
        config(['services.openrouter.key' => 'test-openrouter-key']);
        [$org, $user, $role] = $this->createAdminUserWithRole();

        Http::fake([
            self::AZURE_URL => Http::response('erro interno', 500),
            self::GEMINI_URL => Http::response('erro interno', 500),
            self::OPENROUTER_URL => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($this->validAiPayload($role->id))]],
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Reembolso de despesas',
            'description' => 'Processo de aprovação de reembolso de despesas, do envio do recibo até o pagamento.',
        ]);

        $workflow = Workflow::where('name', 'Reembolso de despesas')->firstOrFail();
        $response->assertRedirect(route('workflows.versions.edit', [$workflow, WorkflowVersion::where('workflow_id', $workflow->id)->firstOrFail()]));

        Http::assertSentCount(3);
    }

    public function test_transition_with_unknown_tmp_id_is_dropped_but_rest_of_draft_is_kept(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        [$org, $user, $role] = $this->createAdminUserWithRole();

        // Reproduz o que o qwen2.5:1.5b devolveu na prática: acerta etapas/atividades, mas uma
        // transição referencia um tmp_id que não existe em nenhuma atividade (confundiu o nome
        // de uma etapa com um tmp_id de atividade).
        $payload = $this->validAiPayload($role->id);
        $payload['transitions'][] = [
            'from_tmp_id' => 'a2',
            'to_tmp_id' => 'tmp_id_inexistente',
            'condition_type' => 'always',
            'condition_expression' => null,
            'label' => null,
        ];

        Http::fake([
            self::GEMINI_URL => $this->geminiResponse($payload),
        ]);

        $response = $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Reembolso de despesas',
            'description' => 'Processo de aprovação de reembolso de despesas, do envio do recibo até o pagamento.',
        ]);

        $workflow = Workflow::where('name', 'Reembolso de despesas')->firstOrFail();
        $version = WorkflowVersion::where('workflow_id', $workflow->id)->firstOrFail();
        $response->assertRedirect(route('workflows.versions.edit', [$workflow, $version]));

        // As duas atividades e a transição válida sobrevivem; só a transição quebrada some.
        $this->assertSame(2, WorkflowActivity::whereHas('workflowStep', fn ($q) => $q->where('workflow_version_id', $version->id))->count());
        $this->assertSame(1, WorkflowTransition::where('workflow_version_id', $version->id)->count());
    }

    public function test_store_without_description_keeps_existing_empty_draft_behavior(): void
    {
        [$org, $user] = $this->createAdminUserWithRole();

        Http::fake();

        $response = $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Processo vazio',
        ]);

        $workflow = Workflow::where('name', 'Processo vazio')->firstOrFail();
        $response->assertRedirect(route('workflows.versions.edit', [$workflow, WorkflowVersion::where('workflow_id', $workflow->id)->firstOrFail()]));
        $this->assertSame(0, WorkflowStep::count());
        Http::assertNothingSent();
    }

    public function test_store_rejects_malformed_ai_response(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        [$org, $user] = $this->createAdminUserWithRole();

        Http::fake([
            self::GEMINI_URL => $this->geminiResponse([
                'is_workflow_description' => true,
                // "steps" ausente de propósito
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Processo quebrado',
            'description' => 'Processo de aprovação de reembolso de despesas.',
        ]);

        $response->assertSessionHasErrors('description');
        $this->assertSame(0, Workflow::count());
    }

    public function test_ai_hallucinated_role_id_is_ignored(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        [$org, $user] = $this->createAdminUserWithRole();

        $payload = $this->validAiPayload(role: null);
        $payload['steps'][0]['activities'][0]['assignee_type'] = 'role';
        $payload['steps'][0]['activities'][0]['assignee_role_id'] = 999999; // não existe na organização

        Http::fake([
            self::GEMINI_URL => $this->geminiResponse($payload),
        ]);

        $this->actingAs($user)->post(route('workflows.store'), [
            'organization_id' => $org->id,
            'name' => 'Reembolso de despesas',
            'description' => 'Processo de aprovação de reembolso de despesas, do envio do recibo até o pagamento.',
        ]);

        $activity = WorkflowActivity::where('name', 'Enviar recibo')->firstOrFail();
        $this->assertNull($activity->assignee_type);
        $this->assertNull($activity->assignee_role_id);
    }

    /**
     * @return array{0: Organization, 1: User, 2: Role}
     */
    private function createAdminUserWithRole(): array
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($org->id);
        $user->assignRole(OrganizationRole::Admin->value);
        $registrar->setPermissionsTeamId(0);

        $role = Role::factory()->for($org)->create(['name' => 'Financeiro']);

        return [$org, $user, $role];
    }

    /**
     * @return array<string, mixed>
     */
    private function validAiPayload(?int $role): array
    {
        return [
            'is_workflow_description' => true,
            'steps' => [
                [
                    'name' => 'Envio',
                    'activities' => [
                        [
                            'tmp_id' => 'a1',
                            'name' => 'Enviar recibo',
                            'type' => 'task',
                            'assignee_type' => $role ? 'role' : null,
                            'assignee_role_id' => $role,
                            'config' => ['instructions' => 'Anexar o recibo da despesa.'],
                            'sla_hours' => 24,
                            'is_start' => true,
                            'is_end' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'Pagamento',
                    'activities' => [
                        [
                            'tmp_id' => 'a2',
                            'name' => 'Pagar reembolso',
                            'type' => 'automated_action',
                            'assignee_type' => null,
                            'assignee_role_id' => null,
                            'config' => ['action' => 'send_email'],
                            'sla_hours' => null,
                            'is_start' => false,
                            'is_end' => true,
                        ],
                    ],
                ],
            ],
            'transitions' => [
                [
                    'from_tmp_id' => 'a1',
                    'to_tmp_id' => 'a2',
                    'condition_type' => 'always',
                    'condition_expression' => null,
                    'label' => null,
                ],
            ],
        ];
    }

    private function geminiResponse(array $payload)
    {
        return Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode($payload)]]]],
            ],
        ]);
    }
}
