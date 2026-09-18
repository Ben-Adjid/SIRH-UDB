# Plan: 44 corrections SIRH Dakar

## Phase 1 — Bugs critiques (#1-8)

### #1 Teachers.jsx:774 — Label "UFR" au lieu de "Département"
- **File:** `frontend/src/pages/Teachers.jsx`
- **Change:** Line 774, `<label className="form-label">UFR</label>` → `<label className="form-label">Département</label>`

### #2 Homeworks.jsx:78 — Data extraction cassée après CRUD
- **File:** `frontend/src/pages/Homeworks.jsx`
- **Change:** Line 78: `setHomeworks(response.data)` → `setHomeworks(response.data.data || response.data || [])`

### #3 Dashboard.jsx:445 — Operator precedence
- **File:** `frontend/src/pages/Dashboard.jsx`
- **Change:** `(stats.my_leave_balances?.[0]?.total_days - stats.my_leave_balances?.[0]?.used_days || 0)` → `((stats.my_leave_balances?.[0]?.total_days ?? 0) - (stats.my_leave_balances?.[0]?.used_days ?? 0))`

### #4 Subjects.jsx:35,50 — Missing `[]` fallback
- **File:** `frontend/src/pages/Subjects.jsx`
- **Change:** Lines 35, 50: `setSubjects(response.data.data || response.data)` → `setSubjects(response.data.data || response.data || [])`

### #5 Classrooms.jsx:34,49 — Missing `[]` fallback
- **File:** `frontend/src/pages/Classrooms.jsx`
- **Change:** Lines 34, 49: `setClassrooms(response.data.data || response.data)` → `setClassrooms(response.data.data || response.data || [])`

### #6 App.jsx — Ajouter route 404
- **File:** `frontend/src/App.jsx`
- **Change:** Add `<Route path="*" element={<Navigate to="/dashboard" replace />} />` inside `<Layout />` group, and a standalone `<Route path="*" element={<Navigate to="/login" replace />} />` outside it.

### #7 App.jsx — Redirect authenticated users from /login, /register
- **File:** `frontend/src/App.jsx`
- **Change:** Wrap Login/Register routes with a check: if `isAuthenticated`, `<Navigate to="/dashboard" replace />`

### #8 DepartmentController.php:50 — Message "UFR créée" → "Département créé"
- **File:** `backend/app/Http/Controllers/Api/DepartmentController.php`
- **Change:** Line 50: `'UFR créée'` → `'Département créé'`

---

## Phase 2 — Sécurité & données (#9-17)

### #9 backend/.env — APP_DEBUG=true
- **File:** `backend/.env`
- **Change:** `APP_DEBUG=true` → `APP_DEBUG=false`

### #10 Soft deletes — Migration + Models (7 models)
- **Files:** `app/Models/User.php`, `Teacher.php`, `Payroll.php`, `Attendance.php`, `Leave.php`, `Homework.php`, `Document.php`
- **Change:** Add `use Illuminate\Database\Eloquent\SoftDeletes;` trait + `$dates`/`$casts` to each model. Create migration: `$table->softDeletes()` on each table.
- **Controllers:** Update `destroy()` methods to use `->delete()` (soft) instead of force-delete. Add `forceDelete()` endpoint for admin only.

### #11 Pagination — Add to 12 controllers
- **Files:** `UserController`, `SubjectController`, `ClassroomController`, `DepartmentController`, `UfrController`, `ScheduleController`, `HomeworkController`, `AttendanceController`, `TeacherController`, `TeachingSessionController`, `DocumentController`, `PayrollController`
- **Change:** Replace `->get()` with `->paginate($request->input('per_page', 25))` in index/show methods.

### #12 LeaveController.php:175 — Duration includes weekends
- **File:** `backend/app/Http/Controllers/Api/LeaveController.php`
- **Change:** Replace `diffInDays()` with a custom calculation that excludes Saturdays and Sundays. Helper function:
```php
function workingDays(Carbon $start, Carbon $end): int {
    $days = 0;
    $current = $start->copy();
    while ($current->lte($end)) {
        if (!$current->isSaturday() && !$current->isSunday()) $days++;
        $current->addDay();
    }
    return $days;
}
```

### #13 PayrollController.php:186 — Hardcoded 22 working days
- **File:** `backend/app/Http/Controllers/Api/PayrollController.php`
- **Change:** Replace `$workingDays = 22` with dynamic calculation based on the month/year:
```php
$carbon = Carbon::create($year, $month, 1);
$workingDays = $carbon->daysInMonth - $carbon->copy()->endOfMonth()->diffInDaysFiltered(
    fn($date) => $date->isSaturday() || $date->isSunday(), $carbon->copy()->startOfMonth()
);
// Fallback
$workingDays = max($workingDays, 1);
```

### #14 HomeworkController.php:169-188 — Missing grade validation
- **File:** `backend/app/Http/Controllers/Api/HomeworkController.php`
- **Change:** In `recordGrades()`, validate each grade item:
```php
$validated = $request->validate([
    'homework_id' => 'required|exists:homeworks,id',
    'grades' => 'required|array|min:1',
    'grades.*.student_id' => 'required|string',
    'grades.*.student_name' => 'required|string',
    'grades.*.score' => 'required|numeric|min:0',
]);
// After validation, check score <= max_score
$homework = Homework::findOrFail($validated['homework_id']);
foreach ($validated['grades'] as &$g) {
    $g['score'] = min($g['score'], $homework->max_score);
}
```

### #15 DocumentController.php:106 — Public disk exposure
- **File:** `backend/app/Http/Controllers/Api/DocumentController.php`
- **Change:** Switch from `Storage::disk('public')->download()` to `Storage::disk('private')->download()` or use signed URLs. At minimum, add auth check before download.

### #16 AttendanceController.php:41 — Weak randomness
- **File:** `backend/app/Http/Controllers/Api/AttendanceController.php`
- **Change:** `Str::random(4) . rand(1000, 9999)` → `Str::random(4) . random_int(1000, 9999)`

### #17 CorsMiddleware.php:18-25 — Hardcoded origins
- **File:** `backend/app/Http/Middleware/CorsMiddleware.php`
- **Change:** Replace hardcoded origins with `env('CORS_ORIGINS', 'http://localhost:5173')` and split by comma.

---

## Phase 3 — Bugs frontend moyens (#18-31)

### #18 Teachers.jsx:51-52 — Dead state (sortField/sortDir)
- **File:** `frontend/src/pages/Teachers.jsx`
- **Change:** Remove `const [sortField] = useState('name')` and `const [sortDir] = useState('asc')` lines. Also remove references in sort logic if any.

### #19 Sidebar.jsx:1 — Unused import FileText
- **File:** `frontend/src/components/Sidebar.jsx`
- **Change:** Remove `FileText` from the lucide-react import line.

### #20 Login.jsx:24-36 — Missing try/catch
- **File:** `frontend/src/pages/Login.jsx`
- **Change:** Wrap `handleSubmit` body in try/catch, add `finally { setLoading(false); }`. Move `setLoading(true)` to top of try.

### #21 Register.jsx:16-31 — Missing try/catch
- **File:** `frontend/src/pages/Register.jsx`
- **Change:** Same pattern as Login.jsx — wrap in try/catch/finally.

### #22 ErrorBoundary.jsx:15 — console.error in production
- **File:** `frontend/src/components/ErrorBoundary.jsx`
- **Change:** Remove `console.error('ErrorBoundary caught:', error, errorInfo)`.

### #23 NotificationBell.jsx:20,34 — console.warn in production
- **File:** `frontend/src/components/NotificationBell.jsx`
- **Change:** Remove both `console.warn(...)` lines (20 and 34).

### #24 ConfirmModal.jsx:28 — Loading text always "Suppression..."
- **File:** `frontend/src/components/ConfirmModal.jsx`
- **Change:** Replace hardcoded `"Suppression..."` with a prop `loadingText` or derive from `confirmText`:
```jsx
const displayText = loading ? (loadingText || 'Chargement...') : confirmText;
```
Add `loadingText` to the component props with default `'Chargement...'`.

### #25 ConfirmModal.jsx:31 — Missing accessibility
- **File:** `frontend/src/components/ConfirmModal.jsx`
- **Change:** Add `role="dialog"`, `aria-modal="true"`, `aria-labelledby` to the modal container div.

### #26 Classrooms.jsx:198 — Fake "Disponible" badge
- **File:** `frontend/src/pages/Classrooms.jsx`
- **Change:** Remove or replace the hardcoded badge. If there's a `status` field, use it; otherwise remove the badge entirely.

### #27 Dashboard.jsx:131 — Hardcoded "100%" system health
- **File:** `frontend/src/pages/Dashboard.jsx`
- **Change:** Either make it dynamic (ping backend) or replace with a different stat card that shows real data. Simplest: remove the "Santé Système" card or replace with "Dernière connexion" or similar real metric.

### #28 Users.jsx:206 — Hardcoded admin email
- **File:** `frontend/src/pages/Users.jsx`
- **Change:** Instead of `u.email === 'admin@sirh.local'`, check for a role: `getUserRoles(u).includes('admin')` or add a `is_superadmin` flag. Simplest: check if user has `admin` role AND is the only admin.

### #29 NotificationBell.jsx:24-39 — Missing AbortController
- **File:** `frontend/src/components/NotificationBell.jsx`
- **Change:** Add AbortController to the interval fetch, abort on cleanup.

### #30 ErrorBoundary.jsx:35 — Pointless handleReset
- **File:** `frontend/src/components/ErrorBoundary.jsx`
- **Change:** Remove `this.handleReset()` since `window.location.reload()` runs immediately after. Just keep `window.location.reload()`.

### #31 Delete src/nul
- **File:** `frontend/src/nul`
- **Change:** Delete the file (it contains a bash error message from a previous accidental redirect).

---

## Phase 4 — Améliorations code quality (#32-44)

### #32 Replace `|| ''` with `?? ''` across frontend
- **Files:** All pages (Departments, Subjects, Classrooms, Teachers, Users, Homeworks, Attendance, Leaves, Payroll, Schedules)
- **Change:** Replace all `|| ''` and `|| []` patterns with `?? ''` and `?? []` where the value could legitimately be `0` or `false`.
- **Key instances:**
  - Departments.jsx lines 49,51,67,78,87,189-190
  - Teachers.jsx lines 83-85,101,112,121,170-174,195-206
  - Users.jsx line 210
  - Attendance.jsx lines 81-82,87-89,93-94,111,124
  - Leaves.jsx lines 47-49,76,82
  - Schedules.jsx lines 69-72
  - Payroll.jsx lines 84-86,107

### #33 Extract role labels to utils/roles.js
- **Files:** `utils/roles.js`, `Layout.jsx`, `Sidebar.jsx`, `Profile.jsx`, `Dashboard.jsx`, `Users.jsx`
- **Change:** Add to `utils/roles.js`:
```js
export const ROLE_LABELS = {
    admin: 'Administrateur',
    hr_manager: 'Responsable RH',
    academic_manager: 'Responsable Académique',
    accountant: 'Comptable',
    teacher: 'Enseignant',
};
export const getRoleLabel = (role) => ROLE_LABELS[role] || role;
```
Replace all inline mappings.

### #34 Standardize ConfirmModal usage
- **Change:** Standardize to a single `confirmConfig` state object pattern (Teachers.jsx pattern) across all pages: Homeworks, Schedules, Leaves, Payroll, Users, Departments.

### #35 Standardize API error response format
- **File:** `backend/app/Http/Controllers/Api/` (all controllers)
- **Change:** Unify all error responses to use `{'message': '...'}` consistently. Remove `'error'` key usage.

### #36 Mount ErrorBoundary in App.jsx
- **File:** `frontend/src/App.jsx`
- **Change:** Wrap `<Routes>` or main app content with `<ErrorBoundary>`.

### #37 Remove unnecessary React imports
- **Files:** App.jsx, Departments.jsx, Subjects.jsx, Classrooms.jsx, AuditLogs.jsx, Profile.jsx
- **Change:** Remove `import React from 'react'` where not needed.

### #38 Re-enable React.StrictMode
- **File:** `frontend/src/main.jsx`
- **Change:** Re-add `<React.StrictMode>` wrapper. The ConfirmModal fix (text-based loading) should not conflict.

### #39 Fix hardcoded support email
- **File:** `frontend/src/components/Layout.jsx`
- **Change:** Line 151: Replace hardcoded `support@sirh.local` with an env variable or remove the feature.

### #40 Fix Payroll.jsx confirm state management
- **File:** `frontend/src/pages/Payroll.jsx`
- **Change:** Replace 4 separate states (`confirmTitle`, `confirmMessage`, `confirmAction`, `confirmType`) with a single `confirmConfig` object, matching Teachers.jsx pattern.

### #41 HomeworkController approve status check
- **File:** `backend/app/Http/Controllers/Api/HomeworkController.php`
- **Change:** In `approve()`, add status check:
```php
if ($homework->status !== 'pending') {
    return response()->json(['message' => 'Seuls les devoirs en attente peuvent être approuvés'], 422);
}
```

### #42 Move NotificationController::send() to Service
- **Files:** Create `backend/app/Services/NotificationService.php`, update `NotificationController.php` and all controllers that call `NotificationController::send()`.
- **Change:** Extract static method to a proper service class.

### #43 Fix apiResource route conflict
- **File:** `routes/api.php`
- **Change:** Remove `Route::apiResource('schedules', ...)` and keep only the explicit route definitions, OR remove the explicit routes and use only apiResource.

### #44 CorsMiddleware — Use env for origins
- **File:** `backend/app/Http/Middleware/CorsMiddleware.php`
- **Change:** Already covered in #17.

---

## Build & verify
- Run `npm run build` in frontend
- Run `php artisan route:list` to verify routes
- Run `php artisan migrate` for soft deletes migration
