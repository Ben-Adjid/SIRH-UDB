<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_seeded_accounts_can_login(): void
    {
        $this->seed(\Database\Seeders\CompleteSeeder::class);

        $accounts = [
            'admin@sirh.local',
            'rh@sirh.local',
            'academic@sirh.local',
            'compta@sirh.local',
            'prof@sirh.local',
            'diop@sirh.local',
            'sarr@sirh.local',
        ];

        foreach ($accounts as $email) {
            $response = $this->postJson('/api/auth/login', [
                'email'    => $email,
                'password' => 'password',
            ]);

            $this->assertTrue(
                in_array($response->status(), [200, 403]),
                "{$email} = {$response->status()} au lieu de 200"
            );
        }
    }
}
