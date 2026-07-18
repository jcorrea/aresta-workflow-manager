<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class AzureLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_creates_a_new_user_and_logs_in(): void
    {
        $azureUser = (new SocialiteUser)->map([
            'id' => 'azure-999',
            'name' => 'Novo Colaborador',
            'email' => 'novo.colaborador@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        Socialite::shouldReceive('driver->user')->once()->andReturn($azureUser);

        $response = $this->get(route('azure.callback'));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $user = User::where('email', 'novo.colaborador@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('azure-999', $user->azure_id);
    }

    public function test_callback_matches_a_pre_provisioned_user_by_email(): void
    {
        $preProvisioned = User::factory()->create([
            'email' => 'existente@example.com',
            'azure_id' => null,
        ]);

        $azureUser = (new SocialiteUser)->map([
            'id' => 'azure-123',
            'name' => 'Existente',
            'email' => 'existente@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        Socialite::shouldReceive('driver->user')->once()->andReturn($azureUser);

        $response = $this->get(route('azure.callback'));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($preProvisioned->fresh());
        $this->assertSame('azure-123', $preProvisioned->fresh()->azure_id);
        $this->assertSame(1, User::count());
    }

    public function test_callback_matches_recurring_login_by_azure_id_even_if_email_changed(): void
    {
        $existing = User::factory()->create([
            'email' => 'antigo@example.com',
            'azure_id' => 'azure-777',
        ]);

        $azureUser = (new SocialiteUser)->map([
            'id' => 'azure-777',
            'name' => 'Existente',
            'email' => 'novo-email@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        Socialite::shouldReceive('driver->user')->once()->andReturn($azureUser);

        $this->get(route('azure.callback'));

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame('novo-email@example.com', $existing->fresh()->email);
        $this->assertSame(1, User::count());
    }
}
