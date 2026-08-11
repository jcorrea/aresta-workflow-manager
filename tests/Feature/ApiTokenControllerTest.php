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
 * Token pessoal self-service (botão "Gerar meu token" em Workflows/IntegrationInstructions.vue)
 * — distinto do token de `ExternalSystem`. Precisa nascer escopado à organização do workflow
 * (ability `org:{id}`), senão qualquer usuário logado poderia usá-lo pra acessar dados de
 * outra organização trocando `organization_id` no request público
 * (ver ProcessInstanceApiTest::test_user_token_scoped_to_a_different_organization_cannot_reach_this_one).
 */
class ApiTokenControllerTest extends TestCase
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

    private function makeWorkflow(Organization $organization, User $creator): Workflow
    {
        $workflow = Workflow::factory()->for($organization)->for($creator, 'createdBy')->create(['slug' => 'aprovacao-de-compra']);
        $published = WorkflowVersion::factory()->for($workflow)->for($creator, 'createdBy')->published()->create(['version_number' => 1]);
        $workflow->update(['current_published_version_id' => $published->id]);

        return $workflow;
    }

    public function test_generates_a_token_scoped_to_the_workflow_organization(): void
    {
        $org = Organization::factory()->create();
        $viewer = $this->makeUserWithRole($org, OrganizationRole::Viewer->value);
        $workflow = $this->makeWorkflow($org, $viewer);

        $response = $this->actingAs($viewer)->postJson(route('workflows.api-token.store', $workflow));

        $response->assertOk()->assertJsonStructure(['token', 'expiresAt']);
        $this->assertStringContainsString('|', $response->json('token'));

        $this->forgetWebSession();

        // O token devolvido de fato funciona na API pública, só pra essa organização.
        $this->postJson("/api/workflows/{$workflow->slug}/instances", [
            'organization_id' => $org->id,
        ], ['Authorization' => 'Bearer '.$response->json('token')])->assertCreated();

        $otherOrg = Organization::factory()->create();
        $this->postJson("/api/workflows/{$workflow->slug}/instances", [
            'organization_id' => $otherOrg->id,
        ], ['Authorization' => 'Bearer '.$response->json('token')])->assertForbidden();
    }

    public function test_regenerating_revokes_the_previous_token(): void
    {
        $org = Organization::factory()->create();
        $viewer = $this->makeUserWithRole($org, OrganizationRole::Viewer->value);
        $workflow = $this->makeWorkflow($org, $viewer);

        $first = $this->actingAs($viewer)->postJson(route('workflows.api-token.store', $workflow))->json('token');
        $second = $this->actingAs($viewer)->postJson(route('workflows.api-token.store', $workflow))->json('token');

        $this->assertNotSame($first, $second);

        $this->forgetWebSession();

        $this->postJson("/api/workflows/{$workflow->slug}/instances", [
            'organization_id' => $org->id,
        ], ['Authorization' => 'Bearer '.$first])->assertUnauthorized();

        $this->postJson("/api/workflows/{$workflow->slug}/instances", [
            'organization_id' => $org->id,
        ], ['Authorization' => 'Bearer '.$second])->assertCreated();
    }

    /**
     * `Sanctum\Guard` confere o guard `web` (config/sanctum.php) antes do bearer token — sem
     * isso, o `actingAs()` (sessão) usado pra gerar o token continua "logado" pro cliente de
     * teste, e as chamadas seguintes autenticam pela sessão (ability sempre liberada via
     * `TransientToken`) em vez de pelo Bearer token de verdade que este teste quer exercitar.
     */
    private function forgetWebSession(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_user_without_access_to_the_workflow_cannot_generate_a_token(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $creatorA = $this->makeUserWithRole($orgA, OrganizationRole::Admin->value);
        $outsider = $this->makeUserWithRole($orgB, OrganizationRole::Admin->value);
        $workflow = $this->makeWorkflow($orgA, $creatorA);

        $this->actingAs($outsider)
            ->postJson(route('workflows.api-token.store', $workflow))
            ->assertNotFound();
    }

    public function test_guest_cannot_generate_a_token(): void
    {
        $org = Organization::factory()->create();
        $creator = $this->makeUserWithRole($org, OrganizationRole::Admin->value);
        $workflow = $this->makeWorkflow($org, $creator);

        // Rota vive em routes/web.php (não routes/api.php), então erro de auth renderiza como
        // redirect pra /login (bootstrap/app.php só força resposta JSON pra `api/*`) — igual a
        // qualquer outra ação chamada via axios nas telas Inertia deste app.
        $this->postJson(route('workflows.api-token.store', $workflow))->assertRedirect(route('login'));
    }
}
