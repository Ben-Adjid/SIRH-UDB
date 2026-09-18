<?php

namespace App\Services;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;

class UserCreationService
{
    public function createUser(array $data, string $role, ?array $teacherData = null): array
    {
        $tempPassword = 'UDB' . random_int(1000, 9999);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'password' => Hash::make($tempPassword),
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $user->assignRole($role);

        $pinCode = null;
        if ($role === 'teacher') {
            $ufr = Ufr::first();
            if (!$ufr) {
                $user->delete();
                throw new \RuntimeException('Aucune UFR disponible.');
            }

            $pinCode = $this->generateUniquePinCode();

            Teacher::create(array_merge([
                'user_id' => $user->id,
                'ufr_id' => $teacherData['ufr_id'] ?? $ufr->id,
                'employee_id' => $teacherData['employee_id'] ?? ('ENS-' . str_pad($user->id, 5, '0', STR_PAD_LEFT) . '-' . now()->format('ymd')),
                'pin_code' => $pinCode,
                'hire_date' => $teacherData['hire_date'] ?? now(),
                'volume_hours' => $teacherData['volume_hours'] ?? 0,
                'hourly_rate' => $teacherData['hourly_rate'] ?? 0,
                'status' => 'active',
            ], $teacherData ?? []));
        }

        $this->sendNotifications($user, $data, $tempPassword, $pinCode);

        $this->auditLog($user, $data, $role);

        return ['user' => $user->fresh()->load('roles', 'teacher'), 'temp_password' => $tempPassword];
    }

    private function generateUniquePinCode(): string
    {
        do {
            $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Teacher::where('pin_code', $pin)->exists());

        return $pin;
    }

    private function sendNotifications(User $user, array $data, string $tempPassword, ?string $pinCode = null): void
    {
        $phone = $data['phone'] ?? null;
        if ($phone) {
            if (!str_starts_with($phone, '+')) {
                $phone = '+221' . ltrim($phone, '0');
            }
            $smsService = app(SmsServiceInterface::class);
            try {
                $smsService->sendWelcomePassword($phone, $user->name, $tempPassword, $pinCode);
            } catch (\Exception $e) {
                \Log::error('Erreur SMS bienvenue: ' . $e->getMessage());
            }
        }

        try {
            app(BrevoMailService::class)->sendWelcomeEmail($user, $tempPassword, $pinCode);
        } catch (\Exception $e) {
            \Log::error('Erreur email bienvenue: ' . $e->getMessage());
        }
    }

    private function auditLog(User $user, array $data, string $role): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'user_created',
            'model' => 'User',
            'model_id' => $user->id,
            'new_values' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $role,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
