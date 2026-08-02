<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\WorkflowVersionStatus;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\ProcessInstanceStep;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): array
    {
        $org = Organization::factory()->create(['name' => 'Aresta Teste']);
        $user = User::factory()->create();
        $org->users()->attach($user);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($org->id);
        $user->assignRole(OrganizationRole::Admin->value);
        $registrar->setPermissionsTeamId(0);

        return [$org, $user];
    }

    private function createWorkflowWithDraft(Organization $org, User $user): array
    {
        $workflow = Workflow::factory()->for($org)->create([
            'name' => 'Processo de Teste',
            'slug' => 'processo-de-teste',
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
            'name' => 'Etapa 1',
            'position_x' => 40,
            'position_y' => 40,
            'width' => 320,
            'height' => 220,
            'sort_order' => 0,
        ]);

        $activity = WorkflowActivity::create([
            'workflow_step_id' => $step->id,
            'name' => 'Atividade 1',
            'type' => 'task',
            'config' => [],
            'position_x' => 20,
            'position_y' => 48,
            'is_start' => true,
            'is_end' => true,
        ]);

        return [$workflow, $version, $step, $activity];
    }

    public function test_admin_can_delete_workflow_without_instances(): void
    {
        [$org, $user] = $this->createAdminUser();
        [$workflow, $version, $step, $activity] = $this->createWorkflowWithDraft($org, $user);

        $response = $this->actingAs($user)->delete(route('workflows.destroy', $workflow));

        $response->assertRedirect(route('workflows.index'));
        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
        $this->assertDatabaseMissing('workflow_versions', ['id' => $version->id]);
        $this->assertDatabaseMissing('workflow_steps', ['id' => $step->id]);
        $this->assertDatabaseMissing('workflow_activities', ['id' => $activity->id]);
    }

    public function test_delete_workflow_with_instances_requires_confirmation(): void
    {
        [$org, $user] = $this->createAdminUser();
        [$workflow, $version, $step, $activity] = $this->createWorkflowWithDraft($org, $user);

        $instance = ProcessInstance::factory()->for($org)->create([
            'workflow_version_id' => $version->id,
            'started_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('workflows.destroy', $workflow), [
            'confirm_cascade' => false,
        ]);

        $response->assertSessionHasErrors('confirm_cascade');
        $this->assertDatabaseHas('workflows', ['id' => $workflow->id]);
        $this->assertDatabaseHas('process_instances', ['id' => $instance->id]);
    }

    public function test_admin_can_delete_workflow_and_all_instance_history_when_confirmed(): void
    {
        [$org, $user] = $this->createAdminUser();
        [$workflow, $version, $step, $activity] = $this->createWorkflowWithDraft($org, $user);

        $instance = ProcessInstance::factory()->for($org)->create([
            'workflow_version_id' => $version->id,
            'started_by' => $user->id,
        ]);

        $instanceStep = ProcessInstanceStep::create([
            'process_instance_id' => $instance->id,
            'workflow_step_id' => $step->id,
            'name' => $step->name,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $instanceActivity = ProcessInstanceActivity::create([
            'process_instance_id' => $instance->id,
            'workflow_activity_id' => $activity->id,
            'process_instance_step_id' => $instanceStep->id,
            'name' => $activity->name,
            'type' => $activity->type,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->delete(route('workflows.destroy', $workflow), [
            'confirm_cascade' => true,
        ]);

        $response->assertRedirect(route('workflows.index'));
        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
        $this->assertDatabaseMissing('workflow_versions', ['id' => $version->id]);
        $this->assertDatabaseMissing('process_instances', ['id' => $instance->id]);
        $this->assertDatabaseMissing('process_instance_activities', ['id' => $instanceActivity->id]);
        $this->assertDatabaseMissing('process_instance_steps', ['id' => $instanceStep->id]);
    }
}
