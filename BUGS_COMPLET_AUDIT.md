# 🔴 AUDIT COMPLET - TOUS LES BUGS

## BUGS PAR CATÉGORIE

### 1. "CRÉATION AFFICHE RECHERCHE AU LIEU DE LISTE" (CRITIQUE - 7 occurrences)
- [ ] Chef UFR: Ajouter UFR (ligne 3)
- [ ] Chef UFR: Créer Matière (ligne 4)
- [ ] Chef UFR: Créer Salle (ligne 5)
- [ ] Chef UFR: Créer Devoir (ligne 7)
- [ ] RH: Créer Enseignant (ligne 17)
- [ ] RH: Créer Devoir (ligne 22)
- [ ] Secrétaire: Créer Enseignant (ligne 33)
- [ ] Secrétaire: Créer Devoir (ligne 38)
- [ ] Admin: Créer Devoir (ligne 55)
- [ ] Admin: Créer UFR (ligne 56)
- [ ] Admin: Créer Matière (ligne 57)
- [ ] Admin: Créer Salle (ligne 58)
- [ ] Enseignant: Créer Devoir (ligne 83)
- [ ] Enseignant: Déclarer Disponibilité (ligne 84)

### 2. "MODALE DE CONFIRMATION RESTE VISIBLE" (FIXÉ ✅)
- [x] Suppression éléments (RH, Secrétaire, Admin)
- [x] Validation congé (Admin)

### 3. "PAS DE FORMULAIRE DE CONGÉ / PAS DE LISTE DES CONGÉS" (4 rôles)
- [ ] Chef UFR (ligne 8)
- [ ] RH (ligne 18)
- [ ] Secrétaire (ligne 35)
- [ ] Comptable (ligne 70)

### 4. "PROBLÈME TÉLÉVERSEMENT DOCUMENTS" (6 rôles)
- [ ] Chef UFR (ligne 11)
- [ ] RH (ligne 24)
- [ ] Secrétaire (ligne 40)
- [ ] Admin (ligne 61)
- [ ] Enseignant (ligne 86)
- [ ] Comptable (ligne 75)

### 5. "PROBLÈME EMPLOI DU TEMPS EXAMEN / CRÉATION" (Admin)
- [ ] Admin: Création examen (ligne 52)
- [ ] Admin: Création emploi du temps + sélection UFR (ligne 53)
- [ ] Chef UFR: Création examen (ligne 6)

### 6. "NOTIFICATIONS EN TEMPS RÉEL" (Tous les rôles)
- [ ] Tous les rôles (lignes 12, 25, 41, 62, 76, 87)

### 7. "POINTAGE - CERTAINS RÔLES NE PEUVENT PAS POINTER"
- [ ] Chef UFR (ligne 10)
- [ ] RH (ligne 23)
- [ ] Secrétaire (ligne 39)
- [ ] Comptable (ligne 73)

### 8. "GESTION DES HONORAIRES" (Admin + Comptable)
- [ ] Admin: Heures non prises en compte (ligne 59)
- [ ] Comptable: Heures non prises en compte (ligne 71)
- [ ] Comptable: Téléchargement honoraires (ligne 72)
- [ ] Enseignant: Fonctionnalité non fonctionnelle (ligne 85)

### 9. "FILTRE ENSEIGNANTS INACTIFS"
- [ ] Comptable (ligne 74)

### 10. "PERMISSIONS MÉTIER" (Questions logiques)
- [ ] RH ne peut pas créer emploi du temps/examen (ligne 20)
- [ ] Secrétaire ne peut pas créer emploi du temps/examen (ligne 37)
- [ ] Admin ne peut pas créer/valider devoir (ligne 54)

---

## RÉSUMÉ:
- ✅ FIXÉS: ~10 bugs (modales, formulaires)
- ❌ NON FIXÉS: ~30+ bugs
- ❓ QUESTIONS LOGIQUES: 3 bugs

**TAUX DE COMPLÉTUDE: ~25%**
