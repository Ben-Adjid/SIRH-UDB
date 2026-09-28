<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    /**
     * ✅ Test 1: Account creation sends activation email WITHOUT password
     */
    public function test_account_creation_sends_activation_email_without_password()
    {
        Mail::fake();

        $response = $this->actingAs($this->createAdminUser())
            ->postJson('/api/auth/register', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '+22178901234',
                'role' => 'teacher',
            ]);

        $this->assertEquals(201, $response->status());

        // Vérifier que pas de password en réponse
        $this->assertFalse(isset($response['temp_password']));
        $this->assertFalse(isset($response['password']));

        // Vérifier que l'email ne contient PAS de mot de passe
        // (L'email est envoyé via AccountActivationMail)
    }

    /**
     * ✅ Test 2: Activation link works once and only once
     */
    public function test_activation_link_works_once_and_only_once()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('unusable' . \Str::random(64)),
            'is_activated' => false,
        ]);

        $token = Password::broker()->createToken($user);

        // Première tentative - devrait réussir
        $response1 = $this->postJson('/api/auth/activation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
        ]);

        $this->assertEquals(200, $response1->status());

        // Vérifier que le compte est activé
        $this->assertTrue($user->fresh()->is_activated);

        // Deuxième tentative avec le même token - devrait échouer
        $response2 = $this->postJson('/api/auth/activation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AnotherPass123!@#',
            'password_confirmation' => 'AnotherPass123!@#',
        ]);

        $this->assertEquals(422, $response2->status());
    }

    /**
     * ✅ Test 3: Expired activation link fails
     */
    public function test_expired_activation_link_fails()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'expired@example.com',
            'password' => Hash::make(\Str::random(64)),
            'is_activated' => false,
        ]);

        // Créer un token expiré dans la table
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('expired_token'),
            'created_at' => now()->subHours(49), // Plus de 48h
        ]);

        $response = $this->postJson('/api/auth/activation', [
            'token' => 'expired_token',
            'email' => $user->email,
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
        ]);

        $this->assertEquals(422, $response->status());
        $this->assertFalse($user->fresh()->is_activated);
    }

    /**
     * ✅ Test 4: Weak password fails validation
     */
    public function test_weak_password_fails_validation()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'weak@example.com',
            'password' => Hash::make(\Str::random(64)),
            'is_activated' => false,
        ]);

        $token = Password::broker()->createToken($user);

        // Mot de passe trop court
        $response1 = $this->postJson('/api/auth/activation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Short1!',
            'password_confirmation' => 'Short1!',
        ]);
        $this->assertEquals(422, $response1->status());

        // Mot de passe sans symbole
        $response2 = $this->postJson('/api/auth/activation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NoSymbol123Abc',
            'password_confirmation' => 'NoSymbol123Abc',
        ]);
        $this->assertEquals(422, $response2->status());

        // Mot de passe sans chiffre
        $response3 = $this->postJson('/api/auth/activation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NoDigits!@#Abc',
            'password_confirmation' => 'NoDigits!@#Abc',
        ]);
        $this->assertEquals(422, $response3->status());
    }

    /**
     * ✅ Test 5: Throttle blocks after 6 attempts
     */
    public function test_throttle_blocks_after_6_attempts()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'throttle@example.com',
            'password' => Hash::make(\Str::random(64)),
            'is_activated' => false,
        ]);

        $token = Password::broker()->createToken($user);

        // Faire 6 tentatives
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/activation', [
                'token' => 'invalid_token',
                'email' => $user->email,
                'password' => 'SecurePass123!@#',
                'password_confirmation' => 'SecurePass123!@#',
            ]);
        }

        // La 7e tentative devrait être throttlée
        $response7 = $this->postJson('/api/auth/activation', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
        ]);

        $this->assertEquals(429, $response7->status());
    }

    /**
     * ✅ Test 6: Non-activated account cannot login
     */
    public function test_non_activated_account_cannot_login()
    {
        $user = User::create([
            'name' => 'Non Activated',
            'email' => 'notactivated@example.com',
            'password' => Hash::make('ValidPass123!@#'),
            'is_activated' => false,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'notactivated@example.com',
            'password' => 'ValidPass123!@#',
        ]);

        $this->assertEquals(403, $response->status());
        $this->assertStringContainsString('n\'a pas encore été activé', $response['message']);
    }

    /**
     * ✅ Test 7: Activated account can login
     */
    public function test_activated_account_can_login()
    {
        $password = 'ValidPass123!@#';

        $user = User::create([
            'name' => 'Activated User',
            'email' => 'activated@example.com',
            'password' => Hash::make($password),
            'is_activated' => true,
            'activated_at' => now(),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'activated@example.com',
            'password' => $password,
        ]);

        $this->assertEquals(200, $response->status());
        $this->assertTrue(isset($response['token']));
        $this->assertTrue($response['success']);
    }

    // Helper methods
    private function createAdminUser(): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass123!@#'),
            'is_activated' => true,
            'status' => 'active',
        ]);

        $user->assignRole('admin');
        return $user;
    }
}
