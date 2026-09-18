<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CompleteSeeder::class);
    }

    public function test_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@sirh.local',
            'password' => 'Changez-Moi-2026!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_login_fails_with_wrong_password()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@sirh.local',
            'password' => 'mauvais-mot-de-passe',
        ]);

        $response->assertStatus(401);
    }

    public function test_get_authenticated_user_info()
    {
        $admin = User::where('email', 'admin@sirh.local')->first();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@sirh.local')
            ->assertJsonPath('user.name', 'Administrateur Système');
    }

    public function test_logout()
    {
        $admin = User::where('email', 'admin@sirh.local')->first();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout');

        $response->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $admin->id,
        ]);
    }

    public function test_rate_limiting_on_login()
    {
        $payload = [
            'email' => 'admin@sirh.local',
            'password' => 'mauvais-mot-de-passe',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $payload);
        }

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(429);
    }

    public function test_inactive_account_cannot_login()
    {
        $admin = User::where('email', 'admin@sirh.local')->first();
        $admin->update(['status' => 'inactive']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@sirh.local',
            'password' => 'Changez-Moi-2026!',
        ]);

        $response->assertStatus(403);
    }
}
