<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CompleteSeeder::class);
        $this->user = User::where('email', 'admin@sirh.local')->first();
    }

    public function test_send_creates_notification()
    {
        $notification = NotificationService::send(
            $this->user->id,
            'Titre de test',
            'Message de test',
            'info',
            '/dashboard',
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Titre de test',
            'message' => 'Message de test',
            'type' => 'info',
            'link' => '/dashboard',
        ]);
    }

    public function test_send_with_default_values()
    {
        $notification = NotificationService::send(
            $this->user->id,
            'Notification par défaut',
            'Message par défaut',
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals('info', $notification->type);
        $this->assertNull($notification->link);
    }

    public function test_send_warning_notification()
    {
        $notification = NotificationService::send(
            $this->user->id,
            'Alerte retard',
            'Vous êtes en retard',
            'warning',
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Alerte retard',
            'type' => 'warning',
        ]);
    }

    public function test_send_success_notification()
    {
        $notification = NotificationService::send(
            $this->user->id,
            'Succès',
            'Opération réussie',
            'success',
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Succès',
            'type' => 'success',
        ]);
    }
}
