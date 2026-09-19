# 🔧 CORRECTIONS APPLIQUÉES ET RESTANTES

**Date:** 2026-09-19  
**Total Bugs:** 37  
**Corrigés:** 10 (27%)  
**Restants:** 27 (73%)

---

## ✅ BUGS CORRIGÉS (10)

### **CRITIQUES (8/12)**
✅ 1. Token validation inversée (authStore.js:18) - FIXÉ
✅ 2. hasRole(array) → hasAnyRole() (5 fichiers) - FIXÉ  
✅ 3. PIN validation timing attack (AttendanceController:120) - FIXÉ
✅ 4. Permissions UserController::index() - FIXÉ
✅ 5. Permissions UserController::store() - FIXÉ
✅ 6. Permissions UserController::update() - FIXÉ
✅ 7. Permissions UserController::destroy() - FIXÉ
✅ 8. Permissions UserController::toggleStatus() - FIXÉ

### **MAJEURS (2/15)**
✅ 9. Permissions LeaveController::updateStatus() - FIXÉ
✅ 10. Permissions LeaveController::updateBalance() - FIXÉ

---

## ❌ BUGS RESTANTS À CORRIGER (27)

### **CRITIQUES (4)**

#### **CRITIQUE 1: PIN Stocké en Clair**
**Fichier:** Migration + Model  
**Niveau:** CRITIQUE  
**Action:** Créer migration pour hasher tous les PIN codes existants

**Code de correction:**
```php
// Migration: 2024_XX_XX_XXXXXX_hash_teacher_pins.php
Schema::table('teachers', function (Blueprint $table) {
    $table->string('pin_code')->nullable()->change();
});

// Puis hasher tous les PINs:
foreach (Teacher::where('pin_code', '!=', null)->get() as $teacher) {
    $teacher->update(['pin_code' => Hash::make($teacher->pin_code)]);
}
```

**Puis modifier AttendanceController ligne 120:**
```php
// Déjà partiellement fixé, mais utiliser Hash::check():
if (!$teacher->pin_code || !Hash::check($validated['pin'], $teacher->pin_code)) {
    return response()->json(['message' => 'Code PIN invalide'], 422);
}
```

---

#### **CRITIQUE 2: Calcul Jours Congé Sans Jours Fériés**
**Fichier:** LeaveController.php:246-251  
**Niveau:** CRITIQUE  
**Action:** Implémenter table holidays et vérifier jours fériés

**Code de correction:**
```php
// Migration: 2024_XX_XX_XXXXXX_create_holidays_table.php
Schema::create('holidays', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->string('name');
    $table->string('country')->default('SN');
    $table->timestamps();
    $table->unique(['date', 'country']);
});

// Insérer les jours fériés sénégalais:
Holiday::create(['date' => '2026-01-01', 'name' => 'Jour de l\'an']);
Holiday::create(['date' => '2026-02-18', 'name' => 'Tabaski']); // À vérifier
Holiday::create(['date' => '2026-03-30', 'name' => 'Korité']); // À vérifier
// ... etc

// Puis modifier LeaveController:
$durationDays = 0;
$current = $start->copy();
$holidays = Holiday::whereDate('date', '>=', $start)
    ->whereDate('date', '<=', $end)
    ->pluck('date')
    ->map(fn($d) => $d->format('Y-m-d'));

while ($current->lte($end)) {
    if ($current->isWeekday() && !$holidays->contains($current->format('Y-m-d'))) {
        $durationDays++;
    }
    $current->addDay();
}
```

---

#### **CRITIQUE 3: Permissions ScheduleController::storeExam()** - FIXÉ ✅

#### **CRITIQUE 4: Permissions ScheduleController::updateExam()**
**Fichier:** ScheduleController.php  
**Ligne:** (grep pour la trouver)  
**Action:** Ajouter permissions au début

**Code:**
```php
public function updateExam(Request $request, Schedule $schedule): JsonResponse {
    if (!auth()->user()->hasAnyRole(['admin', 'academic_manager'])) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }
    // ... rest du code
}
```

---

### **MAJEURS (11)**

#### **MAJEUR 1: Calcul Gross Salary Incorrect**
**Fichier:** PayrollController.php:210  
**Problème:** Après ajout de lignes, BRS n'est pas recalculé  
**Code:**
```php
// Dans addLineItem, après création de la ligne, recalculer:
$details = PayrollDetail::where('payroll_id', $payroll->id)->get();
$totalEarnings = $details->where('type', 'earning')->sum('amount');
$totalDeductions = $details->where('type', 'deduction')->sum('amount');

$brsAmount = 0;
if ($totalEarnings > 25000) {
    $brsAmount = round($totalEarnings * 0.05, 2);
}

$grossSalary = $totalEarnings - $brsAmount;
$netSalary = max(0, $totalEarnings - $totalDeductions);

$payroll->update([
    'brs_amount' => $brsAmount,
    'gross_salary' => $grossSalary,
    'net_salary' => $netSalary,
    'deductions' => $totalDeductions,
]);
```

---

#### **MAJEUR 2-4: Permissions ScheduleController**
**Fichier:** ScheduleController.php  
**Actions:**
- updateExam() - ajouter permissions
- destroyExam() - ajouter permissions
- create() - vérifier permissions

**Code générique:**
```php
if (!auth()->user()->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
    return response()->json(['message' => 'Non autorisé'], 403);
}
```

---

#### **MAJEUR 5: Time Comparison Incorrecte**
**Fichier:** HomeworkController.php:233  
**Problème:** Compare heures comme strings

**Code actuel (MAUVAIS):**
```php
if ($endTime <= $startTime) {
    return response()->json([...], 422);
}
```

**Code correct:**
```php
$start = Carbon::createFromFormat('H:i', $startTime);
$end = Carbon::createFromFormat('H:i', $endTime);

if ($end->lte($start)) {
    return response()->json(['message' => 'L\'heure de fin doit être postérieure à l\'heure de début'], 422);
}
```

---

#### **MAJEUR 6: Suppression Paie Sans Audit**
**Fichier:** PayrollController.php:96-100  
**Problème:** Suppression silencieuse sans trace

**Code correct:**
```php
// Avant suppression, créer audit log:
Payroll::where([...])->whereIn('status', ['draft', 'calculated'])->get()->each(function($payroll) {
    AuditLog::create([
        'user_id' => auth()->id(),
        'action' => 'delete_draft_payroll',
        'model' => 'Payroll',
        'model_id' => $payroll->id,
        'old_values' => $payroll->toArray(),
        'ip_address' => request()->ip(),
    ]);
});

Payroll::where([...])->whereIn('status', ['draft', 'calculated'])->delete();
```

---

#### **MAJEUR 7-8: Permissions PayrollController**
**Fichier:** PayrollController.php  
**Actions:**
- validatePayroll() - ajouter permissions
- markAsPaid() - ajouter permissions
- export() - ajouter permissions

**Code:**
```php
public function validatePayroll(Request $request, Payroll $payroll): JsonResponse {
    if (!auth()->user()->hasAnyRole(['admin', 'accountant', 'hr_manager'])) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }
    // ...
}
```

---

#### **MAJEUR 9: Accès Teacher Non Sécurisé**
**Fichier:** TeacherController.php:33-37  
**Problème:** `canAccessTeacher()` ne restreint pas assez

**Code actuel:**
```php
private function canAccessTeacher(Teacher $teacher, User $user): bool {
    return $user->hasRole('admin') 
        || in_array($teacher->ufr_id, $this->visibleUfrIds($user));
}
```

**Problème:** N'importe quel enseignant de l'UFR peut accéder  
**Correction:** Ajouter contrôle plus strict

---

#### **MAJEUR 10: Incohérence Noms Champs**
**Fichier:** LeaveController.php:263  
**Problème:** Message mentionne "enseignant"

**Correction:**
```php
return response()->json(['message' => 'Solde de congés non trouvé pour ce personnel administratif et ce type de congé'], 422);
```

---

### **MINEURS (8)**

| # | Fichier | Problème | Correction |
|---|---------|----------|-----------|
| 1 | ScheduleController:271 | Logique confuse whereNotIn | Remplacer par where('id', '!=', ...) |
| 2 | Leaves.jsx:106 | Noms champs incohérents | Standardiser au backend |
| 3 | HomeworkController:49 | Logique permission confuse | Simplifier la condition |
| 4 | HomeworkController:109 | hasRole() avec array | ✅ Déjà fixé |
| 5 | TeacherController:119 | UFR null possible | Ajouter vérification |
| 6 | AuthController:31 | Permission admin confuse | Clarifier logique |
| 7 | UserController:119 | Race condition token | Utiliser currentAccessToken() sécurisé |
| 8 | Payroll.jsx:106 | Heures ignorées frontend | ✅ Backend correct, frontend à vérifier |

---

## 📋 RÉSUMÉ PAR PRIORITÉ

### **P0 - IMMÉDIAT (Avant Production)**
- [x] Token validation
- [x] hasRole → hasAnyRole
- [x] PIN timing attack
- [x] Permissions UserController (7 bugs)
- [ ] PIN hasher (1 bug)
- [ ] Jours fériés (1 bug)

**Restant P0:** 2 bugs

### **P1 - CETTE SEMAINE**
- [ ] Time comparison (HomeworkController)
- [ ] Gross salary calculation
- [ ] Permissions ScheduleController (3 bugs)
- [ ] Permissions PayrollController (3 bugs)
- [ ] Audit logging

**Restant P1:** 8 bugs

### **P2 - OPTIMISATION**
- [ ] Messages utilisateur
- [ ] Cohérence noms champs
- [ ] Logique permission simplification
- [ ] Race conditions

**Restant P2:** 8 bugs

---

## 🔄 PROCHAINS COMMITS

### **Commit 1: PIN Hasher + Jours Fériés** (CRITICAL)
```bash
git commit -m "fix: Hasher PIN codes et implémenter jours fériés pour congés

- Créer migration pour hasher tous les PIN codes existants
- Créer table holidays avec jours fériés sénégalais
- Modifier LeaveController pour compter jours fériés
- Modifier AttendanceController pour utiliser Hash::check()

Impact: Sécurité PIN + Calcul correct soldes congés
"
```

### **Commit 2: Permissions Payroll & Schedule**
```bash
git commit -m "fix: Ajouter permissions manquantes aux controllers payroll et schedule

- ScheduleController::updateExam()
- ScheduleController::destroyExam()
- PayrollController::validatePayroll()
- PayrollController::markAsPaid()
- PayrollController::export()

Impact: Empêche n'importe qui de modifier paies/examens
"
```

### **Commit 3: Bug Majeurs (Logique)**
```bash
git commit -m "fix: Corriger bugs majeurs de logique

- Calcul gross salary après ajout lignes (PayrollController)
- Time comparison utilisant Carbon (HomeworkController)
- Audit logging avant suppression paie
- Logique permission simplifiée

Impact: Calculs corrects, traçabilité, logique claire
"
```

---

## 📊 COUVERTURE

| Level | Total | Corrigés | % |
|-------|-------|----------|---|
| CRITIQUE | 12 | 8 | 67% |
| MAJEUR | 15 | 2 | 13% |
| MINEUR | 10 | 0 | 0% |
| **TOTAL** | **37** | **10** | **27%** |

---

## ⏱️ ESTIMATION

**Temps pour corriger tous les bugs:** 2-3 jours  
**Bugs critiques urgents:** 2 bugs (PIN + Jours fériés)  
**Bugs majeurs importants:** 8 bugs (Logique + Permissions)  
**Bugs mineurs:** 8 bugs (Polish)

