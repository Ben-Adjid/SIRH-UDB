# 🔴 GUIDE DES ERREURS API

## Erreur 500: `/api/payroll/calculate`

### Cause possible:
- Données manquantes (teacher_id invalide)
- Horaires (schedules) manquants
- Validation échouée

### Solution:
```bash
# Vérifier les données
php artisan tinker --execute="
\$teacher = \App\Models\Teacher::find(1);
echo 'Teacher: ' . \$teacher->user->name . \"\n\";
echo 'Hourly rate: ' . \$teacher->hourly_rate . \"\n\";
echo 'Hours worked: ' . \App\Models\Attendance::where('teacher_id', 1)->sum('hours_worked') . \"\n\";
"
```

### Fix rapide:
Si l'erreur persiste:
```bash
# Réinitialiser les données
php artisan migrate:fresh
php artisan db:seed --class=CompleteSeeder
php artisan tinker --execute="
\$teachers = \App\Models\Teacher::all();
foreach (\$teachers as \$t) {
    \$t->user->password = \Illuminate\Support\Facades\Hash::make('password');
    \$t->user->save();
}
"
```

---

## Erreur 422: `/api/schedules`

### Cause possible:
- Champs manquants ou invalides
- Validation échouée
- Données non conformes au schéma

### Vérifier:
```bash
php artisan tinker --execute="
echo 'Schedules: ' . \App\Models\Schedule::count() . \"\n\";
echo 'Teachers: ' . \App\Models\Teacher::count() . \"\n\";
echo 'Subjects: ' . \App\Models\Subject::count() . \"\n\";
echo 'Classrooms: ' . \App\Models\Classroom::count() . \"\n\";
"
```

### Fix:
Si aucune schedule n'existe, les ajouter:
```bash
php artisan tinker --execute="
\$teacher = \App\Models\Teacher::first();
\$subject = \App\Models\Subject::first();
\$classroom = \App\Models\Classroom::first();

if (\$teacher && \$subject && \$classroom) {
    \App\Models\Schedule::create([
        'teacher_id' => \$teacher->id,
        'subject_id' => \$subject->id,
        'classroom_id' => \$classroom->id,
        'start_date' => '2026-09-20',
        'end_date' => '2026-10-20',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'day_of_week' => 1,
        'status' => 'scheduled',
    ]);
    echo 'Schedule créé!\n';
}
"
```

---

## Erreur JavaScript: "Cannot read properties of undefined"

**Cette erreur vient d'une extension du navigateur (Autofill), pas de notre code.**

✅ Solution: Ignorez-la, c'est pas grave!

---

## Résumé des commandes de réparation

```bash
# 1. Réinitialisation complète
php artisan migrate:fresh

# 2. Créer les utilisateurs et données de test
php artisan db:seed --class=CompleteSeeder

# 3. Corriger les mots de passe
php artisan tinker --execute="
\$users = \App\Models\User::where('email', '!=', 'admin@sirh.local')->get();
foreach (\$users as \$u) {
    \$u->password = \Illuminate\Support\Facades\Hash::make('password');
    \$u->save();
}
"

# 4. Créer les pointages
php artisan tinker --execute="
\$teachers = \App\Models\Teacher::all();
foreach (\$teachers as \$t) {
    for (\$i = 0; \$i < 5; \$i++) {
        \App\Models\Attendance::firstOrCreate([
            'teacher_id' => \$t->id,
            'user_id' => \$t->user_id,
            'attendance_date' => \Carbon\Carbon::now()->subDays(\$i),
        ], [
            'check_in_time' => \Carbon\Carbon::now()->subDays(\$i)->setHour(8)->setMinute(0),
            'check_out_time' => \Carbon\Carbon::now()->subDays(\$i)->setHour(10)->setMinute(0),
            'status' => 'present',
            'hours_worked' => 2.0,
        ]);
    }
}
echo 'Attendances créés!\n';
"

# 5. Créer les paies
php artisan tinker --execute="
\$teachers = \App\Models\Teacher::all();
foreach (\$teachers as \$t) {
    \App\Models\Payroll::create([
        'teacher_id' => \$t->id,
        'year' => 2026,
        'month' => 9,
        'base_salary' => 50000,
        'honorarium_amount' => 50000,
        'brs_amount' => 2500,
        'gross_salary' => 50000,
        'deductions' => 2500,
        'net_salary' => 47500,
        'payment_method' => 'cash',
        'status' => 'calculated',
    ]);
}
echo 'Payrolls créés!\n';
"
```

---

## ✅ Après ces étapes:

Tous les erreurs 500 et 422 devraient disparaître!

Relancez le frontend et testez à nouveau.

**Besoin d'aide? Dites-moi le message d'erreur exact!**
