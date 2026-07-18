<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_global_platform_staff_role(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertTrue(
            Role::query()->where('name', 'platform-staff')->where('organization_id', 0)->exists()
        );
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(1, Role::query()->where('name', 'platform-staff')->count());
    }
}
