# 🚀 GUIDE REDÉMARRAGE RAPIDE - SIRH

## ✅ STATUT ACTUEL

| Composant | URL | Status |
|-----------|-----|--------|
| **Backend** | http://localhost:8000 | 🟢 Running |
| **Frontend** | http://localhost:5175 | 🟢 Running |
| **Database** | Local | 🟢 OK |

---

## 📋 CREDENTIALS DE TEST

**Mot de passe pour TOUS les comptes: `password`**

### Comptes Principaux:
```
admin@sirh.local        → Admin (accès complet)
rh@sirh.local           → RH (gestion RH)
compta@sirh.local       → Comptable (gestion paie)
academic@sirh.local     → Chef d'UFR
secretaire@sirh.local   → Secrétaire
prof@sirh.local         → Enseignant (test pointage)
```

### Plus de Comptes:
```
prof1@sirh.local        → Enseignant
prof2@sirh.local        → Enseignant
prof3@sirh.local        → Enseignant
+ 15 autres (voir base de données)
```

---

## 🧪 TESTER LA CONNEXION

### Via Interface Web:
1. Ouvrir: **http://localhost:5175**
2. Cliquer sur "Comptable" (ou autre rôle)
3. Email: `compta@sirh.local` (auto-rempli)
4. Password: `password`
5. Cliquer "Accéder au portail"
6. ✅ Vous êtes connecté!

### Via API (curl):
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@sirh.local","password":"password"}'
```

---

## 🔧 REDÉMARRER LES SERVICES

### Backend (Terminal 1):
```bash
cd C:\Users\aboub\Desktop\DIT\backend
php artisan serve --port=8000
```

### Frontend (Terminal 2):
```bash
cd C:\Users\aboub\Desktop\DIT\frontend
npm run dev
```

**Affichera**: "Local: http://localhost:XXXX"

---

## ⚠️ SI ÇA NE MARCHE PAS

### Problème: "Identifiants invalides"
**Solution**: Réinitialiser les passwords
```bash
cd backend
php artisan tinker --execute="
\$users = \App\Models\User::all();
foreach (\$users as \$u) {
    \$u->password = \Illuminate\Support\Facades\Hash::make('password');
    \$u->save();
}
echo '✅ Passwords réinitialisés';
"
```

### Problème: "Connection refused" (Backend)
**Solution**: Backend pas démarré
```bash
cd backend
php artisan serve --port=8000
```

### Problème: "Failed to load resource 401"
**Solution**: Frontend ne parle pas au backend
- Vérifier backend sur http://localhost:8000/api/auth/login
- Vérifier credentials (email/password)

### Problème: Base de données vide
**Solution**: Réinitialiser
```bash
php artisan migrate:fresh
php artisan db:seed --class=CompleteSeeder
```

---

## 📊 VÉRIFIER L'ÉTAT SYSTÈME

```bash
cd backend

# Vérifier les utilisateurs
php artisan tinker --execute="
echo 'Utilisateurs: ' . \App\Models\User::count();
echo 'Enseignants: ' . \App\Models\Teacher::count();
echo 'Pointages: ' . \App\Models\Attendance::count();
echo 'Paies: ' . \App\Models\Payroll::count();
"

# Vérifier les tests
php artisan test
```

---

## ✨ FONCTIONNALITÉS CLÉS À TESTER

1. **Connexion** ✅
   - Tester avec admin@sirh.local / password

2. **Pointage** ✅
   - Se connecter avec prof@sirh.local
   - Aller à "Pointage"
   - Voir les 5 derniers pointages
   - Générer PIN → Utiliser pour check-in

3. **Paie** ✅
   - Se connecter avec compta@sirh.local
   - Aller à "Paie"
   - Voir les calculs (Brut, BRS, Net)

4. **Profil** ✅
   - Se connecter avec n'importe quel compte
   - Aller à "Mon Profil"
   - Voir informations personnelles

---

## 🎯 RÉSUMÉ

- ✅ Backend: http://localhost:8000
- ✅ Frontend: http://localhost:5175
- ✅ 24 utilisateurs disponibles
- ✅ Tous les passwords: "password"
- ✅ Système prêt pour test/déploiement

**Bon test! 🚀**

