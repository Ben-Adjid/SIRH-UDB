# 🧪 GUIDE COMPLET DE TEST - Application SIRH

## 📋 Données de test disponibles

Exécutez le seeder pour créer les données:
```bash
php artisan db:seed --class=ComprehensiveTestDataSeeder
```

## 👥 5 Acteurs à Tester

### 1️⃣ **ADMIN**
- **Email:** admin@sirh.local
- **Password:** Admin@2026
- **Accès:** Tout le système
- ✅ **À tester:**
  - [ ] Dashboard admin
  - [ ] Gestion des utilisateurs
  - [ ] Gestion des UFR
  - [ ] Gestion des salles
  - [ ] Audit logs
  - [ ] Tout le reste

### 2️⃣ **HR MANAGER** (Responsable RH)
- **Email:** hr@sirh.local
- **Password:** HR@2026
- **Accès:** Gestion du personnel, pointages, congés
- ✅ **À tester:**
  - [ ] Dashboard HR
  - [ ] Gestion des enseignants
  - [ ] Pointages (voir, valider)
  - [ ] Demandes de congé (valider/rejeter)
  - [ ] Listes présences/absences
  - [ ] Audit logs

### 3️⃣ **ACCOUNTANT** (Comptable)
- **Email:** accountant@sirh.local
- **Password:** Accountant@2026
- **Accès:** Paies, calculs financiers
- ✅ **À tester:**
  - [ ] Dashboard comptable
  - [ ] Calcul de paie individuelle
  - [ ] Calcul paies du mois
  - [ ] Validation des paies
  - [ ] Marquer comme payée
  - [ ] Export des paies (CSV)
  - [ ] PDF des états d'honneur

### 4️⃣ **ACADEMIC MANAGER** (Chef UFR)
- **Email:** academic@sirh.local
- **Password:** Academic@2026
- **Accès:** Emploi du temps, matières, salles
- ✅ **À tester:**
  - [ ] Dashboard académique
  - [ ] Gestion des matières
  - [ ] Gestion des salles
  - [ ] Gestion de l'emploi du temps
  - [ ] Distribution des cours

### 5️⃣ **TEACHERS** (5 Enseignants)
- **Emails:** 
  - abdoulaye@sirh.local
  - mariam@sirh.local
  - ousmane@sirh.local
  - fatou@sirh.local
  - modou@sirh.local
- **Password:** Teacher@2026
- ✅ **À tester:**
  - [ ] Dashboard personnel
  - [ ] Mon emploi du temps
  - [ ] Mes pointages
  - [ ] Mes absences
  - [ ] Mes honoraires
  - [ ] Mes documents

---

## 🔧 FONCTIONNALITÉS À VÉRIFIER

### 📚 **Gestion des Enseignants (CRUD)**
- [ ] **CREATE:** Ajouter un nouvel enseignant
  - Validation du formulaire
  - Attribution d'une UFR
  - Configuration du taux horaire
- [ ] **READ:** Voir la liste des enseignants
  - Pagination
  - Filtres (UFR, statut)
  - Recherche
- [ ] **UPDATE:** Modifier les données
  - Changement UFR
  - Modification taux horaire
  - Changement statut
- [ ] **DELETE:** Supprimer (avec confirmation)
  - Soft delete en place
  - Historique préservé

### 🏢 **Gestion des UFR (CRUD)**
- [ ] **CREATE:** Nouveau département
- [ ] **READ:** Lister les UFR
- [ ] **UPDATE:** Modifier informations
- [ ] **DELETE:** Supprimer UFR (cascade check)

### 📚 **Gestion des Matières (CRUD)**
- [ ] **CREATE:** Ajouter matière
  - Code unique
  - Niveau (L1-M2)
- [ ] **READ:** Voir catalogue
  - Par niveau
  - Recherche
- [ ] **UPDATE:** Modifier description
- [ ] **DELETE:** Supprimer matière

### 🏛️ **Gestion des Salles (CRUD)**
- [ ] **CREATE:** Ajouter salle
  - Type (Amphi, TP, TD)
  - Capacité
- [ ] **READ:** Voir disponibilités
- [ ] **UPDATE:** Changer capacité
- [ ] **DELETE:** Supprimer salle

### 📅 **Emploi du Temps**
- [ ] Créer planning
  - Assigner enseignant + matière + salle + horaire
  - Validation pas de conflit d'horaire
- [ ] Voir emploi du temps
  - Par enseignant
  - Par salle
  - Par matière
- [ ] Modifier cours
- [ ] Supprimer cours

### ✋ **Pointages (Attendance)**
- [ ] Check-in avec PIN code
  - Présent / Absent / Retard
  - Horodatage correct
- [ ] Check-out
  - Calcul heures automatique
  - Validation présence heure
- [ ] Voir historique pointages
  - Détails (horaires, durée)
- [ ] Saisir heures manuellement (RH)
  - Validation
  - Audit trail

### 📋 **Demandes de Congé (Leaves)**
- [ ] **Créer demande**
  - Type de congé
  - Dates début/fin
  - Raison
  - Solde disponible vérifiée
- [ ] **Valider demande** (HR)
  - Approuver / Rejeter
  - Commentaires
- [ ] **Voir historique**
  - Statut
  - Soldes utilisés
- [ ] **Calcul congés restants**
  - Par type
  - Par année

### 💰 **Calcul de Paie**
- [ ] **Calcul individuel**
  - Sélectionner enseignant
  - Récupérer heures automatiquement
  - Afficher absences
  - Voir prévisualisation:
    - Honoraire brut (h × taux)
    - BRS (5% si >= 25k)
    - Primes (optionnel)
    - Retenues (optionnel)
    - NET À PAYER
    - Mode paiement (Chèque/Espèces)
  - Ajouter bonus/retenue
  - Enregistrer paie
- [ ] **Calcul du mois entier**
  - Tous les enseignants
  - Avec progress bar
- [ ] **Validation de paie**
  - Confirmer avant validation
  - Historique préservé
- [ ] **Marquer comme payée**
  - Notification enseignant
  - Status changé
- [ ] **Télécharger PDF**
  - Format professionnel
  - Toutes les retenues affichées
- [ ] **Exporter CSV**
  - Données complètes
  - Format correct

### 👤 **Authentification & Sécurité**
- [ ] **Login**
  - Validation email/password
  - Messages d'erreur clairs
  - Redirection dashboard
- [ ] **2FA** (si implémenté)
  - Setup 2FA
  - Vérification code
- [ ] **Permissions par rôle**
  - Admin peut tout voir
  - RH voit personnel/pointages
  - Comptable voit paies
  - Academic voit emploi du temps
  - Teacher voit que ses données

### 📊 **Dashboard**
- [ ] **Admin Dashboard**
  - Utilisateurs actifs
  - Statistiques générales
  - Graphiques
- [ ] **RH Dashboard**
  - Présences/absences du mois
  - Congés en attente
  - Personnel actif
- [ ] **Accountant Dashboard**
  - Paies calculées/validées/payées
  - Montant total
  - Graphique salaires
- [ ] **Academic Dashboard**
  - Emplois du temps
  - Matières offertes
  - Distribution enseignants
- [ ] **Teacher Dashboard**
  - Mes infos
  - Mon emploi du temps
  - Mes pointages
  - Mes honoraires

### 🔒 **Audit & Logs**
- [ ] Tous les changements tracés
- [ ] Qui a fait quoi (user_id)
- [ ] Quand (timestamp)
- [ ] Valeurs avant/après
- [ ] IP address enregistrée

---

## ✅ Checklist de Validation Finale

### Fonctionnalités Core
- [ ] Tous les CRUD (Create/Read/Update/Delete) fonctionnent
- [ ] Pas de crash JavaScript
- [ ] Pas d'erreur SQL
- [ ] Messages d'erreur clairs

### Performance
- [ ] Listes se chargent rapidement
- [ ] Pas de lag UI
- [ ] Pagination fonctionne
- [ ] Recherches réactives

### Sécurité
- [ ] Passwords hashedés en base
- [ ] Routes protégées par auth
- [ ] Permissions respectées par rôle
- [ ] XSS protégé (escapeHtml)
- [ ] CSRF tokens en place

### UX/Design
- [ ] Interface responsive
- [ ] Textes français corrects
- [ ] Icones cohérentes
- [ ] Couleurs harmonieuses
- [ ] Toast notifications claires

### Data Integrity
- [ ] Soft deletes en place
- [ ] Audit logs complets
- [ ] Cascade delete paramétré
- [ ] Validations strictes

---

## 🚀 Commandes Utiles

```bash
# Charger les données de test
php artisan db:seed --class=ComprehensiveTestDataSeeder

# Réinitialiser & charger
php artisan migrate:fresh --seed

# Lancer le serveur backend
php artisan serve

# Lancer le frontend (depuis /frontend)
npm run dev

# Voir les logs
tail -f storage/logs/laravel.log

# Lancer les tests
php artisan test
```

---

## 📝 Notes pour la Soutenance

**Ce que vous pouvez montrer:**
1. Connexion avec 5 rôles différents
2. Dashboard pour chaque rôle
3. CRUD complet (créer, modifier, supprimer)
4. Calcul de paie automatisé
5. Pointages avec PIN code
6. Demandes de congé
7. Export PDF/CSV
8. Audit logs

**Points forts à mettre en avant:**
- ✅ Architecture complète (Admin + 4 acteurs)
- ✅ Logique métier correcte (calcul paie, BRS)
- ✅ Sécurité (authentification, permissions, audit)
- ✅ UX moderne et responsive
- ✅ Tests et validation en place

---

## 🐛 Troubleshooting

| Problème | Solution |
|----------|----------|
| Erreur 401 Unauthorized | Login avec les credentials fournis |
| Endpoint 404 | Vérifier que le seeder a créé les données |
| Pas de données | Exécuter `php artisan db:seed --class=ComprehensiveTestDataSeeder` |
| JavaScript error | Ouvrir DevTools (F12) et vérifier console |
| Paie ne calcule pas | Vérifier qu'il y a des pointages pour le mois |

---

**Bonne chance pour les tests ! 🎉**
