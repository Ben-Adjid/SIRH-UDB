# 🔍 DIAGNOSTIC COMPLET - TOUS LES RÔLES UTILISATEURS

**Date:** 23 septembre 2026  
**Status:** AUDIT COMPLET DE 6 RÔLES  
**Derniers commits:** 9f10ec6 (fix badge overlap)

---

## 📋 RÔLES À VÉRIFIER

1. **Admin** - Accès complet à tout
2. **Chef UFR (Academic Manager)** - Gère son UFR uniquement
3. **Prof (Teacher)** - Voir ses données personnelles
4. **RH (HR Manager)** - Gérer présences et congés
5. **Secrétaire (Secretary)** - Même que RH
6. **Comptable (Accountant)** - Gère la paie

---

## 🧪 CHECKLIST DE DIAGNOSTIC PAR RÔLE

### 1️⃣ ADMIN
**Pages à vérifier:**

| Page | Affichage | Auto-refresh | Filtrage | Erreurs | Status |
|------|-----------|---|---|---|---|
| Dashboard | 9 users, 4 teachers, données complètes | ✅ | N/A | À tester | ⏳ |
| Users | Liste complète de tous utilisateurs | ✅ | Aucun filtre | À tester | ⏳ |
| Teachers | Tous les enseignants | ✅ | UFR visible | À tester | ⏳ |
| Attendance | TOUS les pointages | ✅ | Aucun filtre UFR | À tester | ⏳ |
| Leaves | TOUS les congés | ✅ | N/A | À tester | ⏳ |
| Schedules | Tous les cours | ✅ | Tous les UFR | À tester | ⏳ |
| Payroll | Tous les salaires | ✅ | Tous les teachers | À tester | ⏳ |
| Homeworks | Tous les devoirs | ✅ | Tous les teachers | À tester | ⏳ |

**Vérifications spécifiques Admin:**
- [ ] Dashboard affiche les 9 utilisateurs
- [ ] Dashboard affiche les 4 enseignants actifs
- [ ] Pas de filtre UFR appliqué
- [ ] Peut créer/modifier/supprimer tout
- [ ] Auto-refresh fonctionne après mutations

---

### 2️⃣ CHEF UFR (Academic Manager)
**Pages à vérifier:**

| Page | Affichage | Auto-refresh | Filtrage | Erreurs | Status |
|------|-----------|---|---|---|---|
| Dashboard | Données UFR uniquement | ✅ | Par UFR | À tester | ⏳ |
| Teachers | Enseignants UFR uniquement | ✅ | Filtre UFR appliqué | À tester | ⏳ |
| Attendance | Son pointage + enseignants UFR | ✅ | Par UFR | À tester | ⏳ |
| Leaves | Congés UFR uniquement | ✅ | Par UFR | À tester | ⏳ |
| Schedules | Cours UFR uniquement | ✅ | Filtre UFR | À tester | ⏳ |
| Payroll | Paies UFR uniquement | ✅ | Par UFR | À tester | ⏳ |
| Homeworks | Devoirs UFR uniquement | ✅ | Par UFR | À tester | ⏳ |

**Vérifications spécifiques Chef UFR:**
- [ ] Voit son pointage personnel dans liste (badge 👤 MON POINTAGE)
- [ ] Voit SEULEMENT enseignants de son UFR
- [ ] Filtre UFR appliqué automatiquement
- [ ] Dashboard montre données UFR
- [ ] Pas d'accès aux autres UFR
- [ ] Auto-refresh fonctionne après pointage

---

### 3️⃣ PROF (Teacher)
**Pages à vérifier:**

| Page | Affichage | Auto-refresh | Filtrage | Erreurs | Status |
|------|-----------|---|---|---|---|
| Dashboard | Contrat, honoraires, prochains cours | ✅ | Personnel | À tester | ⏳ |
| Attendance | Son pointage personnel uniquement | ✅ | Personnel | À tester | ⏳ |
| Schedules | Ses cours uniquement | ✅ | Personnel | À tester | ⏳ |
| Payroll | Ses honoraires uniquement | ✅ | Personnel | À tester | ⏳ |
| Homeworks | Ses devoirs uniquement | ✅ | Personnel | À tester | ⏳ |
| Users | ❌ Pas d'accès | N/A | N/A | À tester | ⏳ |
| Teachers | ❌ Pas d'accès | N/A | N/A | À tester | ⏳ |
| Leaves | Ses congés uniquement | ✅ | Personnel | À tester | ⏳ |

**Vérifications spécifiques Prof:**
- [ ] Voit SEULEMENT ses données
- [ ] Peut se pointer (check-in/check-out)
- [ ] Voit ses cours programmés
- [ ] Voit ses honoraires
- [ ] Pas d'accès à gestion d'autres
- [ ] Auto-refresh fonctionne

---

### 4️⃣ RH/SECRÉTAIRE (HR Manager / Secretary)
**Pages à vérifier:**

| Page | Affichage | Auto-refresh | Filtrage | Erreurs | Status |
|------|-----------|---|---|---|---|
| Dashboard | Suivi des présences, absences | ✅ | Tous | À tester | ⏳ |
| Users | Tous les utilisateurs | ✅ | Peut filtrer | À tester | ⏳ |
| Teachers | Tous les enseignants | ✅ | Peut filtrer | À tester | ⏳ |
| Attendance | TOUS les pointages | ✅ | Peut filtrer par date/UFR | À tester | ⏳ |
| Leaves | TOUS les congés | ✅ | Peut valider/rejeter | À tester | ⏳ |
| Schedules | Tous les cours | ✅ | Par UFR | À tester | ⏳ |
| Payroll | Voir tous | ✅ | Lecture seule | À tester | ⏳ |
| Homeworks | Tous les devoirs | ✅ | Lecture seule | À tester | ⏳ |

**Vérifications spécifiques RH:**
- [ ] Voit TOUS les pointages sans filtre UFR
- [ ] Peut valider les congés
- [ ] Dashboard affiche suivi présences
- [ ] Peut enregistrer absences
- [ ] Auto-refresh fonctionne
- [ ] Pas de filtre UFR imposé

---

### 5️⃣ COMPTABLE (Accountant)
**Pages à vérifier:**

| Page | Affichage | Auto-refresh | Filtrage | Erreurs | Status |
|------|-----------|---|---|---|---|
| Dashboard | Stats financières | ✅ | Données paie | À tester | ⏳ |
| Payroll | Tous les salaires | ✅ | Peut calculer/valider | À tester | ⏳ |
| Users | ❌ Pas d'accès | N/A | N/A | À tester | ⏳ |
| Teachers | ❌ Pas d'accès | N/A | N/A | À tester | ⏳ |
| Attendance | ❌ Pas d'accès | N/A | N/A | À tester | ⏳ |
| Leaves | ❌ Pas d'accès | N/A | N/A | À tester | ⏳ |
| Schedules | ❌ Pas d'accès | N/A | N/A | À tester | ⏳ |
| Homeworks | ❌ Pas d'accès | N/A | N/A | À tester | ⏳ |

**Vérifications spécifiques Comptable:**
- [ ] Voit que le Payroll
- [ ] Accès limité à données financières
- [ ] Pas d'accès aux autres pages
- [ ] Dashboard montre stats paie
- [ ] Auto-refresh fonctionne

---

## 🔧 POINTS DE VÉRIFICATION GLOBAUX

### Erreurs Console
- [ ] Pas d'erreurs JavaScript dans console
- [ ] Pas de warnings inutiles
- [ ] Pas de 404 sur assets

### Auto-Refresh
- [ ] ✅ Création d'utilisateur → refresh immédiat
- [ ] ✅ Création enseignant → refresh immédiat
- [ ] ✅ Pointage check-in → refresh immédiat
- [ ] ✅ Pointage check-out → refresh immédiat
- [ ] ✅ Création congé → refresh immédiat
- [ ] ✅ Validation congé → refresh immédiat
- [ ] ✅ Création cours → refresh immédiat
- [ ] ✅ Création devoir → refresh immédiat
- [ ] ✅ Calcul paie → refresh immédiat

### Performance
- [ ] Pas de lag au clic
- [ ] Chargement rapide des pages
- [ ] Pas de freezes UI
- [ ] Transitions smooth

### Design/UX
- [ ] Pas de texte "Inconnu" injustifié
- [ ] Tous les badges affichent bien
- [ ] Pas de chevauchement UI
- [ ] Couleurs cohérentes
- [ ] Responsive sur mobile/desktop
- [ ] Icônes affichent correctement

### Filtrage et Accès
- [ ] Admin voit TOUT sans filtre
- [ ] Chef UFR voit SEULEMENT son UFR (auto-filtré)
- [ ] Prof voit SEULEMENT ses données
- [ ] RH/Secretary voient TOUT
- [ ] Comptable voit SEULEMENT Payroll
- [ ] Pas d'accès non-autorisé

### Données
- [ ] Timestamps affichent au bon format (HH:MM)
- [ ] Pas de "2026" à la place de l'heure
- [ ] Nombres formatés correctement
- [ ] Pas de "NaN" affichés
- [ ] Dates au bon format (DD/MM/YYYY)

---

## 📊 RÉSUMÉ PAR STATUT

**À tester:** Tous les points ci-dessus
**À corriger:** À déterminer après test
**Commits appliqués:** 15+ commits
**Status global:** En attente de test complet

---

## ✅ PROCHAINES ÉTAPES

1. **Test de chaque rôle** avec vérification checklist
2. **Signalement des bugs** trouvés
3. **Corrections** des bugs identifiés
4. **Re-test** après corrections
5. **Final validation** avant production

---

**Aide-moi à compléter ce diagnostic!**  
Connecte-toi à chaque rôle et teste chaque point de la checklist.
