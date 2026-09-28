# 🔐 PLAN DE SÉCURISATION - SYSTÈME D'ACTIVATION DE COMPTE

**Date:** 28/09/2026  
**Faille:** Mots de passe temporaires envoyés en clair par Email/SMS  
**Solution:** Lien d'activation à usage unique (48h)

---

## 📋 FICHIERS À MODIFIER/CRÉER

### Backend (Laravel)

#### 1. Migrations
- ✅ `2026_09_28_000001_add_account_activation_to_users_table.php` (CRÉÉ)
  - Ajoute: `is_activated (boolean)`, `activated_at (timestamp)`

#### 2. Services (à créer/modifier)
- `ActivationMailService.php` (NOUVEAU)
  - Envoie un e-mail sans mot de passe
  - Contient le lien d'activation avec token

- `UserCreationService.php` (À MODIFIER)
  - Ne plus générer/retourner de mot de passe temporaire
  - Générer un token d'activation
  - Appeler ActivationMailService

- `AfricaTalkingSmsService.php` (À MODIFIER)
  - Supprimer l'envoi du mot de passe
  - Message générique: "Consultez votre email pour activer votre compte"

#### 3. Controllers (à créer/modifier)
- `AuthController.php` (À MODIFIER)
  - Nouvelle méthode: `activation(Request $request)` 
  - Vérifier le token
  - Définir le mot de passe
  - Marquer comme activé
  - Invalider le token

- `UserController.php` (À MODIFIER - si création d'utilisateur)
  - Utiliser UserCreationService

#### 4. Mailable (à modifier/créer)
- `AccountActivationMail.php` (NOUVEAU)
  - Lien d'activation sécurisé
  - Pas de mot de passe
  - Lien valable 48h
  - Message générique

#### 5. Config
- `config/auth.php`
  - Vérifier: `passwords.users.expire = 2880` (48h)

### Frontend (React)

#### 1. Pages (à créer)
- `ActivationPage.jsx` (NOUVEAU)
  - URL: `/activer-compte?token=...&email=...`
  - Champs: nouveau mot de passe, confirmation
  - Indicateur des règles (12 chars, MAJ, min, chiffre, symbole)
  - POST `/api/auth/activation`

#### 2. Routes
- Ajouter: `/activer-compte` (non-authentifiée)

#### 3. Composants (à modifier)
- Réutiliser `PasswordChangeForm.jsx` pour le formulaire

### Tests (à créer)
- `AccountActivationTest.php`
  - Création sans mot de passe retourné
  - E-mail sans secret
  - Activation réussit une fois
  - Token expiré échoue
  - Mot de passe faible refusé
  - Throttle 6/1 minute
  - Compte non activé ne peut se connecter

---

## 🔧 IMPLÉMENTATION ÉTAPES

### Phase 1: Database (IMMÉDIAT)
```bash
php artisan migrate
```

### Phase 2: Backend (Services & Controllers)
1. Créer `ActivationMailService`
2. Modifier `UserCreationService`
3. Modifier `AfricaTalkingSmsService`
4. Créer `AccountActivationMail`
5. Modifier `AuthController` (+ méthode activation)
6. Modifier `AuthController::login` (vérifier is_activated)

### Phase 3: Frontend (React)
1. Créer `ActivationPage.jsx`
2. Ajouter route `/activer-compte`
3. Créer `useActivation()` hook

### Phase 4: Audit & Logs
1. Journaliser: "compte créé", "lien activé", "compte activé"
2. Supprimer logs contenant des mots de passe

### Phase 5: Tests
1. Créer tests Pest/PHPUnit
2. Vérifier flux complet: création → email → activation → login

### Phase 6: Nettoyage
1. Supprimer code ancien (temporaire, plainPassword, etc.)
2. Mettre à jour textes UI

---

## ✅ CRITÈRES D'ACCEPTATION

- [ ] Aucun mot de passe en email/SMS
- [ ] Aucun mot de passe en API response
- [ ] Lien activation valable 48h, usage unique
- [ ] Compte non activé = pas de login
- [ ] Flux complet fonctionne
- [ ] Tests passent
- [ ] Audit journal complet

---

## 📌 VARIABLES D'ENV À VÉRIFIER

```env
APP_URL=http://localhost:3000  # Frontend URL
MAIL_FROM_ADDRESS=noreply@sirh.udb.local
MAIL_FROM_NAME="SIRH UDB"
ACTIVATION_LINK_EXPIRE=2880  # 48h en minutes
```

---

## 📊 TIMELINE

- Migration: 5 min
- Backend: 1-2 h
- Frontend: 30 min
- Tests: 1 h
- **Total: ~3-4 heures**

