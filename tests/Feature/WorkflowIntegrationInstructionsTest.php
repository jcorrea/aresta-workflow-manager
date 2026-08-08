<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tela de "instruções de integração (IA)" (botão em Workflows/Show.vue): expõe descrição +
 * grafo (toGraphPayload) da versão publicada, com fallback pro rascunho quando ainda não há
 * nenhuma publicada — e respeita o mesmo isolamento entre organizações das outras telas de
 * workflow (CLAUDE.md "Isolamento entre organizações é fronteira de acesso real").
 */
class WorkflowIntegrationInstructionsTest extends TestCase
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

    public function test_shows_description_and_graph_for_the_published_version(): void
    {
        $org = Organization::factory()->create();
        $viewer = $this->makeUserWithRole($org, OrganizationRole::Viewer->value);

        $workflow = Workflow::factory()->for($org)->for($viewer, 'createdBy')->create([
            'slug' => 'aprovacao-de-compra',
            'description' => 'Aprova pedidos de compra acima de um valor.',
        ]);
        $published = WorkflowVersion::factory()->for($workflow)->for($viewer, 'createdBy')->published()->create(['version_number' => 1]);
        $draft = WorkflowVersion::factory()->for($workflow)->for($viewer, 'createdBy')->create();
        $workflow->update(['current_published_version_id' => $published->id]);

        $this->actingAs($viewer)
            ->get(route('workflows.integration-instructions', $workflow))
            ->assertInertia(fn ($page) => $page
                ->component('Workflows/IntegrationInstructions')
                ->where('workflow.slug', 'aprovacao-de-compra')
                ->where('workflow.description', 'Aprova pedidos de compra acima de um valor.')
                ->where('workflow.organizationId', $org->id)
                ->where('version.id', $published->id)
                ->where('version.status', 'published')
                ->has('graph.nodes')
                ->has('graph.edges')
                ->where('apiBaseUrl', rtrim(config('app.url'), '/').'/api')
            );

        $this->assertNotSame($draft->id, $published->id);
    }

    public function test_falls_back_to_the_draft_graph_when_no_published_version_exists(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);

        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();
        $draft = WorkflowVersion::factory()->for($workflow)->for($admin, 'createdBy')->create();

        $this->actingAs($admin)
            ->get(route('workflows.integration-instructions', $workflow))
            ->assertInertia(fn ($page) => $page
                ->component('Workflows/IntegrationInstructions')
                ->where('version.id', $draft->id)
                ->where('version.status', 'draft')
            );
    }

    public function test_it_404s_when_the_workflow_has_no_version_at_all(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = Workflow::factory()->for($org)->for($admin, 'createdBy')->create();

        $this->actingAs($admin)
            ->get(route('workflows.integration-instructions', $workflow))
            ->assertNotFound();
    }

    public function test_user_from_another_organization_cannot_reach_it(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $adminA = $this->makeUserWithRole($orgA, OrganizationRole::Admin->value);
        $outsider = $this->makeUserWithRole($orgB, OrganizationRole::Admin->value);

        $workflow = Workflow::factory()->for($orgA)->for($adminA, 'createdBy')->create();
        WorkflowVersion::factory()->for($workflow)->for($adminA, 'createdBy')->published()->create(['version_number' => 1]);

        $this->actingAs($outsider)
            ->get(route('workflows.integration-instructions', $workflow))
            ->assertNotFound();
    }
}
