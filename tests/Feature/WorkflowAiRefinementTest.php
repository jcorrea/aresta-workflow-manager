<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\WorkflowVersionStatus;
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

class WorkflowAiRefinementTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/*';

    private function createAdminUserWithRole(): array
    {
        $org = Organization::factory()->create(['name' => 'Aresta Teste']);
        $user = User::factory()->create();
        $org->users()->attach($user);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($org->id);
        $user->assignRole(OrganizationRole::Admin->value);
        $registrar->setPermissionsTeamId(0);

        $role = Role::factory()->for($org)->create([
            'name' => 'Aprovador Financeiro',
            'active' => true,
        ]);

        return [$org, $user, $role];
    }

    private function createWorkflowWithDraft(Organization $org, User $user): array
    {
        $workflow = Workflow::factory()->for($org)->create([
            'name' => 'Processo de Compras',
            'slug' => 'processo-de-compras',
            'description' => 'Fluxo inicial de compras.',
            'created_by' => $user->id,
        ]);

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'status' => WorkflowVersionStatus::Draft,
            'created_by' => $user->id,
            'draft_lock_workflow_id' => $workflow->id,
        ]);

        $step = WorkflowStep::create([
            'workflow_version_id' => $version->id,
            'name' => 'Etapa Inicial',
            'position_x' => 40,
            'position_y' => 40,
            'width' => 320,
            'height' => 220,
            'sort_order' => 0,
        ]);

        $activity = WorkflowActivity::create([
            'workflow_step_id' => $step->id,
            'name' => 'Solicitar compra',
            'type' => 'task',
            'config' => [],
            'position_x' => 20,
            'position_y' => 48,
            'is_start' => true,
            'is_end' => true,
        ]);

        return [$workflow, $version, $step, $activity];
    }

    private function geminiResponse(array $payload)
    {
        return Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => json_encode($payload)]]]],
            ],
        ]);
    }

    public function test_refine_ai_updates_draft_workflow_version_graph(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        [$org, $user, $role] = $this->createAdminUserWithRole();
        [$workflow, $version] = $this->createWorkflowWithDraft($org, $user);

        Http::fake([
            self::GEMINI_URL => $this->geminiResponse([
                'is_workflow_description' => true,
                'steps' => [
                    [
                        'name' => 'Solicitação',
                        'activities' => [
                            [
                                'tmp_id' => 'a1',
                                'name' => 'Preencher formulário de compra',
                                'type' => 'form',
                                'is_start' => true,
                                'is_end' => false,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Aprovação',
                        'activities' => [
                            [
                                'tmp_id' => 'a2',
                                'name' => 'Aprovar compra',
                                'type' => 'task',
                                'assignee_type' => 'role',
                                'assignee_role_id' => $role->id,
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
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->post(
            route('workflows.versions.refine-ai', [$workflow, $version]),
            [
                'description' => 'Fluxo atualizado de compras corporativas.',
                'instructions' => 'Adicionar etapa de aprovação pelo Aprovador Financeiro.',
            ]
        );

        $response->assertRedirect(route('workflows.versions.edit', [$workflow, $version]));

        $workflow->refresh();
        $this->assertSame('Fluxo atualizado de compras corporativas.', $workflow->description);

        $this->assertSame(2, WorkflowStep::where('workflow_version_id', $version->id)->count());
        $this->assertSame(2, WorkflowActivity::whereHas('workflowStep', fn ($q) => $q->where('workflow_version_id', $version->id))->count());
        $this->assertSame(1, WorkflowTransition::where('workflow_version_id', $version->id)->count());

        $approvalActivity = WorkflowActivity::where('name', 'Aprovar compra')->firstOrFail();
        $this->assertSame('role', $approvalActivity->assignee_type->value);
        $this->assertSame($role->id, $approvalActivity->assignee_role_id);
    }

    public function test_refine_ai_creates_new_role_when_returned_by_ai(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        [$org, $user] = $this->createAdminUserWithRole();
        [$workflow, $version] = $this->createWorkflowWithDraft($org, $user);

        Http::fake([
            self::GEMINI_URL => $this->geminiResponse([
                'is_workflow_description' => true,
                'steps' => [
                    [
                        'name' => 'Auditoria',
                        'activities' => [
                            [
                                'tmp_id' => 'a1',
                                'name' => 'Verificar conformidade',
                                'type' => 'task',
                                'assignee_type' => 'role',
                                'assignee_role_name' => 'Equipe de Compliance',
                                'is_start' => true,
                                'is_end' => true,
                            ],
                        ],
                    ],
                ],
                'transitions' => [],
            ]),
        ]);

        $response = $this->actingAs($user)->post(
            route('workflows.versions.refine-ai', [$workflow, $version]),
            [
                'instructions' => 'Atribuir a verificação para a Equipe de Compliance.',
            ]
        );

        $response->assertRedirect(route('workflows.versions.edit', [$workflow, $version]));

        $newRole = Role::where('organization_id', $org->id)->where('name', 'Equipe de Compliance')->first();
        $this->assertNotNull($newRole);

        $auditActivity = WorkflowActivity::where('name', 'Verificar conformidade')->firstOrFail();
        $this->assertSame('role', $auditActivity->assignee_type->value);
        $this->assertSame($newRole->id, $auditActivity->assignee_role_id);
    }

    public function test_refine_ai_refuses_when_ai_returns_off_topic_instructions(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        [$org, $user] = $this->createAdminUserWithRole();
        [$workflow, $version, $initialStep, $initialActivity] = $this->createWorkflowWithDraft($org, $user);

        Http::fake([
            self::GEMINI_URL => $this->geminiResponse([
                'is_workflow_description' => false,
                'refusal_reason' => 'As instruções fornecidas não se aplicam a um processo de trabalho.',
            ]),
        ]);

        $response = $this->actingAs($user)->post(
            route('workflows.versions.refine-ai', [$workflow, $version]),
            [
                'instructions' => 'Qual é a capital da França?',
            ]
        );

        $response->assertSessionHasErrors('instructions');

        // Garante atamicidade: o grafo original continua intacto
        $this->assertSame(1, WorkflowStep::where('workflow_version_id', $version->id)->count());
        $this->assertSame(1, WorkflowActivity::whereHas('workflowStep', fn ($q) => $q->where('workflow_version_id', $version->id))->count());
    }
}
