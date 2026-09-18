<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class LeaveController extends Controller {

    private const MANAGER_ROLES = ['admin', 'hr_manager', 'secretary'];
    private const ELIGIBLE_ROLES = ['admin', 'hr_manager', 'secretary', 'academic_manager', 'accountant'];

    private function canManageAll(User $user): bool
    {
        return $user->hasAnyRole(self::MANAGER_ROLES);
    }

    private function isEligible(User $user): bool
    {
        return !$user->hasRole('teacher') && $user->hasAnyRole(self::ELIGIBLE_ROLES);
    }
    
    public function index(Request $request): JsonResponse {
        try {
            $user = auth()->user();

            $query = LeaveRequest::with(['user.roles', 'teacher.user', 'leaveType', 'approvedBy']);

            if (!$this->canManageAll($user)) {
                $query->where('user_id', $user->id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($builder) use ($search) {
                    $builder->whereHas('user', fn($user) => $user->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('leaveType', fn($type) => $type->where('name', 'like', "%{$search}%"));
                });
            }

            if ($request->filled('user_id') && $this->canManageAll($user)) {
                $query->where('user_id', $request->user_id);
            }

            return response()->json($query->paginate($request->input('per_page', 20)));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LeaveIndex error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des congés'], 500);
        }
    }

    public function updateStatus(Request $request, LeaveRequest $leave): JsonResponse {
        try {
            $validated = $request->validate([
                'status' => 'required|in:approved,rejected',
                'rejection_reason' => 'required_if:status,rejected|string',
            ]);

            if ($leave->status !== 'pending') {
                return response()->json(['message' => 'Seules les demandes en attente peuvent être traitées'], 422);
            }

            return DB::transaction(function () use ($validated, $leave, $request) {
                $oldStatus = $leave->status;
                
                $leave->update([
                    'status' => $validated['status'],
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'rejection_reason' => $validated['rejection_reason'] ?? null,
                ]);

                if ($validated['status'] === 'approved' && $oldStatus === 'pending') {
                    $balance = LeaveBalance::where('user_id', $leave->user_id)
                        ->where('leave_type_id', $leave->leave_type_id)
                        ->where('year', Carbon::parse($leave->start_date)->year)
                        ->first();

                    if ($balance) {
                        $balance->decrement('pending_days', $leave->duration_days);
                        $balance->increment('used_days', $leave->duration_days);
                    }
                } elseif ($validated['status'] === 'rejected' && $oldStatus === 'pending') {
                    $balance = LeaveBalance::where('user_id', $leave->user_id)
                        ->where('leave_type_id', $leave->leave_type_id)
                        ->where('year', Carbon::parse($leave->start_date)->year)
                        ->first();

                    if ($balance) {
                        $balance->decrement('pending_days', $leave->duration_days);
                    }
                }

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'leave_' . $validated['status'],
                    'model' => 'LeaveRequest',
                    'model_id' => $leave->id,
                    'ip_address' => $request->ip(),
                ]);

                if ($leave->user) {
                    NotificationService::send(
                        $leave->user_id,
                        $validated['status'] === 'approved' ? 'Congé Approuvé' : 'Congé Rejeté',
                        "Votre demande de congé du " . $leave->start_date . " au " . $leave->end_date . " a été " . ($validated['status'] === 'approved' ? 'approuvée' : 'rejetée') . ".",
                        $validated['status'] === 'approved' ? 'success' : 'danger',
                        '/leaves'
                    );

                    try {
                        if ($leave->user) {
                            app(\App\Services\BrevoMailService::class)->sendLeaveStatusUpdate(
                                $leave->user,
                                $leave->toArray()
                            );
                        }
                    } catch (\Exception $e) {
                        \Log::error('Erreur email congé (Brevo API): ' . $e->getMessage());
                    }
                }

                return response()->json(['message' => 'Statut du congé mis à jour', 'leave' => $leave]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LeaveUpdateStatus error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour du statut'], 500);
        }
    }

    public function updateBalance(Request $request): JsonResponse {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'year' => 'required|integer',
                'total_days' => 'required|integer|min:0',
            ]);

            $targetUser = User::findOrFail($validated['user_id']);
            if (!$this->isEligible($targetUser)) {
                return response()->json(['message' => 'Un enseignant ne peut pas recevoir de solde de congés'], 422);
            }

            $existingBalance = LeaveBalance::where('user_id', $validated['user_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('year', $validated['year'])
                ->first();

            if ($existingBalance && $validated['total_days'] < ($existingBalance->used_days + $existingBalance->pending_days)) {
                return response()->json([
                    'message' => 'Le total ne peut pas être inférieur aux jours déjà utilisés (' . $existingBalance->used_days . ' utilisés, ' . $existingBalance->pending_days . ' en attente)',
                ], 422);
            }

            $balance = LeaveBalance::updateOrCreate(
                [
                    'user_id' => $validated['user_id'],
                    'leave_type_id' => $validated['leave_type_id'],
                    'year' => $validated['year'],
                ],
                ['total_days' => $validated['total_days']]
            );

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update_leave_balance',
                'model' => 'LeaveBalance',
                'model_id' => $balance->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['message' => 'Solde mis à jour', 'balance' => $balance]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LeaveUpdateBalance error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour du solde'], 500);
        }
    }

    public function getHistory(Request $request): JsonResponse {
        try {
            $user = auth()->user();

            // Tous les utilisateurs peuvent voir leur propre historique de congés, les managers peuvent voir tous
            $query = LeaveRequest::with(['leaveType', 'approvedBy', 'user']);

            if (!$this->canManageAll($user)) {
                // Les utilisateurs réguliers voient uniquement leurs propres demandes
                $query->where('user_id', $user->id);
            } elseif ($request->filled('user_id')) {
                // Les managers peuvent filtrer par utilisateur
                $query->where('user_id', $request->user_id);
            }

            return response()->json($query->orderBy('start_date', 'desc')->paginate($request->input('per_page', 20)));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LeaveGetHistory error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement de l\'historique'], 500);
        }
    }

    public function store(Request $request): JsonResponse {
        try {
            $user = auth()->user();

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'required|string',
            ]);

            $start = Carbon::parse($validated['start_date']);
            $end = Carbon::parse($validated['end_date']);

            $requestedUser = User::with('roles')->findOrFail($validated['user_id']);

            // Tout utilisateur peut créer une demande pour son propre compte, les managers peuvent en créer pour d'autres
            if (!$this->canManageAll($user) && $requestedUser->id !== $user->id) {
                return response()->json(['message' => 'Vous ne pouvez créer une demande de congé que pour votre propre compte'], 403);
            }

            if ($start->year !== $end->year) {
                return response()->json(['message' => 'Une demande de congé doit rester dans la même année'], 422);
            }
            $durationDays = 0;
            $current = $start->copy();
            while ($current->lte($end)) {
                if ($current->isWeekday()) {
                    $durationDays++;
                }
                $current->addDay();
            }

            if ($durationDays === 0) {
                return response()->json(['message' => 'La durée doit être d\'au moins un jour ouvré'], 422);
            }

            $balance = LeaveBalance::where('user_id', $validated['user_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('year', $start->year)
                ->first();

            if (!$balance) {
                return response()->json(['message' => 'Solde de congés non trouvé pour cet enseignant et ce type de congé'], 422);
            }

            $availableDays = $balance->total_days - $balance->used_days - $balance->pending_days;
            if ($durationDays > $availableDays) {
                return response()->json(['message' => "Solde insuffisant. Jours disponibles : {$availableDays}"], 422);
            }

            $overlap = LeaveRequest::where('user_id', $validated['user_id'])
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $validated['end_date'])
                ->whereDate('end_date', '>=', $validated['start_date'])
                ->exists();
            if ($overlap) {
                return response()->json(['message' => 'Une demande couvre déjà cette période'], 422);
            }

            $leave = DB::transaction(function () use ($validated, $durationDays, $balance, $user, $request) {
                $leave = LeaveRequest::create([
                    'user_id' => $validated['user_id'],
                    'leave_type_id' => $validated['leave_type_id'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'reason' => $validated['reason'],
                    'duration_days' => $durationDays,
                    'status' => 'pending',
                ]);

                $balance->increment('pending_days', $durationDays);

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'create_leave',
                    'model' => 'LeaveRequest',
                    'model_id' => $leave->id,
                    'ip_address' => $request->ip(),
                ]);

                return $leave;
            });

            NotificationService::send(
                $requestedUser->id,
                'Demande de congé enregistrée',
                "Votre demande du {$validated['start_date']} au {$validated['end_date']} est en attente de validation.",
                'info',
                '/leaves'
            );

            $admins = User::whereHas('roles', fn($q) => $q->whereIn('name', self::MANAGER_ROLES))->get();
            foreach ($admins as $admin) {
                NotificationService::send(
                    $admin->id,
                    'Nouvelle demande de congé',
                    "Nouvelle demande de congé créée pour {$requestedUser->name} du {$validated['start_date']} au {$validated['end_date']}",
                    'info',
                    '/leaves'
                );
            }

            return response()->json(['message' => 'Demande de congé créée', 'leave' => $leave->load(['user', 'leaveType'])], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LeaveStore error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la soumission de la demande'], 500);
        }
    }

    public function getLeaveTypes(): JsonResponse {
        try {
            return response()->json(LeaveType::all());
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LeaveGetTypes error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des types de congés'], 500);
        }
    }

    public function getBalances(Request $request): JsonResponse {
        try {
            $user = auth()->user();
            $requestedUserId = $request->input('user_id');

            if ($requestedUserId) {
                $requestedUser = User::find($requestedUserId);
                if (!$requestedUser || !$this->isEligible($requestedUser)) {
                    return response()->json(['message' => 'Personnel administratif non trouvé'], 404);
                }
                if (!$this->canManageAll($user) && $requestedUser->id !== $user->id) {
                    return response()->json(['message' => 'Non autorisé'], 403);
                }
            } else {
                if (!$this->isEligible($user)) {
                    return response()->json(['message' => 'Les enseignants n\'ont pas de solde de congés'], 403);
                }
                $requestedUser = $user;
            }

            $balances = LeaveBalance::with('leaveType')
                ->where('user_id', $requestedUser->id)
                ->where('year', now()->year)
                ->get();

            return response()->json($balances);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LeaveGetBalances error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des soldes'], 500);
        }
    }
}
