# 🔴 POURQUOI JE NE PEUX PAS ME CONNECTER?

## ✅ ÉTAPE 1: VÉRIFIER QUE LE BACKEND EST DÉMARRÉ

Ouvrez un terminal et exécutez:
```bash
cd C:\Users\aboub\Desktop\DIT\backend
php artisan serve
```

**Vous devez voir:**
```
Starting Laravel development server: http://127.0.0.1:8000
```

**Si vous voyez ça = ✅ Backend OK**

❌ **Si ça ne marche pas:**
- Vérifier que PHP est installé: `php --version`
- Vérifier que les dépendances sont installées: `composer install`
- Vérifier le port 8000 n'est pas utilisé: `netstat -ano | findstr :8000`

---

## ✅ ÉTAPE 2: VÉRIFIER LES CREDENTIALS

**Tous les comptes utilisent le mot de passe: `password`**

**Comptes disponibles:**
```
admin@sirh.local
rh@sirh.local
secretaire@sirh.local
academic@sirh.local
compta@sirh.local
prof@sirh.local
diop@sirh.local
sarr@sirh.local
```

❌ **Erreur courante:** Copier-coller avec espaces
✅ **Solution:** Entrer manuellement sans espaces

---

## ✅ ÉTAPE 3: VÉRIFIER LA BASE DE DONNÉES

Ouvrir un terminal et exécuter:
```bash
cd C:\Users\aboub\Desktop\DIT\backend
php artisan tinker --execute="
\$user = \App\Models\User::where('email', 'admin@sirh.local')->first();
echo 'User exists: ' . (\$user ? 'YES' : 'NO') . \"\n\";
echo 'User status: ' . \$user?->status . \"\n\";
echo 'Password correct: ' . (\Illuminate\Support\Facades\Hash::check('password', \$user->password) ? 'YES' : 'NO') . \"\n\";
"
```

**Vous devez voir:**
```
User exists: YES
User status: active
Password correct: YES
```

❌ **Si "NO":** La base de données n'a pas les bonnes données
✅ **Solution:** Réinitialiser:
```bash
php artisan migrate:fresh
php artisan db:seed --class=CompleteSeeder
```

---

## ✅ ÉTAPE 4: TESTER L'API DIRECTEMENT

Ouvrir PowerShell et exécuter:
```powershell
$body = @{
    email = "admin@sirh.local"
    password = "password"
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:8000/api/auth/login" `
    -Method Post `
    -Headers @{"Content-Type"="application/json"} `
    -Body $body
```

**Vous devez voir:**
```json
{
    "message": "Connexion réussie",
    "user": {...},
    "token": "..."
}
```

❌ **Si erreur 401:** Password incorrecte
❌ **Si erreur 500:** Erreur serveur (vérifier logs)
❌ **Si pas de réponse:** Backend pas démarré

---

## 🔧 CHECKLIST DE DÉBOGAGE

Cocher chaque case:

- [ ] Backend démarré (`php artisan serve`)
- [ ] Frontend démarré (`npm run dev`)
- [ ] Email correct (sans espaces)
- [ ] Password correct: `password` (exactement)
- [ ] Pas de CAPS LOCK
- [ ] Base de données initialisée
- [ ] Port 8000 libre

---

## ⚠️ ERREURS COURANTES

### **Erreur 401 Unauthorized**
```
❌ CAUSE: Password incorrect
✅ SOLUTION: Utiliser exactement: password
```

### **Erreur 500 Internal Server Error**
```
❌ CAUSE: Erreur serveur PHP
✅ SOLUTION: Vérifier logs: storage/logs/laravel.log
```

### **Erreur "Connection refused"**
```
❌ CAUSE: Backend pas démarré
✅ SOLUTION: Exécuter php artisan serve
```

### **Erreur "Port already in use"**
```
❌ CAUSE: Autre Laravel sur port 8000
✅ SOLUTION: php artisan serve --port=8001
```

### **Erreur "SQLSTATE: Database not found"**
```
❌ CAUSE: DB pas créée ou pas connectée
✅ SOLUTION: 
   php artisan migrate
   php artisan db:seed --class=CompleteSeeder
```

---

## 📝 RÉINITIALISATION COMPLÈTE

Si rien ne marche, faire une réinitialisation:

```bash
# 1. Aller au backend
cd C:\Users\aboub\Desktop\DIT\backend

# 2. Supprimer la DB et la recréer
php artisan migrate:fresh

# 3. Créer les données de test
php artisan db:seed --class=CompleteSeeder

# 4. Tester
php artisan tinker --execute="
echo 'Users: ' . \App\Models\User::count() . \"\n\";
"
```

✅ Vous devez voir: `Users: 8`

---

## 🎯 RÉSUMÉ RAPIDE

**Si vous ne pouvez pas vous connecter:**

1. ✅ **Vérifier backend démarré** → `php artisan serve`
2. ✅ **Vérifier credentials** → `admin@sirh.local` / `password`
3. ✅ **Vérifier base de données** → Voir étape 3 ci-dessus
4. ✅ **Réinitialiser si besoin** → `php artisan migrate:fresh && php artisan db:seed --class=CompleteSeeder`

---

## 📞 BESOIN D'AIDE?

Envoyez-moi:
1. **L'erreur exacte** que vous voyez
2. **La réponse** de l'API test (Étape 4)
3. **Ce que vous avez déjà essayé**

Je peux vous aider! 🤝
