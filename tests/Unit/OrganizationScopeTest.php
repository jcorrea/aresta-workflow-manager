<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\ScopedFixture;
use Tests\TestCase;

/**
 * Cobre 01-modelo-de-dados.md §5.3: um usuário nunca deve enxergar/acessar um registro
 * organizacional de outra organização, mesmo por engano. `platform-staff` atravessa essa
 * fronteira para suporte/operação.
 */
class OrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('scoped_fixtures', function ($table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function test_user_only_sees_records_of_their_own_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        ScopedFixture::create(['organization_id' => $orgA->id, 'name' => 'da organização A']);
        ScopedFixture::create(['organization_id' => $orgB->id, 'name' => 'da organização B']);

        $userA = User::factory()->create();
        $orgA->users()->attach($userA);

        $this->actingAs($userA);

        $this->assertSame(1, ScopedFixture::count());
        $this->assertSame('da organização A', ScopedFixture::first()->name);
    }

    public function test_user_cannot_fetch_a_record_from_another_organization_by_id(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $recordB = ScopedFixture::create(['organization_id' => $orgB->id, 'name' => 'da organização B']);

        $userA = User::factory()->create();
        $orgA->users()->attach($userA);

        $this->actingAs($userA);

        $this->assertNull(ScopedFixture::find($recordB->id));
    }

    public function test_platform_staff_sees_records_of_every_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        ScopedFixture::create(['organization_id' => $orgA->id, 'name' => 'da organização A']);
        ScopedFixture::create(['organization_id' => $orgB->id, 'name' => 'da organização B']);

        Role::create(['name' => 'platform-staff', 'organization_id' => 0]);
        $staff = User::factory()->create();
        $staff->assignRole('platform-staff');

        $this->actingAs($staff);

        $this->assertSame(2, ScopedFixture::count());
    }

    public function test_guest_sees_no_records(): void
    {
        $org = Organization::factory()->create();
        ScopedFixture::create(['organization_id' => $org->id, 'name' => 'qualquer']);

        $this->assertSame(0, ScopedFixture::count());
    }
}
