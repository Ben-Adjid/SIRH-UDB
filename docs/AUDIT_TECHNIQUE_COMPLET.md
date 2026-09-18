# RAPPORT D'AUDIT TECHNIQUE COMPLET — SIRH Université Dakar Bourguiba

**Date :** 02 Août 2026
**Auteur :** Revue de code professionnelle (Lead Software Engineer)
**Tech :** Laravel 12 + React 19 + Sanctum + Spatie RBAC + SQLite/MySQL + Tailwind
**Statut :** Rapport d'audit — en attente de validation avant refactoring

---

## 1. ÉTAT ACTUEL DU PROJET

| Critère | Valeur |
|---|---|
| **Note globale** | **5,5/10** _(révisée avec les clarifications métier du 02/08/2026)_ |
| **Avancement réel** | **~58%** _(révisé : paie et congés à refondre, modèle Teacher à simplifier)_ |
| **Ce qui est terminé** | Auth/2FA/SMS, CRUDs référentiels (UFR, départements, matières, salles, années académiques), emplois du temps + examens, devoirs + notes, pointage |
| **Partiellement terminé (à adapter)** | Paie (calcul trop complexe, logique BRS à revoir, bulletins à remplacer), Congés (inutiles pour les profs), Gestion Enseignants (contract_type/base_salary à supprimer) |
| **Ce qui manque** | CI/CD, Docker, Form Requests, API Resources, Events/Jobs, Policies, tests étendus, email via Mailables |
| **À corriger en priorité** | `CURLOPT_SSL_VERIFYPEER=false`, 3 Mailables morts, CompleteSeeder bug, **correction du modèle métier (prof = vacataire, pas de salaire, pas de congés)** |

---

## 1-bis. ⚠️ CLARIFICATIONS MÉTIER (02/08/2026 — Université Dakar Bourguiba)

Ces clarifications ont été obtenues directement de l'université. Elles redéfinissent le modèle métier et impactent l'implémentation existante.

### Règles métier confirmées

| # | Règle | État actuel dans le code | Action |
|---|-------|------------------------|--------|
| R1 | **2 types de profs : Prestataire** (payé à l'heure, `Honoraire = Heures × Taux`) **et Contrat** (CDI/CDD, règles définies par l'école, salaire fixe possible). | Le modèle `Teacher` a `contract_type` et `base_salary`. ✅ Bonne base mais la logique de calcul est trop complexe. | **Simplifier :** le calcul prestataire = heures × taux. Le calcul contrat = base_salary mensuel. Supprimer la complexité inutile (bonus/deduction actuels). |
| R2 | **Un prof n'a pas de salaire, seulement un honoraire** calculé comme `Heures × Taux horaire`. | Le `Payroll` a `base_salary`, `honorarium_amount`, `gross_salary`, `net_salary`. Format bulletin de paie complet. | **Simplifier :** une seule ligne Honoraire = heures × taux. Pas de bulletin complexe. |
| R3 | **Pas de congés pour les profs.** Un prof signale juste une absence ou un retard. **Le personnel admin (RH, comptable, secrétaire) peut avoir des congés.** | Module `Leave` complet pour tout le monde. | **Restreindre :** désactiver le module congés pour le rôle `teacher`. Garder pour `admin`, `hr_manager`, `secretary`, `accountant`. Paramétrable. |
| R4 | **BRS 5 %** retenu uniquement si le montant dépasse **25 000 F**. | Déjà implémenté dans `PayrollController` : `BRS_RATE = 0.05`, `BRS_THRESHOLD = 25000`. ✅ | **Conserver.** Vérifier que le calcul est correct. |
| R5 | **Après BRS → montant brut.** Puis : si brut ≥ 100 000 → **chèque** ; sinon → **espèces**. | Le code a `payment_method` enum et `CHECK_THRESHOLD = 100000`. ✅ Mais `gross_salary` actuel inclut `base + honorarium + brs` — logique différente. | **Recalculer :** `Honoraire = heures × taux`. `BRS = 5% si Honoraire > 25000`. `Brut = Honoraire - BRS`. `Paiement = chèque si Brut ≥ 100000, espèces sinon`. |
| R6 | **Le RH exporte les données vers la comptabilité** (export CSV). | `PayrollController::export()` existe déjà (CSV avec BOM). ✅ | **Conserver.** |
| R7 | **Un prof n'a pas de bulletin de salaire.** | Le frontend génère des bulletins HTML (Dashboard, Payroll). | **Remplacer** par un simple récapitulatif d'honoraires (heures × taux − BRS = net à payer). |
| R8 | **Taux horaires par type d'enseignement :** CM, TD, TP. Valeur par défaut : **5000 F/h**. L'école peut ajuster chaque taux. | `teaching_types` a `hourly_rate`. Les seeders ont des valeurs arbitraires. | **Mettre à jour** le défaut à 5000. Permettre la modification des taux via l'interface admin. |
| R9 | **UFR et Département :** Le site de l'école parle uniquement d'UFR. Le concept "Département" ne semble pas exister côté admin. | UFR (faculté) est parent de Department (département). 2 entités distinctes. | **Proposition :** fusionner en une seule entité `Ufr`. Renommer `departments` → `ufrs`, supprimer l'actuelle table `ufrs`. Si plus tard on découvre qu'il faut une hiérarchie, on pourra la rétablir. |
| R10 | **Le chef de département** est sous l'UFR. | Modèles `Department.head_id` et `Ufr.head_id` existent. ✅ | **Conserver.** |

### Impact sur le plan de refactoring

Ces clarifications métier sont **plus prioritaires** que le refactoring structurel. Avant d'introduire FormRequests/API Resources, il faut **corriger le modèle métier** pour éviter de refactorer du code qui va être supprimé.

**Nouvelles tâches ajoutées au plan (priorité maximale) :**

| # | Tâche | Impact |
|---|-------|--------|
| A | Simplifier le modèle `Teacher` : supprimer `contract_type`, `base_salary`. Tout le monde est vacataire. | Migration + modèle + contrôleurs + frontend |
| B | Simplifier le calcul de paie : Honoraire = heures × taux. Supprimer le calcul permanent. | `PayrollController` (572L → ~200L) |
| C | Revoir la logique BRS / Brut / Net / Mode de paiement | `PayrollController`, modèle `Payroll` |
| D | Supprimer le module Congés (leave_types, leave_balances, leave_requests) ou le limiter au personnel non-enseignant | Migrations, contrôleurs, modèles, frontend |
| E | Remplacer les bulletins de salaire par un récapitulatif d'honoraires simple | Frontend `Dashboard.jsx`, `Payroll.jsx` |
| F | Mettre à jour les taux horaires dans les seeders (Cours=10000, TD=6500) | `UfrAndTeachingTypeSeeder`, `CompleteSeeder` |
| G | Clarifier la relation UFR/Département avec l'utilisateur | Analyse, puis éventuelle fusion ou renommage |

### Note d'avancement révisée

Avec ces clarifications, l'avancement réel tombe à **~55-60%** car :
- Le module Paie doit être refondu (calcul actuel trop complexe/incorrect)
- Le module Congés est partiellement inutile
- La notion de "prof permanent" doit disparaître
- Les bulletins de salaire sont à remplacer

---

## 2. CARTOGRAPHIE COMPLÈTE DU PROJET

```
DIT/
├── .opencode/                    # Configuration opencode (agent IA)
│   ├── plans/Audit-Fixes-44-Items.md  # Correction plan (batch précédent, large part appliqué)
├── backend/                      # Laravel 12 API (131 routes)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/   # 18 contrôleurs (18 000+ lignes total)
│   │   │   │   ├── AuthController.php(551L)  # Login/register/reset/2FA
│   │   │   │   ├── AttendanceController.php(883L) # God controller — pointage
│   │   │   │   ├── PayrollController.php(572L)  # Paie + export CSV
│   │   │   │   ├── TeacherController.php(437L)  # CRUD enseignants + contrats
│   │   │   │   ├── TwoFactorController.php(409L) # Google2FA + SMS
│   │   │   │   ├── ScheduleController.php(399L)  # Emplois du temps + examens
│   │   │   │   ├── LeaveController.php(342L)    # Congés (calcul jours ouvrés ✅)
│   │   │   │   ├── DashboardController.php(342L) # 5 dashboards par rôle
│   │   │   │   ├── HomeworkController.php(302L)  # Devoirs + notes
│   │   │   │   ├── UserController.php(280L)     # Gestion utilisateurs
│   │   │   │   ├── DepartmentController.php(162L)
│   │   │   │   ├── AcademicYearController.php(158L)
│   │   │   │   ├── UfrController.php(123L)
│   │   │   │   ├── SubjectController.php(118L)
│   │   │   │   ├── ClassroomController.php(118L)
│   │   │   │   ├── DocumentController.php(109L)
│   │   │   │   ├── NotificationController.php(81L)
│   │   │   │   ├── TeachingTypeController.php(13L) # Read-only
│   │   │   │   └── Controller.php(8L)          # 🚨 Coquille vide
│   │   │   └── Middleware/ForceHttps.php        # HTTPS + headers sécurité
│   │   ├── Mail/                                # 🚨 3/4 INUTILISÉS !
│   │   │   ├── WelcomeEmail.php                 # ✅ Utilisé 1x (TeacherController)
│   │   │   ├── LeaveStatusUpdated.php           # ❌ Jamais instancié
│   │   │   ├── ResetPasswordLink.php            # ❌ Jamais instancié
│   │   │   └── VerifyEmail.php                  # ❌ Jamais instancié
│   │   ├── Models/ (25 modèles)
│   │   ├── Providers/
│   │   │   ├── AppServiceProvider.php           # 🚨 Coquille vide (register+boot vides)
│   │   │   └── SmsServiceProvider.php           # Binding interface SMS
│   │   └── Services/
│   │       ├── SmsServiceInterface.php          # Interface SMS
│   │       ├── AfricaTalkingSmsService.php      # Prod SMS (Africa's Talking)
│   │       ├── LogSmsService.php                # Dev SMS (log)
│   │       ├── BrevoMailService.php             # 🚨 SSL_VERIFYPEER=false!
│   │       └── NotificationService.php          # Static utility
│   ├── database/
│   │   ├── migrations/ (42 fichiers, toutes Ran ✅)
│   │   ├── seeders/ (6 fichiers)
│   │   │   ├── CompleteSeeder.php(314L)         # 🚨 Bug: 'secretary' référencé non créé
│   │   │   ├── RoleAndPermissionSeeder.php      # Seul créateur du rôle 'secretary'
│   │   │   ├── FakerSeeder.php(432L)            # 🚨 Non idempotent
│   │   │   ├── ScheduleSeeder.php(371L)         # 720 créneaux (!)
│   │   │   ├── UfrAndTeachingTypeSeeder.php     # Avec accents incohérents
│   │   │   └── DatabaseSeeder.php               # N'appelle PAS CompleteSeeder
│   │   └── factories/UserFactory.php            # Seule factory existante
│   ├── routes/
│   │   ├── api.php(228L)                        # 131 routes, toutes role-protégées
│   │   └── web.php(20L)                         # Sert le SPA React
│   ├── config/ (13 fichiers)
│   ├── tests/Feature/ (3 fichiers, 24 tests, 45 assertions)
│   └── vendor/ (installé, OK)
├── frontend/                    # React 19 + Vite + Tailwind
│   ├── src/
│   │   ├── pages/ (22 pages, 9474 lignes total)
│   │   ├── components/ (11 composants)
│   │   ├── hooks/useAnimatedNumber.js
│   │   ├── services/api.js(129L)    # Intercepteur Axios + refresh token queue
│   │   ├── store/authStore.js       # Zustand
│   │   └── utils/ (4 fichiers)
│   ├── package.json, vite.config.js, tailwind.config.js
│   └── dist/ → copier manuellement dans backend/public/spa/
├── config/ (vide)
├── logs/ (vide)
├── docs/uml/
│   ├── class_diagram.puml
│   └── use_case_diagram.puml
└── SIRH_PLAN_COMPLET_A-Z.md        # 🚨 Supprimé du repo (dans historique git)
```

**Directoires inexistants (6 absents critiques) :**
- `app/Events/` → pas de système d'événements
- `app/Jobs/` → pas de jobs asynchrones (tout est synchrone)
- `app/Notifications/` → pas de notifications Laravel natives
- `app/Http/Requests/` → validation inline dans les contrôleurs (pas de FormRequest)
- `app/Http/Resources/` → modèles sérialisés directement (pas d'API Resource)
- `app/Policies/` → 0 policy, 100% middleware role (pas de contrôle par modèle)

---

## 3. ANALYSE DES POINTS FORTS

### Sécurité
- **RBAC complet :** 6 rôles + permissions granulaires, middleware `role:` sur toutes les routes (131)
- **Rate limiting généralisé :** throttle 5-60/min sur toutes les routes sensibles
- **Anti-brute-force multi-niveaux :** cache + middleware throttle + timing attack mitigation (login)
- **2FA Google Authenticator** avec codes de récupération
- **Force HTTPS + HSTS + X-Frame-Options + nosniff** (ForceHttps middleware)
- **Audit logs exhaustifs :** login, login échoué, CRUDs, changements mot de passe...
- **Mots de passe :** BCRYPT_ROUNDS=12, min 12 caractères, regex force forte
- **Anti-énumération :** login et forgot-password ne révèlent pas l'existence des comptes
- **Force change password** au premier login + invalidation de tous les anciens tokens
- **Documents :** stockés sur disque privé (disk local), pas de public/ accessible directement

### Architecture
- **Séparation claire backend/frontend** (API Laravel + SPA React)
- **Intercepteur Axios robuste :** refresh token avec file d'attente de requêtes (subscriber queue)
- **Soft deletes** sur 7 modèles clés (user, teacher, payroll, attendance, leave, homework, document)
- **Transactions DB** sur les opérations critiques (Teacher destroy, Payroll calculate, Attendance code)
- **SMS driver interface** (SmsServiceInterface + 2 implémentations : Africa's Talking, Log) → swap via config
- **Clean separation** entre routes web (SPA) et routes API

### Frontend
- **Lazy loading** de toutes les pages (Suspense + React.lazy)
- **Error Boundary** global
- **Protection des routes par rôle** (ProtectedRoute + GuestRoute + TempTokenRoute)
- **Tous les useEffect ont un cleanup** (AbortController, intervals)
- **Aucun console.log/warn/error** dans le code de production
- **Build et lint passent sans erreur**
- **Zustand store** bien structuré, utilisable hors React (api.js)

### Base de données
- **42 migrations, toutes exécutées**, schéma cohérent
- **Unicité contrainte au niveau DB :** unique(teacher_id, year, month) sur payroll, unique(homework_id, student_id) sur grades...
- **Seeders de données de test** (Faker + Schedule) pour le développement

---

## 4. ANALYSE DES FAIBLESSES

### 🔴 CRITIQUE

1. **`CURLOPT_SSL_VERIFYPEER = false` dans `BrevoMailService.php:41`** — désactive la vérification TLS pour l'API Brevo. Vulnérabilité MITM. **Tous les emails transitent sans vérification de certificat.**

2. **`CompleteSeeder.php:206` — référence le rôle 'secretary' sans le créer.** Si exécuté en standalone (`php artisan db:seed --class=CompleteSeeder`), il crashe. Le rôle 'secretary' n'est créé que par `RoleAndPermissionSeeder`.

3. **`CompleteSeeder::truncate()` incomplet** — tronque users/teachers/departments/subjects/classrooms MAIS pas schedules, attendances, homeworks, grades, payrolls, documents, notifications, leaves. Après troncature, ces tables contiennent des **orphelins** référençant des IDs supprimés.

4. **`FakerSeeder` non idempotent** — utilise `create()` partout sans `firstOrCreate()`. Re-run = exception sur contrainte d'unicité.

5. **3 Mailables inutilisés** (`LeaveStatusUpdated`, `ResetPasswordLink`, `VerifyEmail`) — code mort qui n'est jamais instancié. Tous les emails passent par `BrevoMailService` en direct.

### 🟠 IMPORTANT

6. **0 tests pour 15 des 18 contrôleurs** — Paie, pointage, congés, 2FA, emplois du temps, examens, documents, notifications = 0 couverture.

7. **`AttendanceController.php` (883 lignes, 16 méthodes)** — god controller. Mélange gestion codes, check-in/out, absences, disponibilités, rapports, exports. Violation flagrante de SRP.

8. **Logique de création utilisateur dupliquée 3 fois** (AuthController, TeacherController, UserController) — temp password, SMS, email, assignation rôle. Maintenance cauchemardesque.

9. **Détection de conflits d'emploi du temps dupliquée 3 fois** (ScheduleController::store, update, storeExam) — ~25 lignes identiques copiées.

10. **`addBonus()` et `addDeduction()` (PayrollController) sont 90% identiques** — duplication de code massive. Bug potentiel : `gross_salary` recalculé incorrectement (set à `totalEarnings` au lieu de `base + earnings`).

11. **0 Form Requests, 0 API Resources, 0 Policies** — toute la logique est inline dans les contrôleurs.

12. **Authorisation redondante** — chaque contrôleur refait `auth()->user()->hasRole(...)` alors que le middleware `role:` l'a déjà vérifié. Double vérification inutile.

13. **Pas de rate limiting sur les GET** pour `/academic-years`, `/ufrs`, `/departments`, `/teachers`, `/subjects`, `/classrooms` — endpoints publics qui peuvent être abusés.

### 🟡 MOYEN

14. **`Base Controller.php` vide** (8 lignes, coquille abstraite) — n'apporte rien aux 18 contrôleurs.

15. **Pas de pagination sur `AcademicYear::index()` et `Notification::index()`** — hard limit 50 pour les notifications.

16. **Colonnes ENUM en string** — `audit_logs.action`, `notifications.type`, `schedules.day_of_week`, `schedules.type`, `attendance.justification_status` sont des `string` au lieu d'enum au niveau DB. N'importe quelle valeur peut entrer en base.

17. **Méthodes statiques sur les contrôleurs** (`AuthController::generateTempPassword()`, `NotificationController::send()`) — anti-pattern. Des utilitaires ne devraient pas être sur des contrôleurs.

18. **Injection de dépendances absente** — seul `TwoFactorController` a un constructeur. Les 17 autres résolvent les services en ligne (`app(BrevoMailService::class)` ou `new Service()`).

19. **Migration level enum cassée au rollback** — `_000003 down` drop level sans restaurer l'original, `_000002 down` essaye de le drop à nouveau → crash.

20. **Colonnes redondantes** — `address` et `phone` existent à la fois sur `users` et `teachers`.

### 🔵 FAIBLE

21. **`LogSmsService` logue les mots de passe en clair** en `Log::info()` — si jamais activé en prod accidentellement, credentials dans les logs.

22. **2 imports inutilisés frontend** — `Shield` dans ForgotPassword.jsx, `User` dans AuditLogs.jsx.

23. **Année hardcodée `2026`** dans `Layout.jsx:219` et `Login.jsx:158` — devrait utiliser `new Date().getFullYear()`.

24. **`refreshToken` crée un token nommé `auth-token`** vs `auth_token` partout ailleurs — incohérence cosmétique.

25. **READMEs = templates Laravel/React par défaut** — pas de doc d'installation personnalisée.

26. **Duplication frontend :** `monthNames` (Dashboard + Payroll), regex password (ResetPassword + ForcePasswordChange + Profile), patron search/filter (toutes les pages).

27. **Seulement 1 factory** (UserFactory) sur 25 modèles.

---

## 5. DETTE TECHNIQUE

| Dette | Impact | Pourquoi c'est problématique |
|---|---|---|
| **Tests quasi inexistants** (3/18 controllers) | 🔴 Critique | Aucune garantie de non-régression. La refonte d'un module paie/congés peut casser silencieusement |
| **God controller Attendance (883 L)** | 🟠 Élevé | Impossible à maintenir/tester. Toute modification = risque d'effet de bord |
| **Code dupliqué 3x** (création user, conflits schedule, bonus/deduction) | 🟠 Élevé | Un bug corrigé à un endroit reste présent ailleurs |
| **0 Form Requests** | 🟠 Élevé | Règles de validation éparpillées, non réutilisables, non testables unitairement |
| **0 API Resources** | 🟡 Moyen | Modèles exposés directement → champs internes visibles, shape de réponse non contrôlé |
| **0 Policies** | 🟡 Moyen | Logique d'autorisation dans les contrôleurs, non centralisée |
| **Emails dupliqués** (Mailables vs BrevoMailService) | 🟡 Moyen | Deux systèmes parallèles, risque de désynchronisation des templates |
| **Pas de CI/CD** | 🔴 Critique | Déploiement manuel fragile, pas de tests automatisés |
| **Colonnes string au lieu d'enum** (7 colonnes) | 🟡 Moyen | Pas de contrainte DB → données invalides possibles |
| **Foreign keys `cascadeOnDelete` agressives** | 🟠 Élevé | Supprimer un département = supprime TOUS les schedules/homeworks associés |
| **Static methods sur contrôleurs** | 🔵 Faible | Anti-pattern qui empêche l'injection et le mock |
| **`AppServiceProvider` vide** | 🔵 Faible | Code mort, confusion pour les nouveaux devs |
| **3 Mailables morts** | 🔵 Faible | Code mort, confusion |
| **Année hardcodée `2026`** | 🔵 Faible | Devra être changée manuellement |

---

## 6. PLAN DE REFACTORING

### ⚠️ PHASE 0 — Correction du modèle métier (NOUVEAU — clarifications université)
**Objectif :** Aligner le code sur la réalité du terrain avant tout refactoring structurel.

| Action | Fichiers | Risque | Temps |
|---|---|---|---|
| 0.1 Clarifier les 4 questions restantes avec l'admin (voir liste des 10 questions) | Discussion utilisateur | Nul | 30 min |
| 0.2 Fusionner UFR et Département en une seule entité `Ufr` : renommer `departments` → `ufrs`, supprimer l'ancienne table `ufrs` et la FK `ufr_id` | Migration + `Ufr.php` + `Department.php` → `Ufr.php` + tous les contrôleurs/seeders/frontend qui référencent Department ou Ufr | **Élevé** — impact large, beaucoup de fichiers | 4h |
| 0.3 Restreindre le module Congés aux rôles non-teacher : cacher les routes/pages Leaves pour le rôle `teacher` | `routes/api.php` + `Sidebar.jsx` + `Leaves.jsx` | Faible | 1h |
| 0.4 Refondre le calcul de paie : 2 modes — Prestataire (Honoraire = Heures × Taux) et Contrat (salaire fixe). BRS 5% si > 25000 → Brut → Chèque/Espèces | `PayrollController.php`, `Payroll.php` model | **Élevé** — calcul critique, 2 branches | 4h |
| 0.5 Remplacer les bulletins de salaire complexes par un récapitulatif d'honoraires simple | `Dashboard.jsx`, `Payroll.jsx` | Moyen | 3h |
| 0.6 Mettre les taux horaires par défaut à 5000 F/h dans les seeders + permettre la modification via l'interface | `UfrAndTeachingTypeSeeder.php`, `CompleteSeeder.php`, `TeachingTypeController.php` (ajouter update) | Faible | 1h |

**Tests à exécuter :** `php artisan test` (adapter les tests existants) + `php artisan migrate:fresh --seed` (vérifier que les seeders créent des données cohérentes)

---

### PHASE 1 — Correctifs de sécurité et bugs critiques
**Objectif :** Éliminer les vulnérabilités et bugs bloquants avant toute modification structurelle.

| Action | Fichiers | Risque | Temps |
|---|---|---|---|
| 1.1 Activer `CURLOPT_SSL_VERIFYPEER = true` | `BrevoMailService.php:41` | Aucun (la valeur par défaut de curl est `true`) | 5 min |
| 1.2 Supprimer les 3 Mailables inutilisés | `LeaveStatusUpdated.php`, `ResetPasswordLink.php`, `VerifyEmail.php` | Aucun (jamais instanciés) | 5 min |
| 1.3 Corriger CompleteSeeder (ajouter `secretary` role) | `CompleteSeeder.php` | Faible | 10 min |
| 1.4 Supprimer `auth()->user()->hasRole()` redondant dans tous les contrôleurs | 18 contrôleurs | **Élevé** — si le middleware est mal configuré, l'autorisation est contournée. Vérifier route par route. | 3h |
| 1.5 Ajouter `try/catch` manquant dans ~50% des méthodes | `AcademicYearController`, `DocumentController`, `NotificationController`, `AttendanceController`, `UserController`, `UfrController` | Faible | 2h |
| 1.6 Ajouter rate limiting sur les GET publics | `routes/api.php` | Faible | 30 min |

**Tests à exécuter :** `php artisan test` (24 tests existants doivent tous passer)

---

### PHASE 2 — Extraction des duplications et services
**Objectif :** DRY — éliminer le code dupliqué et créer des services réutilisables.

| Action | Fichiers | Risque | Temps |
|---|---|---|---|
| 2.1 Créer `UserCreationService` (temp password + SMS + email + rôle + audit) | Nouveau + 3 contrôleurs modifiés | **Élevé** — doit reproduire le comportement exact des 3 implémentations | 6h |
| 2.2 Créer `ScheduleConflictService` (check conflit salle + prof) | Nouveau + `ScheduleController` (3 méthodes) | Moyen | 2h |
| 2.3 Fusionner `addBonus/addDeduction` en `addLineItem(type)` | `PayrollController` | **Élevé** — bug de calcul `gross_salary` à corriger en même temps | 3h |
| 2.4 Fusionner `validatePayroll` et `generatePayslip` | `PayrollController` | Moyen — ces méthodes font la même chose | 1h |
| 2.5 Extraction `monthNames` + `passwordRegex` en constantes partagées | Frontend : `constants.js` + 5 fichiers | Faible | 1h |

**Tests à exécuter :** `php artisan test` + test manuel : créer un utilisateur enseignant via les 3 chemins (AuthController, TeacherController, UserController)

---

### PHASE 3 — Refactoring structurel (Controllers → Requests/Resources)
**Objectif :** Introduire Form Requests, API Resources, et découper le god controller.

| Action | Fichiers | Risque | Temps |
|---|---|---|---|
| 3.1 Créer Form Requests pour chaque contrôleur | ~15 nouveaux fichiers + 18 contrôleurs | **Élevé** — les règles de validation doivent être exactement préservées | 8h |
| 3.2 Créer API Resources pour chaque modèle | ~20 nouveaux fichiers + 18 contrôleurs | **Élevé** — les shapes de réponse doivent être préservés | 8h |
| 3.3 Découper `AttendanceController` (16 méthodes) en 3 contrôleurs | `AttendanceCodeController`, `AttendanceController`, `AttendanceReportController` + routes | **Élevé** — impact routes, frontend, permissions | 6h |
| 3.4 Supprimer `Base Controller.php` vide et retirer les `extends Controller` inutiles | `Controller.php` + 18 contrôleurs | Nul | 10 min |

**Tests à exécuter :** `php artisan test` + test d'intégration complet manuel (login → dashboard → chaque page)

---

### PHASE 4 — Base de données et modèles
**Objectif :** Corriger les colonnes enum, les foreign keys agressives, les modèles incomplets.

| Action | Fichiers | Risque | Temps |
|---|---|---|---|
| 4.1 Convertir 7 colonnes `string` en `enum` (migration) | 7 nouvelles migrations | **Élevé** — perte de données si valeurs invalides existent. Nettoyer d'abord. | 3h |
| 4.2 Remplacer `cascadeOnDelete` par `restrictOnDelete` sur departments/subjects/classrooms | 3 migrations + modèles | **Élevé** — change le comportement. Nécessite gestion d'erreur dans les contrôleurs. | 2h |
| 4.3 Ajouter soft deletes sur `departments`, `subjects`, `classrooms`, `ufrs`, `teaching_types` | 1 migration + 5 modèles | Faible | 1h |
| 4.4 Ajouter les casts manquants (8 modèles sans cast) | `Classroom`, `Department`, `Homework`, `LeaveBalance`, `LeaveType`, `Subject` | Faible | 1h |
| 4.5 Ajouter les relations inverses manquantes | `LeaveType`, `Teacher`, `Subject`, `Grade` | Faible | 30 min |
| 4.6 Ajouter les indexes manquants (7 colonnes) | 1 migration | Faible | 1h |
| 4.7 Ajouter les factories pour 15+ modèles | 15 nouveaux fichiers | Faible | 2h |

**Tests à exécuter :** `php artisan migrate:rollback && php artisan migrate` + `php artisan test`

---

### PHASE 5 — Tests et couverture
**Objectif :** Augmenter la couverture de tests de ~8% à ~60%.

| Action | Risque | Temps |
|---|---|---|
| 5.1 Tests unitaires : `UserCreationService`, `ScheduleConflictService`, `BrevoMailService` | Faible | 4h |
| 5.2 Tests Feature : `PayrollController` (calcul permanent, vacataire, bonus, déduction, validation) | Moyen | 6h |
| 5.3 Tests Feature : `AttendanceController` (check-in, check-out, code, export) | Moyen | 6h |
| 5.4 Tests Feature : `LeaveController` (création, validation, solde) | Moyen | 4h |
| 5.5 Tests Feature : `TwoFactorController`, `AcademicYearController`, `ScheduleController` | Moyen | 4h |
| 5.6 Tests frontend : composants critiques (Login, Dashboard, modales CRUD) | Moyen | 6h |

**Tests à exécuter :** `php artisan test --coverage` (cibler >60%)

---

### PHASE 6 — Déploiement et CI/CD
**Objectif :** Rendre le projet déployable automatiquement.

| Action | Risque | Temps |
|---|---|---|
| 6.1 Automatiser le build frontend → `backend/public/spa` (script npm/vite) | Faible | 1h |
| 6.2 Créer `Dockerfile` + `docker-compose.yml` (PHP 8.2 + Nginx + MySQL) | Moyen | 3h |
| 6.3 Créer workflow GitHub Actions (lint + test + build) | Faible | 2h |
| 6.4 Ajouter endpoint `/api/health` | Faible | 15 min |
| 6.5 Créer `README.md` personnalisé avec docs d'installation | Faible | 2h |

---

### PHASE 7 — Nettoyage final
**Objectif :** Dette technique restante, code mort, incohérences.

| Action | Risque | Temps |
|---|---|---|
| 7.1 Supprimer imports inutilisés frontend (`Shield`, `User`) | Nul | 5 min |
| 7.2 Remplacer année hardcodée `2026` par `new Date().getFullYear()` | Nul | 5 min |
| 7.3 Supprimer colonnes redondantes (`address`/`phone` de `teachers`) | **Élevé** — migration + mise à jour du code | 2h |
| 7.4 Corriger nom token `auth-token` → `auth_token` dans `refreshToken` | Nul | 1 min |
| 7.5 Extraire composants frontend réutilisables (`SearchBar`, `EmptyState`) | Faible | 2h |

---

## 7. PRIORISATION

### 🔴 URGENT — Correction du modèle métier (NOUVEAU — clarifications université)
Ces tâches sont prioritaires sur tout le reste. Refactorer du code incorrect est une perte de temps.

| # | Tâche | Temps |
|---|-------|-------|
| B1 | Simplifier `Teacher` : supprimer `contract_type`, `base_salary`, tout le monde est vacataire | 3h |
| B2 | Refondre le calcul de paie : Honoraire = Heures × Taux → BRS 5% si > 25000 → Brut = Honoraire − BRS → Chèque si Brut ≥ 100000 | 4h |
| B3 | Supprimer/limiter le module Congés (inutile pour les profs vacataires) | 3h |
| B4 | Remplacer les bulletins de salaire par un récapitulatif d'honoraires simple | 3h |
| B5 | Mettre à jour les taux horaires dans les seeders (Cours=10000, TD=6500, TP=?) | 30 min |
| B6 | Clarifier avec l'utilisateur : UFR = Département ? Faut-il fusionner les 2 entités ? | 30 min |

### 🚨 URGENT — Sécurité et bugs (avant toute mise en production)
1. Activer `CURLOPT_SSL_VERIFYPEER = true` (Brevo) — **5 min, sécurité critique**
2. Supprimer les 3 Mailables morts — **5 min, code mort**
3. Corriger CompleteSeeder (rôle secretary) — **10 min, bug bloquant**
4. Ajouter rate limiting sur les endpoints GET publics — **30 min, DoS**
5. Remplacer `cascadeOnDelete` par `restrictOnDelete` sur référentiels — **2h, perte de données silencieuse**

### ⚡ IMPORTANT — Maintenabilité et qualité
6. Extraire `UserCreationService` — **6h, 3x duplication**
7. Ajouter Form Requests — **8h, 0 validation réutilisable**
8. Ajouter API Resources — **8h, 0 contrôle de shape**
9. Tests Payroll + Attendance + Leave — **16h, 0 couverture sur modules critiques**
10. Découper AttendanceController — **6h, god controller**

### 👍 OPTIONNEL — Qualité, confort
11. Docker + CI/CD — **8h**
12. Factories pour les modèles — **2h**
13. Composants frontend réutilisables — **4h**
14. Docs README — **2h**

---

## 8. ROADMAP

```
Phase 0 (Correction modèle métier) ─── 2-3 jours ⚠️ PRIORITÉ ABSOLUE
    │     Simplifier Teacher, refondre Paie, supprimer Congés profs
    │     ⚠️ Bloquant : ne pas refactorer du code qui va être supprimé
    │
    └── Phase 1 (Sécurité/Critique) ─── 1 jour
              │     SSL, Mailables morts, CompleteSeeder, rate limiting, cascadeOnDelete
              │
              └── Phase 2 (Extraction DRY) ─── 2-3 jours
                        │
                        └── Phase 3 (Structure) ─── 3-4 jours
                                  │
                                  └── Phase 4 (DB/Models) ─── 2-3 jours
                                            │
                                            └── Phase 5 (Tests) ─── 4-5 jours
                                                      │
                                                      └── Phase 6 (CI/CD) ─── 2 jours
                                                                │
                                                                └── Phase 7 (Nettoyage) ─── 1 jour
```

**Durée totale estimée : 20-25 jours ouvrés** (révisé avec la phase 0 métier)

Ne pas paralléliser les phases : chaque phase dépend de la précédente. La Phase 0 est critique car elle détermine ce qui doit être refactoré ou supprimé dans les phases suivantes.

---

## 9. BONNES PRATIQUES À APPLIQUER PENDANT LE REFACTORING

1. **Un commit par étape atomique** — pouvoir rollbacker individuellement
2. **Toujours exécuter `php artisan test` après chaque modification backend**
3. **Ne jamais modifier la signature des routes API sans mettre à jour le frontend** (les URLs sont hardcodées dans `api.js` et les pages)
4. **Utiliser le pattern Interface + Implémentation** pour tout nouveau service (comme `SmsServiceInterface`)
5. **Ne pas mélanger refactoring structurel et correctifs fonctionnels** dans le même commit
6. **Nommer les Form Requests : `StoreTeacherRequest`, `UpdateScheduleRequest`, etc.**
7. **Nommer les API Resources : `TeacherResource`, `PayrollCollection`, etc.**
8. **Utiliser `firstOrCreate()` pour les seeders** — idempotence obligatoire
9. **Extraire les valeurs magiques en constantes de classe** (`const BRS_RATE = 0.05` → `Config::get('payroll.brs_rate')`)
10. **Toujours `use Illuminate\Support\Facades\Http` plutôt que `curl_*` natif** — testable via `Http::fake()`

---

## 10. VÉRIFICATIONS APRÈS CHAQUE PHASE

| Phase | Vérification |
|---|---|
| **0** | `php artisan migrate:fresh --seed` (seed sans erreur) + `php artisan test` (adapter les tests) + test manuel : créer un prof, calculer son honoraire, vérifier BRS/montant brut/mode de paiement |
| **1** | `php artisan test` (24 tests) + login manuel + création d'un user enseignant |
| **2** | `php artisan test` + création user via 3 chemins + test conflit emploi du temps |
| **3** | `php artisan test` + `php artisan route:list` (vérifier aucune route perdue) + test manuel complet de chaque page |
| **4** | `php artisan migrate:fresh --seed` (seed sans erreur) + `php artisan test` |
| **5** | `php artisan test --coverage` (objectif >60%) |
| **6** | `docker-compose up` + `curl localhost/api/health` + `npm run build` (vérifier spa/) |
| **7** | `npm run lint` + `npm run build` + `php artisan test` |

---

## TABLEAU RÉCAPITULATIF

### Phase 0 — Correction du modèle métier (NOUVEAU)

| # | Tâche | Priorité | Difficulté | Impact | Temps | Dépendance |
|---|-------|----------|------------|--------|-------|-----------|
| B1 | Simplifier Teacher (supprimer contract_type, base_salary) | 🔴 Critique | Moyenne | Critique — change tout le module paie | 3h | — |
| B2 | Refondre calcul paie (Honoraire = Heures × Taux, BRS, Brut, Chèque/Espèces) | 🔴 Critique | Difficile | Critique — cœur du module paie | 4h | B1 |
| B3 | Supprimer/limiter le module Congés pour les profs | 🔴 Critique | Moyenne | Élevé — code inutile à retirer | 3h | B1 |
| B4 | Remplacer bulletins par récapitulatif d'honoraires simple | 🔴 Critique | Moyenne | Élevé — frontend à refaire | 3h | B2 |
| B5 | Mettre à jour les taux horaires dans les seeders | 🔴 Critique | Facile | Moyen | 30 min | B2 |
| B6 | Clarifier UFR = Département ? (fusion si nécessaire) | 🔴 Critique | Incertain | Variable — peut impacter tout le référentiel | 30 min (analyse) + ? | — |

### Phases 1-7 — Sécurité, structure, tests, CI/CD

| # | Tâche | Priorité | Difficulté | Impact | Temps | Dépendance |
|---|-------|----------|------------|--------|-------|-----------|
| 1 | `CURLOPT_SSL_VERIFYPEER = true` | 🔴 Urgent | Facile | Critique | 5 min | — |
| 2 | Supprimer 3 Mailables morts | 🔴 Urgent | Facile | Moyen | 5 min | — |
| 3 | Corriger CompleteSeeder (secretary) | 🔴 Urgent | Facile | Élevé | 10 min | — |
| 4 | Supprimer `hasRole()` redondant | 🔴 Urgent | Moyenne | Élevé | 3h | — |
| 5 | Ajouter `try/catch` manquant | 🔴 Urgent | Facile | Moyen | 2h | — |
| 6 | Rate limiting sur endpoints GET | 🔴 Urgent | Facile | Élevé | 30 min | — |
| 7 | `UserCreationService` | 🟡 Important | Difficile | Élevé | 6h | — |
| 8 | `ScheduleConflictService` | 🟡 Important | Moyenne | Moyen | 2h | — |
| 9 | Fusionner `addBonus/addDeduction` | 🟡 Important | Difficile | Élevé | 3h | B2 |
| 10 | Form Requests (15 fichiers) | 🟡 Important | Difficile | Élevé | 8h | B1-B3 |
| 11 | API Resources (20 fichiers) | 🟡 Important | Difficile | Élevé | 8h | #10 |
| 12 | Découper AttendanceController | 🟡 Important | Difficile | Élevé | 6h | #10, #11 |
| 13 | Colonnes enum DB (7 migrations) | 🟡 Important | Moyenne | Élevé | 3h | — |
| 14 | `cascadeOnDelete` → `restrictOnDelete` | 🟡 Important | Moyenne | Élevé | 2h | — |
| 15 | Soft deletes manquants (5 tables) | 🟡 Important | Facile | Moyen | 1h | — |
| 16 | Casts manquants (8 modèles) | 🟡 Important | Facile | Faible | 1h | — |
| 17 | Tests Payroll + Attendance + Leave | 🟡 Important | Difficile | Critique | 16h | B1-B3, #7-12 |
| 18 | Docker + docker-compose | 👍 Optionnel | Moyenne | Élevé | 3h | — |
| 19 | GitHub Actions CI | 👍 Optionnel | Moyenne | Élevé | 2h | #18 |
| 20 | Build frontend automatisé vers spa/ | 👍 Optionnel | Facile | Moyen | 1h | — |
| 21 | Endpoint `/api/health` | 👍 Optionnel | Facile | Faible | 15 min | — |
| 22 | README personnalisé | 👍 Optionnel | Facile | Moyen | 2h | — |
| 23 | Factories (15 modèles) | 👍 Optionnel | Facile | Faible | 2h | — |
| 24 | Résoudre colonnes redondantes | 👍 Optionnel | Moyenne | Faible | 2h | B1 |
| 25 | Composants frontend réutilisables | 👍 Optionnel | Moyenne | Faible | 4h | B4 |

---

**TOTAL :** 31 tâches (25 initiales + 6 métier), ~80-95 heures de travail estimées.

**⚠️ Rapport en attente de validation — la Phase 0 (correction modèle métier) est la priorité absolue. Aucun refactoring structurel ne doit commencer avant.**

---

## Clarifications reçues (03/08/2026)

| Règle | Détail | Action code |
|---|---|---|
| **UFR vs Département** | Le site de l'école parle d'UFR partout (UFR Sciences et Technologies, UFR Droit, etc.). Le concept "Département" ne semble pas exister côté admin. | **Proposition :** renommer `departments` → `ufrs`, supprimer l'entité `Ufr` actuelle. Une seule entité = UFR. Si plus tard on découvre qu'il faut les 2, on pourra restaurer. |
| **Profs Prestataires** | Payés à l'heure. `Honoraire = Heures × Taux`. Plus d'heures = plus d'argent. | `contract_type = vacataire` → calcul horaire. |
| **Profs Contrat** | Règles différentes, définies par l'école (salaire fixe possible). | `contract_type = CDI/CDD` → `base_salary` fixe. |
| **Taux horaires** | Paramétrables. Défaut : 5000 F/h. L'école peut ajuster par type (CM/TD/TP). | `teaching_types.hourly_rate` avec défaut 5000. L'école modifie via l'interface. |
| **Congés profs** | Aucun. Un prof signale juste absence/retard. | Supprimer le module Leave pour les enseignants. |
| **Congés admin** | Le personnel admin (RH, comptable, secrétaire) **peut** avoir des congés. | Garder le module Leave mais limiter aux rôles non-teacher. Paramétrable. |
| **BRS** | 5% si montant > 25000 F. Après BRS → Brut. Brut ≥ 100000 → Chèque, sinon Espèces. | ✅ Déjà implémenté, à vérifier. |

---

## Questions à poser à l'administration universitaire

*À imprimer ou copier pour votre prochaine réunion.*

| # | Question | Pourquoi c'est important |
|---|----------|------------------------|
| 1 | **UFR et Département :** Est-ce que ce sont deux niveaux distincts (ex: UFR Sciences → Département Informatique, Département Maths) ou un seul niveau (ex: UFR Informatique, UFR Maths) ? | Détermine si on garde 2 tables ou si on fusionne. |
| 2 | **Taux horaires exacts :** Quels sont les taux appliqués pour CM, TD et TP ? Les valeurs par défaut (5000 F/h) sont-elles acceptables en attendant ? | Permet de renseigner les vrais montants. |
| 3 | **Profs contrat :** Un prof en CDI/CDD a-t-il un salaire mensuel fixe OU un volume horaire garanti avec taux horaire ? Comment est calculée sa rémunération ? | Détermine la logique de calcul pour les contractuels. |
| 4 | **Primes et retenues :** En plus du BRS (5%), y a-t-il d'autres retenues ou primes (IPRES, IR, assurance, ancienneté, indemnités) ? | Évite de devoir tout recalculer plus tard. |
| 5 | **Volume horaire par prof :** Y a-t-il un plafond d'heures par mois/année pour un vacataire ? | Permet d'ajouter une validation côté backend. |
| 6 | **Personnel administratif :** Combien de personnels admin utilisent le système (RH, comptable, secrétaire, chef département) ? Ont-ils les mêmes règles de congés que les profs ? | Dimensionne les rôles et les règles de congés. |
| 7 | **Export comptabilité :** La comptabilité attend quel format exact ? CSV (déjà fait) ? PDF ? Un format spécifique avec des colonnes obligatoires ? | Valide ou adapte l'export existant. |
| 8 | **Pointage :** Le code de pointage est-il généré par le responsable et communiqué aux profs, ou chaque prof a-t-il son propre code permanent ? | Valide la logique actuelle (code généré par admin, 6 chiffres, durée limitée). |
| 9 | **Documents :** Quels types de documents l'école souhaite-t-elle stocker pour chaque prof (CV, diplômes, contrat, pièce d'identité...) ? Taille max ? | Permet de configurer les règles d'upload. |
| 10 | **Année académique :** Comment s'appellent les années académiques (ex: "2025-2026") et combien de semestres par an ? Les emplois du temps changent-ils entre S1 et S2 ? | Valide la structure academic_years actuelle. |
