<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\ProcessInstanceStatus;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Iniciar instância de teste pela UI logada (sem depender de tinker/API Sanctum externa) —
 * mesmo motor (`WorkflowEngine::start`) usado pela API externa.
 */
class ProcessInstanceStartTest extends TestCase
{
    use RefreshDatabase;

    private function actingEditor(Organization $org): User
    {
        $user = User::factory()->create();
        $org->users()->attach($user);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($org->id);
        $user->assignRole(OrganizationRole::Editor->value);
        $registrar->setPermissionsTeamId(0);

        return $user;
    }

    public function test_editor_can_start_an_instance_from_the_published_version(): void
    {
        $org = Organization::factory()->create();
        $user = $this->actingEditor($org);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->published()->create();
        $workflow->update(['current_published_version_id' => $version->id]);

        $response = $this->actingAs($user)->post(route('process-instances.store', $workflow->id), [
            'name' => 'Instância de teste',
            'context' => '{"valor": 500}',
        ]);

        $instance = ProcessInstance::query()->where('workflow_version_id', $version->id)->firstOrFail();

        $response->assertRedirect(route('process-instances.show', $instance->code));
        $this->assertSame('Instância de teste', $instance->name);
        $this->assertSame(['valor' => 500], $instance->context);
        $this->assertSame($user->id, $instance->started_by);
        $this->assertSame(ProcessInstanceStatus::Running, $instance->status);
    }

    public function test_viewer_cannot_start_an_instance(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($org->id);
        $user->assignRole(OrganizationRole::Viewer->value);
        $registrar->setPermissionsTeamId(0);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->published()->create();
        $workflow->update(['current_published_version_id' => $version->id]);

        $this->actingAs($user)
            ->post(route('process-instances.store', $workflow->id), ['name' => 'Não deveria existir'])
            ->assertForbidden();

        $this->assertDatabaseCount('process_instances', 0);
    }

    public function test_cannot_start_an_instance_without_a_published_version(): void
    {
        $org = Organization::factory()->create();
        $user = $this->actingEditor($org);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();

        $this->actingAs($user)
            ->post(route('process-instances.store', $workflow->id), ['name' => 'Sem versão'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('process_instances', 0);
    }

    public function test_invalid_context_json_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $user = $this->actingEditor($org);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->published()->create();
        $workflow->update(['current_published_version_id' => $version->id]);

        $this->actingAs($user)
            ->post(route('process-instances.store', $workflow->id), ['context' => 'não é json'])
            ->assertSessionHasErrors('context');

        $this->assertDatabaseCount('process_instances', 0);
    }
}
