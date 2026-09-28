<?php

namespace App\Services;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserCreationService
{
    public function createUser(array $data, string $role, ?array $teacherData = null): array
    {
        // Créer un mot de passe aléatoire inutilisable (jamais communiqué à l'utilisateur)
        $unusablePassword = Hash::make(Str::random(64));

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'password' => $unusablePassword,
            'status' => 'active',
            'is_activated' => false, // ✅ NOUVEAU: Compte en attente d'activation
            'must_change_password' => false, // Pas de changement forcé au premier login
        ]);

        $user->assignRole($role);

        $pinCode = null;
        if ($role === 'teacher') {
            $ufr = Ufr::first();
            if (!$ufr) {
                $user->delete();
                throw new \RuntimeException('Aucune UFR disponible. Créez d\'abord une UFR avant de créer des enseignants.');
            }

            if (empty($teacherData['ufr_id'])) {
                $user->delete();
                throw new \RuntimeException('L\'UFR est obligatoire pour créer un enseignant.');
            }

            $pinCode = $this->generateUniquePinCode();
            $user->update(['attendance_pin' => $pinCode]);

            Teacher::create(array_merge([
                'user_id' => $user->id,
                'ufr_id' => $teacherData['ufr_id'],
                'employee_id' => $teacherData['employee_id'] ?? ('ENS-' . str_pad($user->id, 5, '0', STR_PAD_LEFT) . '-' . now()->format('ymd')),
                'pin_code' => $pinCode,
                'hire_date' => $teacherData['hire_date'] ?? now(),
                'volume_hours' => $teacherData['volume_hours'] ?? 0,
                'hourly_rate' => $teacherData['hourly_rate'] ?? 0,
                'status' => 'active',
            ], $teacherData ?? []));
        }

        $this->sendActivationNotifications($user, $data, $pinCode);

        $this->auditLog($user, $data, $role, 'account_created');

        // ✅ NE PAS RETOURNER DE MOT DE PASSE
        return ['user' => $user->fresh()->load('roles', 'teacher')];
    }

    private function generateUniquePinCode(): string
    {
        do {
            $pin = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while (Teacher::where('pin_code', $pin)->exists());

        return $pin;
    }

    private function sendActivationNotifications(User $user, array $data, ?string $pinCode = null): void
    {
        // Envoyer le lien d'activation par email
        try {
            $activationService = app(AccountActivationService::class);
            $activationService->sendActivationLink($user);

            $this->auditLog($user, $data, $user->roles->first()?->name, 'activation_link_sent');
        } catch (\Exception $e) {
            \Log::error('Erreur envoi lien activation: ' . $e->getMessage());
        }

        // SMS (optionnel): Message générique SANS mot de passe
        $phone = $data['phone'] ?? null;
        if ($phone) {
            if (!str_starts_with($phone, '+')) {
                $phone = '+221' . ltrim($phone, '0');
            }
            $smsService = app(SmsServiceInterface::class);
            try {
                $smsService->sendAccountCreatedNotification($phone, $user->name);
            } catch (\Exception $e) {
                \Log::error('Erreur SMS notification: ' . $e->getMessage());
            }
        }
    }

    private function auditLog(User $user, array $data, ?string $role = null, string $action = 'user_created'): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model' => 'User',
            'model_id' => $user->id,
            'new_values' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $role,
                'is_activated' => $user->is_activated,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
