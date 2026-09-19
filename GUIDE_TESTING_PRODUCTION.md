# 🧪 GUIDE DE TEST EN PRODUCTION - SIRH DIT

**Objectif:** Vérifier que tous les bugs ont été corrigés  
**Durée estimée:** 30-45 minutes  
**Requis:** Accès à l'application avec différents rôles

---

## ✅ CHECKLIST PRÉ-TEST

- [ ] Backend est en ligne (vérifier `/health`)
- [ ] Frontend est en ligne
- [ ] Reverb WebSocket est actif
- [ ] Base de données est accessible
- [ ] Comptes test pour chaque rôle sont disponibles

---

## 🔍 TEST 1: NOTIFICATIONS WEBOCKET EN TEMPS RÉEL

### Préparation
1. Ouvrir 2 navigateurs/onglets
2. Onglet 1: Admin ou RH
3. Onglet 2: Enseignant ou Employé

### Test
**Onglet 2 (Enseignant):**
1. Aller sur `/leaves`
2. Cliquer sur "Nouvelle demande"
3. Remplir le formulaire
4. Soumettre

**Onglet 1 (Admin/RH):**
1. Vérifier que la notification apparaît en temps réel (haut de l'écran)
2. Cliquer sur la notification → Doit aller à `/leaves`
3. La nouvelle demande doit être visible dans la liste

### ✅ Résultat Attendu
- [ ] Notification WebSocket reçue en temps réel
- [ ] Notification visible en haut de l'écran
- [ ] Clic sur notification redirige correctement
- [ ] Demande visible dans la liste

---

## 🔍 TEST 2: CRÉER UNE UFR

### Étapes
1. Connecter en tant qu'Admin
2. Aller sur `/ufrs`
3. Cliquer sur "Ajouter une UFR"
4. Remplir le formulaire:
   - Nom: "UFR TEST 2024"
   - Code: "UFR-TST-2024"
   - Description: "UFR de test"
5. Soumettre

### ✅ Résultat Attendu
- [ ] Modale se ferme
- [ ] Liste des UFRs s'affiche (PAS de page de recherche)
- [ ] La nouvelle UFR "UFR TEST 2024" est visible dans la liste
- [ ] Notification de succès apparaît
- [ ] Champ de recherche est vide

---

## 🔍 TEST 3: CRÉER UNE MATIÈRE

### Étapes
1. Connecter en tant qu'Admin ou Chef UFR
2. Aller sur `/subjects`
3. Cliquer sur "Ajouter une matière"
4. Remplir le formulaire:
   - Nom: "Matière TEST"
   - Code: "MAT-TST"
   - Niveau: "L1"
5. Soumettre

### ✅ Résultat Attendu
- [ ] Modale se ferme immédiatement
- [ ] Liste des matières s'affiche (PAS de page de recherche)
- [ ] La nouvelle matière est visible en haut
- [ ] Champ de recherche est vide
- [ ] Pagination reset à page 1

---

## 🔍 TEST 4: CRÉER UNE SALLE

### Étapes
1. Connecter en tant qu'Admin
2. Aller sur `/classrooms`
3. Cliquer sur "Ajouter une salle"
4. Remplir le formulaire:
   - Nom: "Salle TEST"
   - Code: "SALLE-TST"
   - Capacité: "50"
   - Type: "classroom"
5. Soumettre

### ✅ Résultat Attendu
- [ ] Modale se ferme immédiatement
- [ ] Liste des salles s'affiche
- [ ] La nouvelle salle est visible
- [ ] Pas d'affichage de page de recherche

---

## 🔍 TEST 5: DEMANDE DE CONGÉ (ENSEIGNANT)

### Étapes
1. Connecter en tant qu'Enseignant
2. Aller sur `/leaves`
3. Cliquer sur "Nouvelle demande"
4. Remplir le formulaire:
   - Type de congé: "Congé annuel"
   - Date de début: Date future (ex: 2026-10-01)
   - Date de fin: Date future (ex: 2026-10-05)
   - Raison: "Test de congé"
5. Soumettre

### ✅ Résultat Attendu
- [ ] Modale se ferme
- [ ] La demande apparaît dans la liste
- [ ] Statut = "En attente"
- [ ] Notification de création s'affiche
- [ ] Formulaire vide (réinitialisé)

### Validation (RH/Admin)
1. Connecter en tant qu'Admin ou RH
2. Aller sur `/leaves`
3. Voir la demande
4. Cliquer sur "Approuver"
5. Vérifier que le statut passe à "Approuvé"

### ✅ Résultat Attendu
- [ ] Notification WebSocket reçue par l'enseignant
- [ ] Enseignant voit la notification "Congé Approuvé"
- [ ] Statut passe à "Approuvé" chez l'Admin
- [ ] Solde de congé est mis à jour

---

## 🔍 TEST 6: CRÉER UN DEVOIR

### Étapes (Enseignant)
1. Connecter en tant qu'Enseignant
2. Aller sur `/homeworks`
3. Cliquer sur "Nouveau devoir"
4. Remplir le formulaire:
   - Matière: (sélectionner une matière)
   - Salle: (sélectionner une salle)
   - Titre: "Devoir TEST"
   - Description: "Description test"
   - Date: Date future
   - Heure début: "14:00"
   - Heure fin: "15:00"
   - Points max: "20"
5. Soumettre

### ✅ Résultat Attendu
- [ ] Modale se ferme immédiatement
- [ ] Liste des devoirs s'affiche (PAS de recherche)
- [ ] Le nouveau devoir est visible
- [ ] Notification de création

---

## 🔍 TEST 7: CRÉER UN EMPLOI DU TEMPS

### Étapes
1. Connecter en tant qu'Admin ou Chef UFR
2. Aller sur `/schedules`
3. Cliquer sur "Ajouter un cours"
4. Remplir le formulaire:
   - UFR: (sélectionner)
   - Enseignant: (sélectionner)
   - Matière: (sélectionner)
   - Salle: (sélectionner)
   - Dates et heures
5. Soumettre

### ✅ Résultat Attendu
- [ ] Modale se ferme
- [ ] Liste s'affiche (pas de recherche)
- [ ] Cours visible dans la liste
- [ ] Pas d'erreur

---

## 🔍 TEST 8: GESTION DES HONORAIRES

### Étapes (Comptable/Admin)
1. Aller sur `/payroll`
2. Sélectionner un enseignant
3. Cliquer sur "Calculer salaire"
4. Le formulaire pré-remplit les heures du pointage
5. (Optionnel) Modifier les heures manuellement
6. Soumettre

### ✅ Résultat Attendu
- [ ] Heures du pointage sont affichées
- [ ] Si heures manuelles > 0, elles sont utilisées (priorité)
- [ ] Sinon, heures du pointage sont utilisées
- [ ] Calcul du salaire correct
- [ ] BRS appliqué si nécessaire

---

## 🔍 TEST 9: TÉLÉVERSEMENT DE DOCUMENTS

### Étapes (Enseignant ou Admin pour enseignant)
1. Aller sur `/documents` (ou profil enseignant)
2. Cliquer sur "Ajouter document"
3. Sélectionner un fichier (PDF, Doc, Image)
4. Donner un nom et type
5. Soumettre

### Test Suppression
1. Cliquer sur "Supprimer" sur un document
2. Confirmer la suppression
3. Vérifier que le document disparaît

### Test Modification
1. Cliquer sur "Modifier" sur un document
2. Changer le nom
3. Soumettre
4. Vérifier que le nom est mis à jour

### ✅ Résultat Attendu
- [ ] Document uploadé avec succès
- [ ] Liste mise à jour
- [ ] Document supprimé après confirmation
- [ ] Document modifié après modification
- [ ] Pas d'erreur de permissions

---

## 🔍 TEST 10: POINTAGE (TOUS LES RÔLES)

### Préparation
- Avoir des PIN configurés pour tous les utilisateurs

### Étapes
1. Chaque rôle va sur `/attendance`
2. Clique sur "Pointer arrivée"
3. Saisi son PIN
4. Vérifier le résultat

### ✅ Résultat Attendu
Pour **Chef UFR, RH, Secrétaire, Comptable:**
- [ ] Champ PIN s'affiche
- [ ] Pointage accepté avec le bon PIN
- [ ] Message de succès
- [ ] Notification de pointage

Pour **Enseignant:**
- [ ] Pointage fonctionne avec PIN personnel
- [ ] Nécessite un cours à cette heure

---

## 🔍 TEST 11: SUPPRESSION AVEC CONFIRMATION

### Étapes
1. Aller sur n'importe quelle liste (UFR, Matière, Salle, etc.)
2. Cliquer sur "Supprimer" un élément
3. Modale de confirmation s'affiche
4. Confirmer la suppression

### ✅ Résultat Attendu
- [ ] Modale de confirmation s'affiche
- [ ] Élément supprimé après confirmation
- [ ] Modale se ferme complètement
- [ ] Liste mise à jour
- [ ] Notification de succès

---

## 📊 RÉSUMÉ DU TEST

| Test | Statut | Notes |
|------|--------|-------|
| 1. WebSocket Notifications | ✅ Pass / ❌ Fail | |
| 2. Créer UFR | ✅ Pass / ❌ Fail | |
| 3. Créer Matière | ✅ Pass / ❌ Fail | |
| 4. Créer Salle | ✅ Pass / ❌ Fail | |
| 5. Demande Congé | ✅ Pass / ❌ Fail | |
| 6. Créer Devoir | ✅ Pass / ❌ Fail | |
| 7. Emploi du temps | ✅ Pass / ❌ Fail | |
| 8. Honoraires | ✅ Pass / ❌ Fail | |
| 9. Documents | ✅ Pass / ❌ Fail | |
| 10. Pointage | ✅ Pass / ❌ Fail | |
| 11. Suppression | ✅ Pass / ❌ Fail | |

---

## 🐛 EN CAS D'ERREUR

### Notifications WebSocket ne fonctionnent pas?
```bash
# Vérifier que Reverb est lancé
ps aux | grep reverb

# Vérifier les logs backend
tail -f storage/logs/laravel.log

# Vérifier la console du navigateur (F12)
# Chercher les erreurs de connexion WebSocket
```

### Formulaires ne se réinitialisent pas?
- Purger le cache: `php artisan cache:clear`
- Rafraîchir le navigateur (Ctrl+Shift+R)

### Modales qui ne se ferment pas?
- Vérifier les logs du navigateur
- Vérifier la console API (network tab)

### Permissions refusées?
- Vérifier le rôle de l'utilisateur connecté
- Vérifier les logs backend pour l'autorisation

---

## 📞 SUPPORT

**Tous les tests doivent passer avant déploiement en production.** Si un test échoue:

1. Vérifier les logs backend: `storage/logs/laravel.log`
2. Vérifier la console navigateur (F12 → Console)
3. Vérifier les network requests (F12 → Network)
4. Vérifier le statut de Reverb

