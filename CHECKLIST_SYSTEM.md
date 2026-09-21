# ✅ CHECKLIST SYSTÈME SIRH

## 🔍 PRÉ-DÉMARRAGE

### Infrastructure
- [ ] PHP installé (vérifie: `php -v`)
- [ ] Node.js installé (vérifie: `node -v`)
- [ ] npm installé (vérifie: `npm -v`)
- [ ] Dossier backend existe: `C:\Users\aboub\Desktop\DIT\backend`
- [ ] Dossier frontend existe: `C:\Users\aboub\Desktop\DIT\frontend`

### Fichiers essentiels
- [ ] `backend/.env` existe
- [ ] `backend/database/database.sqlite` existe
- [ ] `frontend/.env` existe
- [ ] `frontend/package.json` existe

### Configuration
- [ ] `frontend/.env` contient: `VITE_API_URL=http://localhost:8000`
- [ ] `frontend/.env` contient: `VITE_DEMO_MODE=true`

---

## 🚀 DÉMARRAGE

### Étape 1: Lancer le Backend
```bash
cd C:\Users\aboub\Desktop\DIT\backend
php artisan serve --port=8000
```

Vérifier:
- [ ] Fenêtre affiche: "Laravel development server started"
- [ ] Message contient: "http://127.0.0.1:8000"
- [ ] Pas d'erreurs PHP/SQL

### Étape 2: Attendre 3-5 secondes
- [ ] Laisser le backend s'initialiser

### Étape 3: Lancer le Frontend (Nouveau terminal)
```bash
cd C:\Users\aboub\Desktop\DIT\frontend
npm run dev
```

Vérifier:
- [ ] Fenêtre affiche: "VITE v5"
- [ ] Message contient: "Local: http://localhost"
- [ ] Port affiché (5173 ou plus)
- [ ] Pas d'erreurs npm/node

---

## 🧪 TESTS BACKEND

### Test 1: Health Check
```bash
curl http://localhost:8000/api/health
```
- [ ] Réponse: `{"status":"ok"}`
- [ ] Code HTTP: 200

### Test 2: Login Admin
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@sirh.local","password":"password"}'
```
- [ ] Réponse contient: `"Connexion réussie"`
- [ ] Réponse contient: `"token"`
- [ ] Code HTTP: 200

### Test 3: Login Comptable
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"compta@sirh.local","password":"password"}'
```
- [ ] Réponse contient: `"Connexion réussie"`

### Test 4: Login Enseignant
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"prof@sirh.local","password":"password"}'
```
- [ ] Réponse contient: `"Connexion réussie"`

### Test 5: Utilisateurs en BD
```bash
cd backend
php artisan tinker --execute="\App\Models\User::count()"
```
- [ ] Affiche: `24`

---

## 🌐 TESTS FRONTEND

### Test 1: Accès à la page
1. [ ] Ouvrir: `http://localhost:5173`
2. [ ] Page charge sans erreurs
3. [ ] Voir le logo UDB
4. [ ] Voir le titre "SIRH"

### Test 2: Éléments visibles
- [ ] Champ Email visible
- [ ] Champ Mot de passe visible
- [ ] Bouton "Accéder au portail" visible
- [ ] 5 boutons rapides visibles (Admin, RH, Comptable, Chef UFR, Enseignant)
- [ ] Message "Mode démo" visible

### Test 3: Interaction avec boutons rapides
1. [ ] Cliquer sur bouton "Comptable"
2. [ ] Le champ Email s'auto-remplit: `compta@sirh.local`
3. [ ] Message toast affiche: "Saisissez le mot de passe..."

### Test 4: Connexion Admin
1. [ ] Email: `admin@sirh.local` (ou cliquer bouton)
2. [ ] Password: `password`
3. [ ] Cliquer "Accéder au portail"
4. [ ] [ ] Redirection vers `/dashboard`
5. [ ] [ ] Page charge correctement
6. [ ] [ ] Voir les stats du dashboard

### Test 5: Connexion Comptable
1. [ ] Logout (si connecté)
2. [ ] Email: `compta@sirh.local`
3. [ ] Password: `password`
4. [ ] Cliquer "Accéder au portail"
5. [ ] [ ] Redirection vers `/dashboard`
6. [ ] [ ] Menu "Paie" visible

### Test 6: Connexion Enseignant
1. [ ] Logout
2. [ ] Email: `prof@sirh.local`
3. [ ] Password: `password`
4. [ ] Cliquer "Accéder au portail"
5. [ ] [ ] Dashboard chargé
6. [ ] [ ] Menu "Pointage" visible

### Test 7: Connexion Chef UFR
1. [ ] Logout
2. [ ] Email: `academic@sirh.local`
3. [ ] Password: `password`
4. [ ] Cliquer "Accéder au portail"
5. [ ] [ ] Dashboard chargé

---

## 🎯 FONCTIONNALITÉS CLÉS

### Pointage (Prof)
1. [ ] Se connecter avec: `prof@sirh.local` / `password`
2. [ ] Cliquer "Pointage"
3. [ ] Voir "5 derniers pointages"
4. [ ] Cliquer "Générer PIN"
5. [ ] [ ] PIN généré (numéro 4 chiffres)
6. [ ] [ ] PIN valide pendant 10 minutes
7. [ ] Cliquer "Check-in"
8. [ ] [ ] Entrer le PIN
9. [ ] [ ] Check-in enregistré

### Paie (Comptable)
1. [ ] Se connecter avec: `compta@sirh.local` / `password`
2. [ ] Cliquer "Paie"
3. [ ] Voir la liste des paies
4. [ ] Voir les colonnes: Brut, BRS, Net
5. [ ] [ ] Cliquer une paie
6. [ ] [ ] Voir détails: Brut, Déductions, Net
7. [ ] [ ] Boutton "Valider" visible si non validée

### Profil
1. [ ] Se connecter avec n'importe quel compte
2. [ ] Cliquer "Mon Profil"
3. [ ] [ ] Voir: Nom, Email, Téléphone, Adresse
4. [ ] [ ] Boutton "Modifier" visible
5. [ ] [ ] Champ "Changer mot de passe" visible

### Admin (Admin seul)
1. [ ] Se connecter avec: `admin@sirh.local` / `password`
2. [ ] Cliquer "Administration" ou "Gestion Utilisateurs"
3. [ ] [ ] Liste des utilisateurs affichée
4. [ ] [ ] Bouttons: Ajouter, Modifier, Supprimer visibles
5. [ ] [ ] Voir les colonnes: Email, Rôle, Status

---

## 🔐 SÉCURITÉ

### Connexion invalide
1. [ ] Email incorrect → Message "Identifiants invalides"
2. [ ] Password incorrect → Message "Identifiants invalides"
3. [ ] Rate limiting (5 tentatives) → Message "Trop de tentatives"

### Déconnexion
1. [ ] Cliquer "Logout"
2. [ ] [ ] Redirection vers `/login`
3. [ ] [ ] localStorage vidé
4. [ ] [ ] Pas d'accès à `/dashboard` sans token

---

## 📊 PERFORMANCE

### Backend
- [ ] Temps réponse `/health`: < 100ms
- [ ] Temps réponse `/auth/login`: < 500ms
- [ ] Pas d'erreurs 500

### Frontend
- [ ] Page charge en < 3 secondes
- [ ] Boutons réactifs (< 200ms click-to-response)
- [ ] Pas d'erreurs console (F12 → Console)

---

## 🐛 DEBUGGING

### Si problèmes:

1. **Backend ne démarre**
   - [ ] Vérifier PHP version: `php -v` (min 8.0)
   - [ ] Vérifier fichier `.env`
   - [ ] Vérifier port 8000 libre: `netstat -an | find "8000"`

2. **Frontend ne démarre**
   - [ ] Vérifier Node version: `node -v` (min 16)
   - [ ] Vérifier npm: `npm -v`
   - [ ] Vérifier fichier `.env`
   - [ ] Vérifier port 5173 libre

3. **Connexion échoue**
   - [ ] Vérifier backend URL dans `.env`
   - [ ] Vérifier utilisateur existe: `php artisan tinker`
   - [ ] Vérifier password avec: `Hash::check('password', user.password)`

4. **Base de données vide**
   - [ ] Recréer: `php artisan migrate:fresh --seed`
   - [ ] Vérifier: `sqlite backend/database/database.sqlite` (existence)

---

## ✅ VALIDATION FINALE

### Tous les tests passent?
- [ ] Oui → **SYSTÈME OPÉRATIONNEL** ✅
- [ ] Non → Voir "Debugging" et relancer tests

---

## 📝 RÉSUMÉ

| Élément | Status | Notes |
|---------|--------|-------|
| Infrastructure | ⬜ | À vérifier |
| Backend API | ⬜ | À tester |
| Frontend | ⬜ | À vérifier |
| Connexion | ⬜ | À tester |
| Pointage | ⬜ | À tester |
| Paie | ⬜ | À tester |
| Profil | ⬜ | À tester |
| Admin | ⬜ | À tester |
| Sécurité | ⬜ | À vérifier |

**Remplir les cases au fur et à mesure des tests! ✅**

---

## 🎉 CONCLUSION

Quand tous les ✅ sont cochés:

**LE SYSTÈME SIRH EST 100% OPÉRATIONNEL**

Prêt pour:
- ✅ Tests utilisateur
- ✅ Formation personnel
- ✅ Déploiement
- ✅ Production

---

**Bon test! 🚀**
