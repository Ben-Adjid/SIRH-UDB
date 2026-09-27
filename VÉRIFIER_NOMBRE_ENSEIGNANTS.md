# 📊 VÉRIFICATION: NOMBRE D'ENSEIGNANTS PAR RÔLE

## 🔍 REQUÊTES DANS CHAQUE DASHBOARD

### 1️⃣ ADMIN Dashboard
```php
$teacherStats = Teacher::selectRaw("
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, 
    COUNT(*) as total
")->first();

// Retourne: TOUS les enseignants (pas de filtre UFR)
```

### 2️⃣ HR/Responsable Administratif Dashboard
```php
$teacherStats = Teacher::selectRaw("
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, 
    COUNT(*) as total
")->first();

// Retourne: TOUS les enseignants (pas de filtre UFR)
```

### 3️⃣ CHEF UFR/Academic Manager Dashboard
```php
$teacherQuery = Teacher::where('status', 'active');
if ($isScoped && !empty($visibleUfrIds)) {
    $teacherQuery->whereIn('ufr_id', $visibleUfrIds);  // ⚠️ FILTRÉ PAR UFR
}
$activeTeachers = $teacherQuery->count();

$totalTeachersQuery = Teacher::query();
if ($isScoped && !empty($visibleUfrIds)) {
    $totalTeachersQuery->whereIn('ufr_id', $visibleUfrIds);  // ⚠️ FILTRÉ PAR UFR
}
$totalTeachers = $totalTeachersQuery->count();

// Retourne: SEULEMENT les enseignants de son UFR
```

### 4️⃣ COMPTABLE Dashboard
```php
// Pas de query spécifique pour les enseignants
// Affiche les données de PAIES uniquement
// N'affiche pas le nombre d'enseignants
```

### 5️⃣ ENSEIGNANT Dashboard
```php
// Affiche SEULEMENT ses propres données
// Pas de "nombre d'enseignants"
```

---

## ✅ RÉSUMÉ

| Rôle | Nombre enseignants | Filtre |
|------|-------------------|--------|
| **Admin** | ✅ TOUS | Pas de filtre |
| **HR/Responsable Administratif** | ✅ TOUS | Pas de filtre |
| **Chef UFR** | ⚠️ UFR uniquement | Filtré par UFR |
| **Comptable** | ❌ N/A | N/A |
| **Enseignant** | ❌ N/A | N/A |

---

## 🎯 CONCLUSION

### Admin vs HR/Responsable Administratif
✅ **MÊME NOMBRE** - Tous deux voient TOUS les enseignants

### Admin/HR vs Chef UFR
❌ **DIFFÉRENT** - Chef UFR voit SEULEMENT son UFR

