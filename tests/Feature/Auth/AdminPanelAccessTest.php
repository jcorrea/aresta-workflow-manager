<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_any_organization_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_user_with_an_organization_can_access_the_admin_panel(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_platform_staff_accesses_the_admin_panel_even_without_an_organization(): void
    {
        Role::create(['name' => 'platform-staff', 'organization_id' => 0]);
        $user = User::factory()->create();
        $user->assignRole('platform-staff');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }
}
