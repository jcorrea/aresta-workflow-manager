<?php

namespace Tests\Feature\Auth;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * `AppSettings` (docs/specs/05-identidade-visual.md §2/§7.2, decisão revista em 2026-08-07) é
 * config de instância inteira, não organizacional — mesmo critério de acesso de
 * `OrganizationPolicy` (só `platform-staff`), testado aqui com o mesmo padrão de
 * `AdminPanelAccessTest`.
 */
class AppSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_staff_can_access_the_settings_page(): void
    {
        Role::create(['name' => 'platform-staff', 'organization_id' => 0]);
        $user = User::factory()->create();
        $user->assignRole('platform-staff');

        $response = $this->actingAs($user)->get('/admin/app-settings');

        $response->assertOk();
    }

    public function test_organization_member_cannot_access_the_settings_page(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user);

        $response = $this->actingAs($user)->get('/admin/app-settings');

        $response->assertForbidden();
    }

    public function test_saved_branding_is_shared_with_inertia_pages(): void
    {
        AppSetting::current()->update([
            'app_name' => 'Cliente X',
            'primary_color' => '#FF0000',
        ]);

        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user);

        $response = $this->actingAs($user)->get('/');

        $response->assertInertia(fn ($page) => $page
            ->where('branding.app_name', 'Cliente X'));
    }

    public function test_current_creates_the_singleton_row_only_once(): void
    {
        $first = AppSetting::current();
        $second = AppSetting::current();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AppSetting::query()->count());
    }
}
