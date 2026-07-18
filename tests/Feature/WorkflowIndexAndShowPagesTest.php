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

class WorkflowIndexAndShowPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_lists_workflows_of_the_users_organizations(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);
        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create(['name' => 'Aprovação']);
        WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->create();

        $this->actingAs($user)
            ->get(route('workflows.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Workflows/Index')
                ->where('workflows.0.name', 'Aprovação')
                ->where('workflows.0.hasPublishedVersion', false)
            );
    }

    public function test_show_page_lists_published_version_history(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($org->id);
        $user->assignRole(OrganizationRole::Admin->value);
        $registrar->setPermissionsTeamId(0);

        $workflow = Workflow::factory()->for($org)->for($user, 'createdBy')->create();
        $version = WorkflowVersion::factory()->for($workflow)->for($user, 'createdBy')->published()->create(['version_number' => 1]);
        $workflow->update(['current_published_version_id' => $version->id]);

        $this->actingAs($user)
            ->get(route('workflows.show', $workflow))
            ->assertInertia(fn ($page) => $page
                ->component('Workflows/Show')
                ->where('workflow.currentPublishedVersionId', $version->id)
                ->where('publishedVersions.0.version_number', 1)
            );
    }
}
