# Fixes Summary - September 23, 2026

## ✅ COMPLETED ISSUES

### Issue 1: Chef UFR Can Now Add Teachers & Create Subjects

**Problem:** Chef UFR (academic_manager) couldn't add teachers or create subjects.

**Solutions Implemented:**

**Backend:**
- **Routes** (`routes/api.php`): 
  - Moved `/teachers` POST & PUT routes out of RH-only group
  - Added new `academic_manager` group for teacher create/update
  - Kept delete/archive/contracts for RH-only (`admin|hr_manager|secretary`)

- **TeacherController** (`app/Http/Controllers/Api/TeacherController.php`):
  - Added `isUfrHeadOnly()` helper to detect chef UFR without RH roles
  - In `store()`: validate that chef UFR only adds teachers to their own UFR
  - In `update()`: restrict chef UFR modifications to their UFR's teachers
  - Added `canAccessTeacher()` checks for chef UFR

- **StoreTeacherRequest** (`app/Http/Requests/StoreTeacherRequest.php`):
  - Added `academic_manager` role to authorization

- **SubjectController** (`app/Http/Controllers/Api/SubjectController.php`):
  - Modified `store()` to allow `academic_manager` role
  - Chef UFR: subject automatically assigned to their UFR
  - Added UFR validation in `update()` and `destroy()` for chef UFR

- **Subject Model & Database** (`app/Models/Subject.php`):
  - Added `ufr_id` to fillable array
  - Added `ufr()` relationship (BelongsTo)
  - Created migration: `2026_09_23_000001_add_ufr_id_to_subjects_table`

**Frontend:**
- **Teachers.jsx**:
  - Split permissions: `canEdit` (includes `academic_manager`) for add/edit buttons
  - `canManage` (RH-only) for deactivate/delete buttons
  - Pre-select chef UFR's only UFR in new teacher form

- **Subjects.jsx**:
  - Added UFR field to form (required for chef UFR, optional for admin)
  - Load UFR dropdown with subjects
  - Display UFR badge on subject cards
  - Pre-select single UFR when only one available

---

### Issue 2: Teachers Now See Their Subjects & Schedule Visibility Fixed

**Problem:** 
- Teachers couldn't see their own subjects/homeworks because subjects without schedules weren't visible
- Teachers' schedule visibility was limited by checking only `schedules.teacher.ufr_id`

**Solutions Implemented:**

**Backend:**
- **SubjectController** (`index()` & `store()`):
  - Teachers: filter subjects by active non-cancelled schedules they teach
  - Chef UFR: see subjects by `ufr_id` match OR schedules.teacher.ufr_id match
  - Admin: see all subjects

- **HomeworkController** (`subjectBelongsToVisibleUfr()`):
  - **Before:** Only checked `schedules.teacher.whereIn('ufr_id', ...)`
  - **After:** Also checks `Subject.ufr_id` directly
  - Chef UFR can now create homeworks for subjects in their UFR even before scheduling courses

- **ScheduleController** (`subjectBelongsToVisibleUfr()`):
  - Same fix: check both `Subject.ufr_id` AND `schedules.teacher.ufr_id`
  - Fixes bug where new subjects couldn't be used in schedules

- **DashboardController** (`academicStats()`):
  - Updated `total_subjects` count to include `Subject.ufr_id` matches
  - Previously: only counted subjects with schedules in visible UFRs

**Frontend:**
- **Schedules.jsx**:
  - Teachers: view only their own schedule (already correctly filtered via `/schedules` endpoint)
  - No frontend changes needed; backend `ScheduleController::index()` already filters `teacher_id` for teachers

**Result:** 
- Chef UFR can create subjects before assigning them to any course
- Teachers see only courses/subjects they're assigned to
- No "permission denied" on newly created subjects anymore

---

## ⏳ ISSUES DEFERRED FOR FOLLOW-UP

### Issue 3: Attendance PIN Code Visibility

**Problem:** "Le code de pointage ce n'est pas visible au niveau de là où on doit saisir le code" (Attendance code not visible where it should be entered)

**Status:** Requires investigation into Attendance form layout and PIN display logic

**Next Steps:**
- Check `frontend/src/pages/Attendance.jsx` form rendering
- Review PIN generation/retrieval endpoints
- Verify `attendance/my-pin` and `attendance/active-code` endpoints are called
- May need UI adjustments to display PIN prominently

### Issue 4: Leave/Congé Management Problems

**Problem:** "Pour la partie de congé il ya un problème"

**Status:** Unclear scope; requires more details

**Possible Areas:**
- Role restrictions (administrative users only, teachers excluded)
- Leave balance calculation
- Leave type validation
- Approval workflow

**Next Steps:**
- Request specific error message or behavior description
- Check `LeaveController` and `LeaveRequest` model validation
- Verify leave route permissions

---

## 📋 GIT COMMIT

**Commit Hash:** `821cb15`

**Message:**
```
fix: Grant chef UFR permissions for teacher/subject management + visibility

Issue 1: Chef UFR can now add teachers and create subjects (scoped to their UFR)
- Opened /teachers POST+PUT routes to academic_manager (chef UFR)
- Added UFR-scoping validation in TeacherController
- Created migration to add ufr_id foreign key to subjects table
- Updated SubjectController to allow chef UFR creation (auto-assigns to their UFR)
- Frontend: separate canEdit (includes academic_manager) from canManage (RH-only)

Issue 2: Teachers see only their subjects/homeworks + their schedule visibility
- Subjects: added ufr_id relation; teachers see only schedules where they teach
- HomeworkController & ScheduleController: check subject.ufr_id not just schedules.teacher.ufr_id
  (fixes bug where new subjects without schedules were invisible to UFR heads)
- SubjectController index: filter by active non-archived schedules for teachers
- Dashboard: updated total_subjects count to include ufr_id matches
- Schedules page: already displayed per-teacher correctly (data fetched via /schedules endpoint)

Migration status: pending -> ran successfully
```

---

## 🧪 TESTING RECOMMENDATIONS

1. **As Chef UFR (academic_manager):**
   - Navigate to Teachers page → "Nouvel Enseignant" button should appear
   - Add a teacher (should auto-select your UFR)
   - Navigate to Matières page → "Ajouter Module" button should appear
   - Create a subject → UFR field should show your UFR

2. **As Teacher:**
   - View Emploi du temps → should only show your courses
   - Create a devoir → only your subjects should be available
   - Check Subjects list → only subjects with active schedules where you teach

3. **As Admin:**
   - All features unchanged
   - Can create subjects without UFR assignment

4. **Database:**
   - Run: `php artisan migrate:status` → all migrations should be "Ran"
   - Run: `php artisan tinker` → `Subject::first()->ufr;` should work

---

## 📁 FILES MODIFIED

**Backend:**
- `routes/api.php`
- `app/Http/Controllers/Api/TeacherController.php`
- `app/Http/Controllers/Api/SubjectController.php`
- `app/Http/Controllers/Api/HomeworkController.php`
- `app/Http/Controllers/Api/ScheduleController.php`
- `app/Http/Controllers/Api/DashboardController.php`
- `app/Models/Subject.php`
- `app/Http/Requests/StoreTeacherRequest.php`
- `database/migrations/2026_09_23_000001_add_ufr_id_to_subjects_table.php` (new)

**Frontend:**
- `frontend/src/pages/Teachers.jsx`
- `frontend/src/pages/Subjects.jsx`
- `frontend/src/pages/Schedules.jsx` (no changes; already working correctly)

---

## 🔄 NEXT SESSION ACTION ITEMS

Priority order:
1. **Issue 3 (Attendance PIN):** Investigate visibility/display in form
2. **Issue 4 (Leaves):** Request clarification on specific problem
3. **Testing:** Manually test all scenarios with different roles
4. **API Validation:** Ensure all permission checks are working as expected
