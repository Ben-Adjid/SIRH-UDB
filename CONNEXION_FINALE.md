# 🔑 GUIDE DE CONNEXION FINAL - SIRH

## ✅ PROBLÈME RÉSOLU

**Erreur**: "Mot de passe incorrect"  
**Cause**: Le `.env` du frontend n'existait pas (mode démo désactivé)  
**Solution**: Créé `.env` avec `VITE_DEMO_MODE=true`  
**Status**: ✅ **CORRIGÉ**

---

## 🚀 ACCÈS IMMÉDIAT

### 1️⃣ **Ouvrir le Frontend**
```
🌐 http://localhost:5176
```
*(Port peut changer si déjà utilisé - vérifie le terminal)*

### 2️⃣ **Cliquer sur un Rôle Rapide**
Les boutons s'affichent automatiquement:
- 🔴 **Admin**
- 🔵 **Responsable RH**
- 🟠 **Comptable**
- 🟣 **Chef d'UFR**
- 🟢 **Enseignant**

### 3️⃣ **Mot de Passe**
```
Pour TOUS les comptes: password
```

### 4️⃣ **Cliquer "Accéder au portail"**
✅ Vous êtes connecté!

---

## 📋 TOUS LES COMPTES DISPONIBLES

### Comptes Principaux:
```
admin@sirh.local        → Admin         | password
rh@sirh.local           → RH Manager    | password
secretaire@sirh.local   → Secretary     | password
academic@sirh.local     → Academic Head | password
compta@sirh.local       → Accountant    | password
prof@sirh.local         → Teacher       | password
```

### Enseignants Supplémentaires:
```
prof1@sirh.local        → Teacher       | password
prof2@sirh.local        → Teacher       | password
prof3@sirh.local        → Teacher       | password
+ 15 autres (voir base de données)
```

---

## 🔧 PARAMÈTRES ACTUELS

### Backend
- ✅ **URL**: http://localhost:8000
- ✅ **Status**: Running
- ✅ **Passwords**: "password" pour TOUS

### Frontend
- ✅ **URL**: http://localhost:5176 (ou autre port)
- ✅ **Status**: Running
- ✅ **Demo Mode**: Activé (QUICK_ACCOUNTS visibles)
- ✅ **.env**: Créé et configuré

### Database
- ✅ **Status**: 24 utilisateurs
- ✅ **Passwords**: Hashés avec bcrypt
- ✅ **Validation**: ✓ "password" accepté

---

## 🧪 TEST RAPIDE

### Via Interface Web (Recommandé):
1. Ouvrir http://localhost:5176
2. Cliquer "Comptable"
3. Email: `compta@sirh.local` ← auto-rempli
4. Password: `password` ← visible en mode démo
5. Cliquer "Accéder au portail"
6. ✅ **SUCCÈS!**

### Via API (Test Technique):
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@sirh.local","password":"password"}'
```

**Réponse attendue**:
```json
{
  "message": "Connexion réussie",
  "user": {...},
  "token": "...",
  "requires_2fa": false,
  "must_change_password": false
}
```

---

## 📊 STATUTS DE VÉRIFICATION

| Élément | Status | Détails |
|---------|--------|---------|
| Backend | ✅ | http://localhost:8000 running |
| Frontend | ✅ | http://localhost:5176 running |
| `.env` Frontend | ✅ | `VITE_DEMO_MODE=true` créé |
| `.env` Backend | ✅ | Passwords = "password" |
| Database | ✅ | 24 users, passwords hashés |
| API Login | ✅ | Endpoint fonctionnel |
| Mode Démo | ✅ | QUICK_ACCOUNTS visibles |

---

## ❌ SI ÇA NE MARCHE TOUJOURS PAS

### 1. Vérifier le port du Frontend
Le port peut changer si occupé. Regarder dans le terminal:
```
Local: http://localhost:XXXX
```
Utiliser ce port, pas 5176.

### 2. Vérifier l'API
```bash
curl http://localhost:8000/api/auth/login \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@sirh.local","password":"password"}'
```
Devrait retourner `"Connexion réussie"`.

### 3. Vérifier les Passwords en Base
```bash
cd backend
php artisan tinker --execute="
\$u = \App\Models\User::where('email', 'admin@sirh.local')->first();
echo 'Password check: ' . (\Illuminate\Support\Facades\Hash::check('password', \$u->password) ? '✅' : '❌');
"
```

### 4. Vérifier le `.env` Frontend
```bash
cat frontend/.env
```
Doit contenir:
```
VITE_DEMO_MODE=true
VITE_API_URL=http://localhost:8000
```

### 5. Redémarrer les Services
```bash
# Terminal 1 - Backend
cd backend && php artisan serve --port=8000

# Terminal 2 - Frontend  
cd frontend && npm run dev
```

---

## 🎯 RÉSUMÉ FINAL

✅ **Système MAINTENANT 100% FONCTIONNEL**

**Ce qu'il faut faire**:
1. Ouvrir http://localhost:5176 (ou le port affiché)
2. Cliquer un rôle (ex: "Comptable")
3. Entrer password: `password`
4. Cliquer "Accéder au portail"
5. ✅ Vous êtes connecté!

**Password Unifié**: `password` pour TOUS

**24 utilisateurs disponibles**: admin, rh, compta, academic, secretary, + 19 teachers

---

**🚀 Bonne connexion! Système opérationnel!**

