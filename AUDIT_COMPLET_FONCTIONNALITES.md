# AUDIT COMPLET DES FONCTIONNALITÉS - SIRH DAKAR v2.1.0

> **Université Dakar Bourguiba (UDB)** — Système Intégré de Gestion des Ressources Humaines
>
> Date d'audit : 11/08/2026

---

## TABLE DES MATIÈRES

1. [Présentation Générale](#1-présentation-générale)
2. [Acteurs & Rôles](#2-acteurs--rôles)
3. [Fonctionnalités Transversales (Tous Acteurs)](#3-fonctionnalités-transversales-tous-acteurs)
4. [Fonctionnalités par Acteur](#4-fonctionnalités-par-acteur)
   - [4.1 Administrateur (admin)](#41-administrateur-admin)
   - [4.2 Responsable Administratif (hr_manager)](#42-responsable-administratif-hr_manager)
   - [4.3 Secrétaire Administratif (secretary)](#43-secrétaire-administratif-secretary)
   - [4.4 Chef d'UFR (academic_manager)](#44-chef-dufr-academic_manager)
   - [4.5 Comptable (accountant)](#45-comptable-accountant)
   - [4.6 Enseignant (teacher)](#46-enseignant-teacher)
5. [Matrice Croisée Acteurs × Fonctionnalités](#5-matrice-croisée-acteurs--fonctionnalités)
6. [Modules Détaillés](#6-modules-détaillés)
7. [Sécurité & Conformité](#7-sécurité--conformité)
8. [Données de Référence (Seeds)](#8-données-de-référence-seeds)
9. [Architecture Technique](#9-architecture-technique)

---

## 1. PRÉSENTATION GÉNÉRALE

### 1.1 Identité de l'application

| Propriété | Valeur |
|-----------|--------|
| **Nom** | SIRH Dakar |
| **Version** | 2.1.0 |
| **Organisation** | Université Dakar Bourguiba (UDB) |
| **Type** | Application Web Full-Stack (SPA + API REST) |
| **Langue** | Français |
| **Fuseau horaire** | Africa/Dakar |

### 1.2 Stack technique

| Couche | Technologie | Version |
|--------|-------------|---------|
| **Backend** | PHP / Laravel | 12.x sur PHP 8.2 |
| **Frontend** | React (Vite) | 19.x / Vite 8.x |
| **CSS** | TailwindCSS | 3.x (frontend) / 4.x (backend) |
| **Base de données** | MySQL | 8.0 |
| **Serveur Web** | Nginx | stable-alpine |
| **Conteneurisation** | Docker Compose | 3 services |
| **State Management** | Zustand | 5.x |
| **Routing** | React Router DOM | 7.x |
| **HTTP Client** | Axios | 1.x |
| **Icônes** | Lucide React | 0.x |
| **API Auth** | Laravel Sanctum | 4.x |
| **RBAC** | spatie/laravel-permission | 6.x |
| **2FA** | pragmarx/google2fa-laravel | 3.x |
| **Email** | phpmailer + Brevo API | 7.x |
| **SMS** | Africa's Talking API | custom |

### 1.3 Structure des containers Docker

| Service | Image | Port | Rôle |
|---------|-------|------|------|
| `backend` | PHP 8.2-FPM (Dockerfile) | 9000 (interne) | API Laravel |
| `nginx` | nginx:stable-alpine | 80 | Reverse proxy + SPA statique |
| `mysql` | mysql:8.0 | 3306 | Base de données |

### 1.4 Structure des répertoires

```
DIT/
├── backend/                  # API Laravel 12
│   ├── app/
│   │   ├── Http/Controllers/Api/   # 16 contrôleurs API
│   │   ├── Http/Middleware/        # Middleware (ForceHttps)
│   │   ├── Http/Requests/          # 10 Form Requests de validation
│   │   ├── Http/Resources/         # 14 API Resources de transformation
│   │   ├── Mail/                   # Email de bienvenue
│   │   ├── Models/                 # 19 modèles Eloquent
│   │   ├── Providers/              # Service providers
│   │   └── Services/               # 6 classes de service
│   ├── config/                     # 11 fichiers de configuration
│   ├── database/
│   │   ├── factories/              # 15 factories pour les tests
│   │   ├── migrations/             # 34 fichiers de migration
│   │   └── seeders/                # 7 seeders
│   ├── routes/
│   │   ├── api.php                 # 240 lignes - TOUTES les routes API
│   │   ├── console.php
│   │   └── web.php
│   └── tests/                      # 11 tests feature + 2 tests unitaires
├── frontend/                  # SPA React 19
│   └── src/
│       ├── components/             # 9 composants UI réutilisables
│       ├── hooks/                  # 1 hook personnalisé
│       ├── pages/                  # 21 composants de page
│       ├── services/               # Client Axios avec intercepteurs
│       ├── store/                  # Store Zustand (auth)
│       └── utils/                  # 5 modules utilitaires
├── nginx/                     # Configuration Nginx
├── docs/                      # Documentation + diagrammes UML
└── docker-compose.yml         # Orchestration
```

---

## 2. ACTEURS & RÔLES

### 2.1 Définition des rôles

| Code Rôle | Libellé | Description |
|-----------|---------|-------------|
| `admin` | Administrateur | Super-admin avec toutes les permissions. Gère le système, les utilisateurs et tout le reste. |
| `hr_manager` | Responsable Administratif | Gère les enseignants, le pointage, les congés, les rapports. |
| `secretary` | Secrétaire Administratif | Mêmes droits que le Responsable Administratif. |
| `academic_manager` | Chef d'UFR | Gère l'emploi du temps, les matières, les salles, les devoirs, les examens. |
| `accountant` | Comptable | Gère la paie/honoraires, les rapports financiers. |
| `teacher` | Enseignant | Consulte son emploi du temps, ses cours/devoirs, ses honoraires, ses documents. |

### 2.2 Comptes de test (seedés)

| Email | Rôle | Mot de passe |
|-------|------|-------------|
| `admin@sirh.local` | Administrateur | Défini dans .env |
| `rh@sirh.local` | Responsable Administratif | Défini dans .env |
| `secretaire@sirh.local` | Secrétaire Administratif | Défini dans .env |
| `academic@sirh.local` | Chef d'UFR | Défini dans .env |
| `compta@sirh.local` | Comptable | Défini dans .env |
| `prof1@sirh.local` | Enseignant | Défini dans .env |
| `prof2@sirh.local` | Enseignant | Défini dans .env |
| `prof3@sirh.local` | Enseignant | Défini dans .env |

---

## 3. FONCTIONNALITÉS TRANSVERSALES (TOUS ACTEURS)

Ces fonctionnalités sont accessibles à tous les utilisateurs authentifiés, quel que soit leur rôle.

### 3.1 Authentification

| Fonctionnalité | Route | Méthode | Description |
|---------------|-------|---------|-------------|
| Connexion | `/auth/login` | POST | Login email + mot de passe. Rate-limit : 10/min. |
| Déconnexion | `/auth/logout` | POST | Révocation du token. Rate-limit : 30/min. |
| Rafraîchissement token | `/auth/refresh` | POST | Renouvellement automatique du bearer token. Rate-limit : 10/min. |
| Profil utilisateur | `/auth/me` | GET | Récupération des infos de l'utilisateur connecté. |
| Mise à jour profil | `/auth/profile` | PUT | Modification nom, email, téléphone. Rate-limit : 20/min. |
| Changement mot de passe | `/auth/change-password` | POST | Changement du mot de passe courant. Rate-limit : 5/min. |
| Changement forcé mot de passe | `/auth/force-change-password` | POST | Pour les nouveaux comptes avec must_change_password=true. Rate-limit : 5/min. |
| Envoi vérification email | `/auth/send-verification-email` | POST | Renvoi du lien de vérification. Rate-limit : 3/min. |
| Mot de passe oublié | `/auth/forgot-password` | POST | Envoi lien de réinitialisation par email (Brevo API). Rate-limit : 5/min. (Public) |
| Réinitialisation mot de passe | `/auth/reset-password` | POST | Réinitialisation via token reçu par email. Rate-limit : 5/min. (Public) |
| Vérification email | `/auth/verify-email` | POST | Validation du lien de vérification. Rate-limit : 3/min. (Public) |

**Détails techniques :**
- Authentification par **bearer token** (Laravel Sanctum)
- Token stocké dans `localStorage`
- **Auto-refresh** implémenté via intercepteur Axios : en cas de 401, appel automatique à `/auth/refresh` avec file d'attente pour les requêtes concurrentes
- **Protection anti brute-force** : 5 tentatives max par email, verrouillage 5 minutes
- **Exigences mot de passe** : 12+ caractères, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial
- **Changement forcé de mot de passe** : format temporaire `UDB` + 4 chiffres
- Session driver : `database`

### 3.2 Sécurité - Double Authentification (2FA)

| Fonctionnalité | Route | Méthode | Description |
|---------------|-------|---------|-------------|
| Obtenir le secret 2FA | `/auth/2fa/secret` | GET | Génération du secret TOTP + QR code |
| Activer 2FA | `/auth/2fa/enable` | POST | Activation avec code TOTP. Rate-limit : 5/min. |
| Désactiver 2FA | `/auth/2fa/disable` | POST | Désactivation. Rate-limit : 5/min. |
| Vérification login 2FA | `/auth/2fa/verify-login` | POST | Vérification après login si 2FA activé. Rate-limit : 10/min. (Public) |
| Code de récupération 2FA | `/auth/2fa/verify-recovery` | POST | Connexion via code de récupération. Rate-limit : 5/min. (Public) |

**Détails techniques :**
- Google TOTP 2FA (`pragmarx/google2fa-laravel`)
- Codes de récupération (8 codes uniques)
- Vérification téléphone par SMS (Africa's Talking API)

### 3.3 Vérification Téléphone

| Fonctionnalité | Route | Méthode | Description |
|---------------|-------|---------|-------------|
| Envoyer code SMS | `/auth/phone/send-verification` | POST | Envoi code de vérification par SMS. Rate-limit : 3/min. |
| Vérifier téléphone | `/auth/phone/verify` | POST | Validation du code SMS reçu. Rate-limit : 5/min. |

### 3.4 Dashboard

| Fonctionnalité | Route | Méthode | Acteurs |
|---------------|-------|---------|---------|
| Dashboard Admin | `/dashboards/admin` | GET | admin |
| Dashboard RH | `/dashboards/hr` | GET | admin, hr_manager, secretary |
| Dashboard Enseignant | `/dashboards/teacher` | GET | teacher |
| Dashboard Académique | `/dashboards/academic` | GET | admin, academic_manager |
| Dashboard Comptable | `/dashboards/accountant` | GET | admin, accountant |

**5 dashboards distincts** avec indicateurs de performance (KPIs), graphiques et tendances adaptés à chaque rôle.

### 3.5 Notifications

| Fonctionnalité | Route | Méthode | Description |
|---------------|-------|---------|-------------|
| Lister les notifications | `/notifications` | GET | Toutes les notifications de l'utilisateur. Rate-limit : 60/min. |
| Marquer tout comme lu | `/notifications/mark-all-read` | POST | Lecture groupée. Rate-limit : 30/min. |
| Marquer une comme lue | `/notifications/{id}/read` | PATCH | Lecture individuelle. Rate-limit : 60/min. |
| Supprimer une notification | `/notifications/{id}` | DELETE | Suppression. Rate-limit : 30/min. |

**Types de notifications :**
- Notification de bienvenue à la création de compte
- Mise à jour du statut de congé
- Événements système
- Alertes diverses

### 3.6 Pages communes

| Page | Route frontend | Description |
|------|---------------|-------------|
| Profil utilisateur | `/profile` | Affichage et modification du profil (nom, email, téléphone, avatar en initiales) |
| Configuration 2FA | `/2fa-setup` | Activation/désactivation de la double authentification avec QR code |
| Vérification téléphone | `/phone-verify` | Validation du numéro de téléphone par SMS |
| Page d'accès refusé | `/access-denied` | Affichée si l'utilisateur tente d'accéder à une page non autorisée |
| Notifications | `/notifications` | Centre de notifications complet |

---

## 4. FONCTIONNALITÉS PAR ACTEUR

---

### 4.1 ADMINISTRATEUR (admin)

**32 permissions** - Accès complet à toutes les fonctionnalités.

#### 4.1.1 Gestion des Utilisateurs

| Action | Route API | Description | Permissions requises |
|--------|-----------|-------------|---------------------|
| Lister les utilisateurs | `GET /auth/users` | Liste de tous les comptes utilisateurs | — (route group : admin, hr, secretary) |
| Créer un utilisateur | `POST /auth/users` | Création avec rôle, email, nom | `users.create` |
| Modifier un utilisateur | `PUT /auth/users/{user}` | Modification rôle, nom, email, statut (admin only) | `users.edit` |
| Supprimer un utilisateur | `DELETE /auth/users/{user}` | Suppression d'un compte (admin only) | `users.delete` |
| Activer/Désactiver | `PATCH /auth/users/{user}/toggle-status` | Bascule statut actif/inactif (admin only) | `users.edit` |
| Journal d'audit | `GET /auth/audit-logs` | Consultation des logs d'audit | `users.view_audit_logs` |
| Vider le journal d'audit | `DELETE /auth/audit-logs` | Purge complète des logs (admin only) | `users.view_audit_logs` |

**Pages frontend :**
- `/users` — Gestion des comptes et rôles
- `/audit-logs` — Journal d'audit (admin uniquement)

#### 4.1.2 Gestion des Enseignants

| Action | Route API | Permissions |
|--------|-----------|-------------|
| Lister | `GET /teachers` | — |
| Voir un enseignant | `GET /teachers/{teacher}` | — |
| Créer un enseignant | `POST /teachers` | `teachers.create` |
| Modifier un enseignant | `PUT /teachers/{teacher}` | `teachers.edit` |
| Supprimer un enseignant | `DELETE /teachers/{teacher}` | `teachers.edit` |
| Archiver un enseignant | `PATCH /teachers/{teacher}/archive` | `teachers.edit` |
| Ajouter un contrat | `POST /teachers/{teacher}/contracts` | `teachers.create` |
| Modifier un contrat | `PUT /teachers/{teacher}/contract` | `teachers.edit` |
| Rapport enseignants | `GET /reports/teachers` | `reports.generate` |

**Page frontend :** `/teachers`

#### 4.1.3 Pointage / Présences

| Action | Route API | Permissions |
|--------|-----------|-------------|
| Pointer entrée | `POST /attendance/check-in` | `attendance.mark_own` |
| Pointer sortie | `POST /attendance/check-out` | `attendance.mark_own` |
| Code pointage actif | `GET /attendance/active-code` | — |
| Lister les pointages | `GET /attendance` | — |
| Signaler une absence | `POST /attendance/report-absence` | `attendance.report_absence` |
| Déclarer disponibilité | `POST /attendance/declare-availability` | `availability.declare` |
| Vue hebdomadaire | `GET /attendance/weekly` | — |
| Statistiques | `GET /attendance/stats` | — |
| Export | `GET /attendance/export` | — |
| Disponibilités | `GET /availabilities` | — |
| Générer code pointage | `POST /attendance/generate-code` | `attendance.track` |
| Valider absence | `PATCH /attendance/{id}/validate-absence` | `attendance.validate_absence` |
| Modifier pointage | `PATCH /attendance/{id}` | `attendance.track` |
| Enregistrer absence | `POST /attendance/record-absence` | `attendance.record_absence` |
| Rapport de présence | `GET /reports/attendance` | `reports.generate` |

**Page frontend :** `/attendance`

#### 4.1.4 Congés & Absences

| Action | Route API | Permissions |
|--------|-----------|-------------|
| Créer une demande | `POST /leaves` | — |
| Historique des congés | `GET /leaves/history` | — |
| Soldes de congés | `GET /leaves/balances` | — |
| Types de congés | `GET /leave-types` | `leave_types.manage` |
| Liste des demandes | `GET /leaves` | — |
| Approuver/Rejeter | `PATCH /leaves/{leave}/status` | — |
| Mettre à jour solde | `POST /leaves/update-balance` | — |

**Page frontend :** `/leaves`

#### 4.1.5 Documents

| Action | Route API | Description |
|--------|-----------|-------------|
| Lister documents | `GET /teachers/{teacher}/documents` | Documents d'un enseignant |
| Télécharger document | `GET /documents/{document}/download` | Téléchargement |
| Uploader document | `POST /teachers/{teacher}/documents` | Ajout de document |
| Supprimer document | `DELETE /documents/{document}` | Suppression |

**Page frontend :** `/documents`

#### 4.1.6 Emploi du Temps & Devoirs

| Action | Route API | Permissions |
|--------|-----------|-------------|
| CRUD emplois du temps | `apiResource /schedules` | `schedules.create` |
| CRUD devoirs | `apiResource /homeworks` | `homeworks.publish` |
| Noter un devoir | `POST /homeworks/{id}/grades` | — |
| Voir les notes | `GET /homeworks/{id}/grades` | — |
| Approuver un devoir | `PATCH /homeworks/{id}/approve` | `homeworks.validate` |
| Terminer un devoir | `PATCH /homeworks/{id}/complete` | — |
| Annuler un devoir | `PATCH /homeworks/{id}/cancel` | — |
| Reporter un devoir | `PATCH /homeworks/{id}/reschedule` | — |

**Pages frontend :** `/schedules`, `/homeworks`

#### 4.1.7 Gestion UFR (Départements)

| Action | Route API | Permissions |
|--------|-----------|-------------|
| Lister | `GET /ufrs` | — |
| Voir une UFR | `GET /ufrs/{ufr}` | — |
| Créer une UFR | `POST /ufrs` | `departments.manage` / `departments.create` |
| Modifier une UFR | `PUT /ufrs/{ufr}` | `departments.manage` |
| Supprimer une UFR | `DELETE /ufrs/{ufr}` | `departments.manage` |

**Page frontend :** `/ufrs`

#### 4.1.8 Gestion Matières & Salles

| Action | Route API | Permissions |
|--------|-----------|-------------|
| CRUD Matières | `GET/POST/PUT/DELETE /subjects` | `subjects.manage` |
| CRUD Salles | `GET/POST/PUT/DELETE /classrooms` | `classrooms.manage` |

**Pages frontend :** `/subjects`, `/classrooms`

#### 4.1.9 Gestion Années Académiques

| Action | Route API | Permissions |
|--------|-----------|-------------|
| Lister | `GET /academic-years` | — |
| Créer | `POST /academic-years` | — |
| Modifier | `PUT /academic-years/{id}` | — |
| Supprimer | `DELETE /academic-years/{id}` | — |
| Activer | `POST /academic-years/{id}/activate` | — |
| Archiver semestre | `POST /academic-years/{id}/archive-semester` | — |
| Restaurer semestre | `POST /academic-years/{id}/restore-semester` | — |
| Supprimer archivé | `DELETE /academic-years/{id}/delete-archived` | — |

#### 4.1.10 Types d'Enseignement

| Action | Route API | Permissions |
|--------|-----------|-------------|
| Lister | `GET /teaching-types` | — |
| Créer | `POST /teaching-types` | — |
| Modifier | `PUT /teaching-types/{id}` | — |
| Supprimer | `DELETE /teaching-types/{id}` | — |

**Types :** CM (Cours Magistral), TD (Travaux Dirigés), TP (Travaux Pratiques), chacun avec son taux horaire.

#### 4.1.11 Examens

| Action | Route API | Permissions |
|--------|-----------|-------------|
| Créer un examen | `POST /exams` | `exams.plan` |
| Lister les examens | `GET /exams` | `courses.view` |

#### 4.1.12 Gestion des Honoraires (Paie)

| Action | Route API | Permissions |
|--------|-----------|-------------|
| Calculer la paie | `POST /payroll/calculate` | `payroll.calculate` |
| Ajouter ligne | `POST /payroll/{id}/line-item` | `payroll.add_bonus` / `payroll.add_deduction` |
| Valider la paie | `POST /payroll/{id}/validate` | `payroll.validate` |
| Marquer comme payé | `POST /payroll/{id}/mark-as-paid` | `payroll.generate_payslip` |
| Exporter la paie | `GET /payroll/export` | `payroll.export` |
| Lister les paies | `GET /payroll` | — |
| Voir une paie | `GET /payroll/{payroll}` | — |

**Page frontend :** `/payroll`

#### 4.1.13 Rapports

| Rapport | Route API | Permissions |
|---------|-----------|-------------|
| Rapport enseignants | `GET /reports/teachers` | `reports.generate` |
| Rapport présence | `GET /reports/attendance` | `reports.generate` |

---

### 4.2 RESPONSABLE ADMINISTRATIF (hr_manager)

**6 permissions** — Gestion RH, enseignants, pointage, rapports.

#### 4.2.1 Menu latéral

| Groupe | Liens |
|--------|-------|
| Principal | Tableau de bord |
| Ressources Humaines | Enseignants, Pointage, Congés & Absences, Documents, Emploi du Temps, Cours & Devoirs |
| Administration | Comptes & Rôles |

#### 4.2.2 Liste complète des actions

| Module | Actions disponibles |
|--------|-------------------|
| **Dashboard** | Tableau de bord RH avec KPIs spécifiques |
| **Utilisateurs** | Lister et créer des utilisateurs (pas de modification/suppression, pas de création d'admin) |
| **Enseignants** | Créer (`teachers.create`), modifier (`teachers.edit`), supprimer, archiver, gérer les contrats |
| **Pointage** | Lister, stats, export, générer code, valider absences (`attendance.track`, `attendance.validate_absence`), modifier/enregistrer absences (`attendance.record_absence`) |
| **Congés** | Consulter, approuver/rejeter, mettre à jour les soldes |
| **Documents** | Lister, télécharger, uploader, supprimer |
| **Emploi du Temps** | CRUD complet |
| **Devoirs** | CRUD complet, noter, voir les notes |
| **Rapports** | Générer rapports enseignants et présence (`reports.generate`) |
| **Audit** | Consulter les logs d'audit |
| **Paie** | Consulter les paies (consultation uniquement) |
| **Profil** | Gestion du profil, 2FA, téléphone |

---

### 4.3 SECRÉTAIRE ADMINISTRATIF (secretary)

**6 permissions** — Identiques au Responsable Administratif.

| Différence avec hr_manager | Détail |
|---------------------------|--------|
| **Permissions back-end** | Strictement identiques (`$secretaryPermissions = $hrPermissions`) |
| **Interface** | Même menu, mêmes pages accessibles |
| **Nuance métier** | La secrétaire exécute les tâches administratives déléguées par le responsable RH |

---

### 4.4 CHEF D'UFR (academic_manager)

**7 permissions** — Gestion académique, emploi du temps, devoirs, examens.

#### 4.4.1 Menu latéral

| Groupe | Liens |
|--------|-------|
| Principal | Tableau de bord |
| Ressources Humaines | Enseignants (consultation), Pointage, Congés & Absences |
| UFR | UFR, Matières, Salles, Emploi du Temps, Cours & Devoirs |

#### 4.4.2 Liste complète des actions

| Module | Actions disponibles | Permissions |
|--------|-------------------|-------------|
| **Dashboard** | Tableau de bord académique | — |
| **UFR** | CRUD complet des UFR/départements | `departments.manage` / `departments.create` |
| **Matières** | CRUD complet des matières | `subjects.manage` |
| **Salles** | CRUD complet des salles | `classrooms.manage` |
| **Années Académiques** | CRUD complet, activation, archivage semestre | — |
| **Types Enseignement** | CRUD complet (CM/TD/TP) | — |
| **Emploi du Temps** | CRUD complet | `schedules.create`, `schedules.assign_teachers`, `schedules.plan` |
| **Examens** | Créer et lister les examens | `exams.plan`, `courses.create` |
| **Devoirs** | CRUD complet + workflow (approuver, terminer, annuler, reporter) | `homeworks.validate` |
| **Pointage** | Lister, stats, export, générer code, valider absences | — |
| **Congés** | Consulter ses propres congés, demander | — |
| **Enseignants** | Consultation uniquement | — |
| **Profil** | Gestion du profil, 2FA, téléphone | — |

---

### 4.5 COMPTABLE (accountant)

**6 permissions** — Paie, honoraires, rapports financiers.

#### 4.5.1 Menu latéral

| Groupe | Liens |
|--------|-------|
| Principal | Tableau de bord |
| Finance | Gestion Honoraires, Enseignants (consultation), Pointage (consultation) |
| Congés | Congés & Absences (consultation/demande) |

#### 4.5.2 Liste complète des actions

| Module | Actions disponibles | Permissions |
|--------|-------------------|-------------|
| **Dashboard** | Tableau de bord comptable avec KPIs financiers | — |
| **Paie / Honoraires** | Calculer, ajouter lignes bonus/déduction, valider, marquer payé, exporter | `payroll.calculate`, `payroll.generate_payslip`, `payroll.add_bonus`, `payroll.add_deduction`, `payroll.validate`, `payroll.export` |
| **Pointage** | Lister, stats, export (consultation uniquement) | — |
| **Enseignants** | Consultation de la liste des enseignants | — |
| **Congés** | Consulter ses propres congés, demander | — |
| **Profil** | Gestion du profil, 2FA, téléphone | — |

**Détail calcul honoraires :**
- **Honoraires** = heures_travaillées × taux_horaire
- **BRS** (taxe) : 5% au-delà de 25 000 XOF
- **Déductions/Bonus** : Ajout manuel possible
- **Mode de paiement** : Espèces (< 100 000 XOF), Chèque (≥ 100 000 XOF)
- **Workflow** : Brouillon → Calculé → Validé → Payé

---

### 4.6 ENSEIGNANT (teacher)

**7 permissions** — Consultation de son espace personnel uniquement.

#### 4.6.1 Menu latéral

| Groupe | Liens |
|--------|-------|
| Principal | Tableau de bord |
| Mon Espace | Mon Emploi du Temps, Mes Cours & Devoirs, Présences, Mes Honoraires, Mes Documents, Mon Profil |

#### 4.6.2 Liste complète des actions

| Module | Actions disponibles | Permissions |
|--------|-------------------|-------------|
| **Dashboard** | Tableau de bord enseignant avec KPIs personnels | — |
| **Emploi du Temps** | Voir son emploi du temps uniquement (read-only) | `schedules.view` |
| **Cours & Devoirs** | Consulter les devoirs, voir les notes, publier des devoirs | `courses.view`, `homeworks.publish` |
| **Pointage** | Pointer entrée/sortie, signaler absence, déclarer disponibilité, consulter l'historique | `attendance.mark_own`, `attendance.report_absence`, `availability.declare` |
| **Honoraires** | Consulter ses propres fiches de paie (read-only) | — |
| **Documents** | Consulter et télécharger ses propres documents | `notifications.receive` |
| **Notifications** | Recevoir et consulter les notifications | `notifications.receive` |
| **Profil** | Gestion du profil, 2FA, téléphone | — |

**Note importante :** L'enseignant n'a AUCUN accès à :
- La gestion des utilisateurs
- La gestion des autres enseignants
- La gestion des UFR, matières, salles
- La gestion des congés (réservé au personnel administratif)
- La validation de paie
- Les rapports
- Le journal d'audit

---

## 5. MATRICE CROISÉE ACTEURS × FONCTIONNALITÉS

| Fonctionnalité | Admin | RH | Secrétaire | Chef UFR | Comptable | Enseignant |
|---------------|-------|-----|-----------|----------|-----------|------------|
| **Tableau de bord** | ✅ | ✅ (RH) | ✅ (RH) | ✅ (Acad) | ✅ (Compta) | ✅ (Perso) |
| **Création utilisateurs** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Modification utilisateurs** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Suppression utilisateurs** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Activation/Désactivation** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Journal d'audit** | ✅ (Voir+Purger) | ✅ (Voir) | ✅ (Voir) | ❌ | ❌ | ❌ |
| **CRUD Enseignants** | ✅ | ✅ | ✅ | ❌ (Voir) | ❌ (Voir) | ❌ |
| **Contrats enseignants** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Archivage enseignant** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **CRUD UFR** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **CRUD Matières** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **CRUD Salles** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **CRUD Années Académiques** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **CRUD Types Enseignement** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **CRUD Emploi du Temps** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ (Voir) |
| **CRUD Devoirs** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ (Voir+Publier) |
| **Noter un devoir** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Workflow devoir** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Créer examens** | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Pointer entrée/sortie** | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Code pointage** | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Signaler absence** | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Déclarer disponibilité** | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Vue hebdomadaire** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Stats pointage** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Export pointage** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Générer code pointage** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Valider absence** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Modifier/Enregistrer absence** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Demandes congés** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Historique congés** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Soldes congés** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Approuver/Rejeter congé** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Mettre à jour solde congé** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Calculer paie** | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Lignes bonus/déduction** | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Valider paie** | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Marquer payé** | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Exporter paie** | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Consulter paie** | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ (sienne) |
| **Documents (Voir+Télécharger)** | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ (siens) |
| **Documents (Uploader+Supprimer)** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Rapport enseignants** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Rapport présence** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Profil** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **2FA** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Vérification téléphone** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Notifications** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 6. MODULES DÉTAILLÉS

### 6.1 MODULE AUTHENTIFICATION

**Contrôleur :** `AuthController.php`
**Modèle :** `User.php` (SoftDeletes, HasRoles, HasApiTokens)

| Champ | Description |
|-------|-------------|
| `name` | Nom complet |
| `email` | Email (unique) |
| `password` | Mot de passe hashé (bcrypt) |
| `status` | Actif / Inactif |
| `must_change_password` | Changement forcé au 1er login |
| `phone` | Numéro de téléphone |
| `phone_verified_at` | Date vérification téléphone |
| `address` | Adresse postale |
| `email_verified_at` | Date vérification email |
| `two_factor_secret` | Secret TOTP (Google 2FA) |
| `two_factor_recovery_codes` | Codes de récupération 2FA |
| `two_factor_enabled_at` | Date activation 2FA |
| `last_login_at` | Dernière connexion |
| `deleted_at` | Soft delete |

**Flux de connexion :**
1. Saisie email + mot de passe
2. Vérification anti brute-force (5 tentatives max)
3. Si 2FA activé → Token temporaire → Redirection `/2fa-verify` → Vérification TOTP → Token définitif
4. Si `must_change_password` = true → Token temporaire → Redirection `/force-password-change` → Nouveau mot de passe
5. Si email non vérifié → Proposition de renvoi

### 6.2 MODULE ENSEIGNANTS

**Contrôleur :** `TeacherController.php`
**Modèle :** `Teacher.php` (SoftDeletes)

| Champ | Description |
|-------|-------------|
| `user_id` | Référence compte utilisateur |
| `ufr_id` | UFR/Département de rattachement |
| `employee_id` | Matricule employé |
| `phone` | Téléphone |
| `mobile` | Portable |
| `address` | Adresse |
| `hire_date` | Date d'embauche |
| `volume_hours` | Volume horaire contractuel |
| `hourly_rate` | Taux horaire (F XOF) |
| `status` | Statut (actif/archivé/inactif) |

**Contrat enseignant :** Embauche (hire_date), taux horaire (hourly_rate), volume horaire (volume_hours). Un enseignant = un contrat.

### 6.3 MODULE POINTAGE / PRÉSENCES

**Contrôleur :** `AttendanceController.php` (917 lignes - le plus gros contrôleur)
**Modèles :** `Attendance.php`, `AttendanceCode.php`

| Champ Attendance | Description |
|-----------------|-------------|
| `user_id` | Utilisateur qui pointe |
| `teacher_id` | Enseignant associé |
| `attendance_date` | Date du pointage |
| `check_in_time` | Heure d'arrivée |
| `check_out_time` | Heure de départ |
| `status` | present / late / absent |
| `hours_worked` | Heures travaillées |
| `notes` | Notes |
| `is_justified` | Absence justifiée ? |
| `justification_status` | Statut de la justification |
| `justified_by` | Qui a justifié |
| `justified_at` | Quand |
| `justification_notes` | Notes de justification |

**Code de pointage :** Code généré par admin/RH/Chef UFR, avec date d'expiration. Les enseignants l'utilisent pour pointer.

**Seuil de retard :** > 15 minutes après l'heure prévue = statut "late"

### 6.4 MODULE CONGÉS

**Contrôleur :** `LeaveController.php`
**Modèles :** `LeaveRequest.php`, `LeaveType.php`, `LeaveBalance.php`

| Type de congé | Jours/an |
|--------------|---------|
| Congé annuel | Variable |
| Congé maladie | Variable |
| Congé exceptionnel | Variable |

**Réservé au personnel administratif (non enseignant).**
**Workflow :** Demande → En attente → Approuvée / Rejetée

### 6.5 MODULE PAIE / HONORAIRES

**Contrôleur :** `PayrollController.php`
**Modèles :** `Payroll.php`, `PayrollDetail.php`

| Champ Payroll | Description |
|--------------|-------------|
| `teacher_id` | Enseignant concerné |
| `year` | Année |
| `month` | Mois |
| `honorarium_amount` | Montant honoraires |
| `brs_amount` | Montant BRS (taxe) |
| `gross_salary` | Salaire brut |
| `deductions` | Déductions |
| `net_salary` | Salaire net |
| `payment_method` | cash / check |
| `status` | draft / calculated / validated / paid |
| `validated_by` | Validateur |
| `validated_at` | Date validation |
| `calculation_details` | Détail calcul (JSON) |

**Workflow :** Brouillon → Calculé → Validé → Payé

### 6.6 MODULE EMPLOI DU TEMPS

**Contrôleur :** `ScheduleController.php`
**Service :** `ScheduleConflictService.php` (détection de conflits)
**Modèle :** `Schedule.php` (SoftDeletes)

| Champ | Description |
|-------|-------------|
| `teacher_id` | Enseignant |
| `subject_id` | Matière |
| `teaching_type_id` | Type (CM/TD/TP) |
| `classroom_id` | Salle |
| `academic_year_id` | Année académique |
| `day_of_week` | Jour de la semaine |
| `start_time` | Heure début |
| `end_time` | Heure fin |
| `start_date` | Date début (optionnel) |
| `end_date` | Date fin (optionnel) |
| `level` | Niveau (L1-L3, M1-M2) |
| `semester` | S1 ou S2 |
| `type` | course / exam |
| `status` | Statut |
| `is_archived` | Archivé ? |

**Contrainte d'unicité :** Une salle ne peut pas avoir 2 cours le même jour/même créneau/même semestre.

### 6.7 MODULE DEVOIRS

**Contrôleur :** `HomeworkController.php`
**Modèles :** `Homework.php`, `Grade.php`

| Champ Homework | Description |
|---------------|-------------|
| `teacher_id` | Enseignant |
| `subject_id` | Matière |
| `classroom_id` | Salle |
| `title` | Titre |
| `description` | Description |
| `scheduled_date` | Date prévue |
| `start_time` | Heure début |
| `end_time` | Heure fin |
| `duration_minutes` | Durée |
| `status` | planned / published / completed / approved / cancelled |
| `max_score` | Note maximale |

**Workflow :** Planifié → Publié → Terminé → Approuvé
**Annulation :** possible via `cancel`
**Report :** possible via `reschedule`

### 6.8 MODULE DOCUMENTS

**Contrôleur :** `DocumentController.php`
**Modèle :** `Document.php`

| Champ | Description |
|-------|-------------|
| `teacher_id` | Enseignant propriétaire |
| `name` | Nom du document |
| `type` | Type (CV, contrat, etc.) |
| `file_path` | Chemin stockage |
| `mime_type` | Type MIME |
| `file_size` | Taille |
| `uploaded_by` | Qui a uploadé |

### 6.9 MODULE AUDIT

**Modèle :** `AuditLog.php`

| Champ | Description |
|-------|-------------|
| `user_id` | Qui a effectué l'action |
| `action` | Action (login, logout, create, update, delete...) |
| `model` | Modèle concerné |
| `model_id` | ID du modèle |
| `old_values` | Anciennes valeurs (JSON) |
| `new_values` | Nouvelles valeurs (JSON) |
| `ip_address` | Adresse IP |
| `user_agent` | Navigateur/OS |

**Actions auditées :** Connexion, déconnexion, création/modification/suppression utilisateur, mise à jour profil, changement mot de passe, modifications paie, etc.

### 6.10 MODÈLES DE DONNÉES (19 modèles)

| # | Modèle | Table | SoftDeletes |
|---|--------|-------|-------------|
| 1 | User | users | ✅ |
| 2 | Teacher | teachers | ✅ |
| 3 | Ufr | ufrs | ✅ |
| 4 | Subject | subjects | ✅ |
| 5 | Classroom | classrooms | ✅ |
| 6 | Schedule | schedules | ✅ |
| 7 | Homework | homeworks | ✅ |
| 8 | Grade | grades | ✅ |
| 9 | Attendance | attendances | ✅ |
| 10 | AttendanceCode | attendance_codes | ❌ |
| 11 | LeaveRequest | leave_requests | ✅ |
| 12 | LeaveType | leave_types | ❌ |
| 13 | LeaveBalance | leave_balances | ❌ |
| 14 | Payroll | payrolls | ✅ |
| 15 | PayrollDetail | payroll_details | ❌ |
| 16 | Notification | notifications | ❌ |
| 17 | Document | documents | ❌ |
| 18 | AuditLog | audit_logs | ❌ |
| 19 | AcademicYear | academic_years | ❌ |
| 20 | TeachingType | teaching_types | ❌ |

---

## 7. SÉCURITÉ & CONFORMITÉ

### 7.1 Mesures de sécurité implémentées

| Mesure | Détail |
|--------|--------|
| **HTTPS forcé** | Middleware ForceHttps |
| **Rate limiting** | Sur toutes les routes sensibles (login: 10/min, register: 15/min, forgot-password: 5/min, etc.) |
| **Anti brute-force** | 5 tentatives par email, verrouillage 5 minutes |
| **Protection CORS** | Configuré via `config/cors.php` |
| **Mots de passe forts** | 12+ caractères, majuscule, minuscule, chiffre, spécial |
| **Hashage mots de passe** | bcrypt |
| **2FA** | Google TOTP + codes de récupération + SMS |
| **Tokens Sanctum** | Bearer token avec expiration configurable |
| **Auto-refresh tokens** | Intercepteur Axios avec file d'attente |
| **Soft Deletes** | Sur 11 modèles (données jamais supprimées physiquement) |
| **Journal d'audit** | Toutes les actions sensibles tracées |
| **Permissions RBAC** | 32 permissions granulaires via spatie/laravel-permission |
| **Validation des requêtes** | 10 Form Requests dédiées |
| **Sessions en base** | Session driver = database |
| **Sécurité Nginx** | Headers X-Frame-Options, X-Content-Type-Options |

### 7.2 Permissions granulaires (32 permissions)

| # | Permission | Description |
|---|-----------|-------------|
| 1 | `users.create` | Créer des utilisateurs |
| 2 | `users.edit` | Modifier des utilisateurs |
| 3 | `users.delete` | Supprimer des utilisateurs |
| 4 | `users.view_audit_logs` | Voir les logs d'audit |
| 5 | `departments.manage` | Gérer les départements/UFR |
| 6 | `subjects.manage` | Gérer les matières |
| 7 | `classrooms.manage` | Gérer les salles |
| 8 | `attendance_codes.manage` | Gérer les codes de pointage |
| 9 | `payroll_settings.manage` | Gérer les paramètres de paie |
| 10 | `leave_types.manage` | Gérer les types de congés |
| 11 | `payroll.calculate` | Calculer la paie |
| 12 | `payroll.generate_payslip` | Générer les fiches de paie |
| 13 | `payroll.add_bonus` | Ajouter des bonus |
| 14 | `payroll.add_deduction` | Ajouter des déductions |
| 15 | `payroll.validate` | Valider la paie |
| 16 | `payroll.export` | Exporter la paie |
| 17 | `teachers.create` | Créer des enseignants |
| 18 | `teachers.edit` | Modifier des enseignants |
| 19 | `attendance.track` | Suivre les présences |
| 20 | `attendance.record_absence` | Enregistrer les absences |
| 21 | `attendance.validate_absence` | Valider les absences |
| 22 | `reports.generate` | Générer des rapports |
| 23 | `schedules.create` | Créer des emplois du temps |
| 24 | `schedules.assign_teachers` | Assigner des enseignants |
| 25 | `schedules.plan` | Planifier des créneaux |
| 26 | `homeworks.validate` | Valider des devoirs |
| 27 | `courses.create` | Créer des cours |
| 28 | `exams.plan` | Planifier des examens |
| 29 | `departments.create` | Créer des départements |
| 30 | `schedules.view` | Voir les emplois du temps |
| 31 | `courses.view` | Voir les cours |
| 32 | `homeworks.publish` | Publier des devoirs |
| 33 | `attendance.mark_own` | Pointer ses propres présences |
| 34 | `attendance.report_absence` | Signaler une absence |
| 35 | `availability.declare` | Déclarer sa disponibilité |
| 36 | `notifications.receive` | Recevoir des notifications |

---

## 8. DONNÉES DE RÉFÉRENCE (SEEDS)

### 8.1 Seeders disponibles

| Seeder | Contenu |
|--------|---------|
| `RoleAndPermissionSeeder.php` | 6 rôles + 36 permissions |
| `TestUsersSeeder.php` | 8 comptes de test |
| `UfrAndTeachingTypeSeeder.php` | 4 UFR + 3 types d'enseignement |
| `FakerSeeder.php` | Données fictives (enseignants, emplois du temps, pointages, paies, congés, documents) |
| `ScheduleSeeder.php` | Emplois du temps complets sur 4 UFR (60 salles) |
| `DatabaseSeeder.php` | Orchestration des seeders ci-dessus |

### 8.2 UFR seedées

| Code | Nom | Description |
|------|-----|-------------|
| UFR-GJ | Génie Juridique | Droit et sciences juridiques |
| UFR-GGE | Génie Gestion Économique | Économie et gestion |
| UFR-ST | Sciences et Technologies | Sciences et informatique |
| UFR-LA | Langues et Affaires | Langues et commerce international |

### 8.3 Types d'enseignement seedés

| Code | Nom | Description |
|------|-----|-------------|
| CM | Cours Magistral | Cours en amphithéâtre |
| TD | Travaux Dirigés | Travaux en groupe |
| TP | Travaux Pratiques | Travaux pratiques en labo |

### 8.4 Matières (60+ seedées)

Réparties sur les 4 UFR avec niveaux L1, L2, L3, M1, M2, crédits horaires et codes.
Exemples : Droit Constitutionnel (UFR-GJ), Microéconomie (UFR-GGE), Algorithmique (UFR-ST), Business English (UFR-LA).

### 8.5 Salles (60+ seedées)

Types : Amphithéâtre, Salle de classe, Laboratoire. Avec capacité et localisation.

---

## 9. ARCHITECTURE TECHNIQUE

### 9.1 Communication Frontend ↔ Backend

```
Navigateur → Nginx (:80) → /api/* → Backend PHP-FPM (:9000)
                          → /spa/* → Fichiers statiques (React build)
```

### 9.2 Intercepteur Axios (Auto-refresh token)

```javascript
// Frontend: src/services/api.js
// En cas de 401:
// 1. Vérifie si une requête de refresh est déjà en cours
// 2. Si oui, met en file d'attente
// 3. Sinon, appelle /auth/refresh
// 4. En cas de succès, rejoue toutes les requêtes en attente
// 5. En cas d'échec, déconnecte et redirige vers /login
```

### 9.3 Gestion d'état (Zustand)

```javascript
// Frontend: src/store/authStore.js
// États gérés:
// - user: informations utilisateur connecté
// - token: bearer token
// - isAuthenticated: booléen
// Actions: login(), logout(), fetchUser(), updateProfile()
```

### 9.4 Routes API (240 lignes, ~90 endpoints)

| Catégorie | Nombre d'endpoints |
|-----------|-------------------|
| Authentification | 11 |
| Double authentification (2FA) | 5 |
| Vérification téléphone | 2 |
| Dashboards | 5 |
| Utilisateurs | 6 |
| Notifications | 4 |
| Enseignants | 8 |
| Documents | 4 |
| Pointage / Présences | 14 |
| Congés | 6 |
| UFR | 4 |
| Matières | 4 |
| Salles | 4 |
| Années Académiques | 8 |
| Types d'Enseignement | 4 |
| Emploi du Temps | 5 |
| Devoirs | 8 |
| Examens | 2 |
| Paie / Honoraires | 8 |
| Rapports | 2 |
| Audit | 2 |
| Santé | 1 |

### 9.5 Middleware de route

| Middleware | Usage |
|-----------|-------|
| `auth:sanctum` | Toutes les routes protégées |
| `role:admin\|hr_manager\|secretary` | Restriction par rôle |
| `throttle:X,Y` | Rate limiting (X requêtes / Y minutes) |

### 9.6 Form Requests (Validation)

1. `LoginRequest` — Validation login
2. `RegisterRequest` — Validation création utilisateur
3. `UpdateUserRequest` — Validation modification utilisateur
4. `UpdateProfileRequest` — Validation mise à jour profil
5. `ChangePasswordRequest` — Validation changement mot de passe
6. `ForceChangePasswordRequest` — Validation changement forcé
7. `StoreTeacherRequest` — Validation création enseignant
8. `UpdateTeacherRequest` — Validation modification enseignant
9. `StoreScheduleRequest` — Validation création emploi du temps
10. `UpdateScheduleRequest` — Validation modification emploi du temps

---

## RÉCAPITULATIF

### Pages frontend par acteur

| Acteur | Pages accessibles |
|--------|------------------|
| **Admin** | Dashboard, Teachers, Ufrs, Subjects, Classrooms, Schedules, Homeworks, Attendance, Leaves, Payroll, Users, AuditLogs, Documents, Notifications, Profile, 2FA-Setup, Phone-Verify |
| **Responsable RH** | Dashboard, Teachers, Attendance, Leaves, Documents, Schedules, Homeworks, Users, Notifications, Profile, 2FA-Setup, Phone-Verify |
| **Secrétaire** | Dashboard, Teachers, Attendance, Leaves, Documents, Schedules, Homeworks, Users, Notifications, Profile, 2FA-Setup, Phone-Verify |
| **Chef UFR** | Dashboard, Ufrs, Subjects, Classrooms, Schedules, Homeworks, Teachers (Voir), Attendance, Leaves, Notifications, Profile, 2FA-Setup, Phone-Verify |
| **Comptable** | Dashboard, Payroll, Teachers (Voir), Attendance (Voir), Leaves, Notifications, Profile, 2FA-Setup, Phone-Verify |
| **Enseignant** | Dashboard, Schedules (Voir), Homeworks (Voir+Publier), Attendance (Pointer+Voir), Payroll (Voir), Documents (Voir), Notifications, Profile, 2FA-Setup, Phone-Verify |

### Endpoints API par acteur

| Acteur | Endpoints accessibles | Permissions |
|--------|----------------------|-------------|
| **Admin** | ~90 (tous) | 36 |
| **Responsable RH** | ~45 | 6 |
| **Secrétaire** | ~45 | 6 |
| **Chef UFR** | ~35 | 7 |
| **Comptable** | ~20 | 6 |
| **Enseignant** | ~15 | 7 |

---

*Document généré le 11/08/2026 - Audit complet des fonctionnalités SIRH Dakar v2.1.0*
