# 🔎 DIAGNOSTIC COMPLET - SIRH v2026

## 📊 ÉTAT GLOBAL DU SYSTÈME

| Composant | Status | Notes |
|-----------|--------|-------|
| **Backend** | ✅ 95/95 tests | Fonctionnel |
| **Database** | ✅ Migré | 9 utilisateurs |
| **Frontend** | ⚠️ À tester | Vérifier login |
| **Design** | ⚠️ À auditer | Optimisation UI |
| **Paie** | ⚠️ À valider | Formules correctes |

---

## 🔑 1. PROBLÈME DE CONNEXION

### Diagnostic
L'utilisateur rapporte ne pas pouvoir se connecter avec les credentials fournis.

### Credentials Disponibles:
```
Admin:
  Email: admin@sirh.local
  Pass: Admin@2026
  
RH Manager:
  Email: hr@sirh.local
  Pass: HR@2026

Comptable:
  Email: accountant@sirh.local
  Pass: Accountant@2026

Academic Manager:
  Email: academic@sirh.local
  Pass: Academic@2026

Teachers (5 + PIN pour pointage):
  abdoulaye@sirh.local / Teacher@2026
  mariam@sirh.local / Teacher@2026
  ousmane@sirh.local / Teacher@2026
  fatou@sirh.local / Teacher@2026
  modou@sirh.local / Teacher@2026
  
  PIN codes (pour pointage):
  abdoulaye: 1001
  mariam: 1002
  ousmane: 1003
  fatou: 1004
  modou: 1005
```

### Solutions à Essayer:
1. ✅ Vérifier que le mot de passe est EXACT (case-sensitive)
2. ✅ Vérifier l'email est EXACT
3. ✅ Vérifier que le compte est ACTIF
4. ✅ Vérifier localStorage après login
5. ✅ Vérifier les requêtes réseau (F12 → Network)

### Test Rapide:
```bash
# Terminal - Vérifier utilisateur existe
php artisan tinker
>>> \App\Models\User::where('email', 'admin@sirh.local')->first()

# Test password
>>> \Illuminate\Support\Facades\Hash::check('Admin@2026', \$user->password)
# Devrait retourner: true
```

---

## 🎨 2. DESIGN DE LA PAGE LOGIN

### Problèmes Rapportés:
- [ ] Taille trop grande
- [ ] Police non appropriée
- [ ] Couleurs problématiques
- [ ] Layout non optimisé

### Audit Design Nécessaire:

#### A. **Taille et Responsive**
**À Vérifier**:
- [ ] Mobile (width: 375px) - La page est lisible?
- [ ] Tablette (width: 768px) - Le formulaire rentre?
- [ ] Desktop (width: 1920px) - C'est proportionné?

**Recommandations**:
- Max-width du formulaire: 400px
- Padding: 20px (mobile) à 40px (desktop)
- Input height: 44px (mobile-friendly)

#### B. **Police et Typographie**
**À Vérifier**:
- [ ] Font-size title: 28px (suffisant?)
- [ ] Font-size input: 16px (évite zoom mobile)
- [ ] Font-family: Lisible? (Outfit/Inter/System)
- [ ] Line-height: 1.5x pour lisibilité

**Recommandations**:
```css
/* Mobile */
body { font-size: 14px; }
h1 { font-size: 24px; }
input { font-size: 16px; } /* Important pour mobile */

/* Desktop */
@media (min-width: 1024px) {
  body { font-size: 16px; }
  h1 { font-size: 32px; }
}
```

#### C. **Couleurs et Contraste**
**À Vérifier**:
- [ ] Contraste texte/fond: >= 4.5:1 (WCAG)
- [ ] Background suffisamment clair?
- [ ] Bouton lisible (contraste)?
- [ ] Messages d'erreur visibles?

**Recommandations**:
- Background: #f8f9fa (blanc cassé)
- Texte: #2c3e50 (gris foncé)
- Primaire: #6366f1 (indigo)
- Erreur: #ef4444 (rouge)
- Succès: #10b981 (vert)

#### D. **Layout et Spacing**
**À Vérifier**:
- [ ] Espacement cohérent (8px grid)
- [ ] Gap entre éléments: 16px
- [ ] Padding formulaire: 24px
- [ ] Bouton width: 100%

---

## 💰 3. LOGIQUE DE CALCUL DE PAIE

### Vérification de la Formule

#### A. **Calcul des Honoraires**
```
Honoraires = Heures Travaillées × Taux Horaire
```

**Exemple**:
- Heures: 40h
- Taux: 5000 F/h
- Honoraires: 40 × 5000 = 200,000 F

#### B. **Calcul BRS (5% si >= 25,000 F)**
```
BRS = Honoraires >= 25000 ? Honoraires × 0.05 : 0
```

**Exemples**:
- Honoraires: 200,000 F → BRS: 10,000 F ✅
- Honoraires: 20,000 F → BRS: 0 F ✅

#### C. **Salaire Net**
```
Salaire Net = Honoraires - BRS
```

**Exemple**:
- Honoraires: 200,000 F
- BRS: 10,000 F
- Net: 190,000 F ✅

#### D. **Méthode de Paiement**
```
Méthode = Net >= 100000 ? "Chèque" : "Espèces"
```

#### E. **Stockage des Données**
Table `payrolls`:
- `teacher_id` - Identifiant professeur
- `year` - Année (ex: 2026)
- `month` - Mois (ex: 9)
- `honorarium_amount` - Honoraires bruts
- `brs_amount` - Montant BRS
- `gross_salary` - Salaire brut
- `deductions` - Déductions totales
- `net_salary` - Salaire net
- `payment_method` - Chèque/Espèces
- `status` - calculated/validated/paid

### Code à Vérifier

```php
// backend/app/Http/Controllers/Api/PayrollController.php

// Formule:
$baseAmount = (heures) × (taux_horaire);
$brsAmount = $baseAmount >= 25000 ? round($baseAmount * 0.05, 2) : 0;
$netSalary = $baseAmount - $brsAmount;
$paymentMethod = $netSalary >= 100000 ? 'check' : 'cash';
```

### Test Cas Réels:

**Cas 1: Salaire Élevé**
- Heures: 50h, Taux: 5000 F
- Brut: 250,000 F
- BRS: 12,500 F
- Net: 237,500 F
- Méthode: Chèque ✅

**Cas 2: Salaire Bas**
- Heures: 15h, Taux: 3000 F
- Brut: 45,000 F
- BRS: 0 F (< 25,000)
- Net: 45,000 F
- Méthode: Espèces ✅

---

## 🔧 4. VÉRIFICATION DES CONTRÔLEURS

### À Vérifier Tous les Contrôleurs:

**Backend/app/Http/Controllers/Api/**
- [ ] AuthController.php - Login/Register
- [ ] PayrollController.php - Paie
- [ ] AttendanceController.php - Pointage
- [ ] TeacherController.php - Gestion prof
- [ ] HomeworkController.php - Devoirs
- [ ] DashboardController.php - Tableaux bord
- [ ] LeaveController.php - Congés
- [ ] ScheduleController.php - Emploi du temps

### Erreurs Courantes à Chercher:
1. ❌ Imports manquants (`use Carbon\Carbon;`)
2. ❌ Erreurs de syntaxe PHP
3. ❌ Variables non définies
4. ❌ Appels de méthodes inexistantes
5. ❌ Problèmes de base de données

### Commande de Vérification:
```bash
# Vérifier syntaxe tous les fichiers
for file in backend/app/Http/Controllers/Api/*.php; do
  php -l "$file"
done
```

---

## ✅ RÉSUMÉ DES ACTIONS

| Action | Priority | Status |
|--------|----------|--------|
| Tester login avec credentials | 🔴 HAUTE | À faire |
| Auditer design login | 🟡 MOYENNE | À faire |
| Valider calculs paie | 🔴 HAUTE | À faire |
| Vérifier tous contrôleurs | 🟡 MOYENNE | À faire |
| Tester frontend complet | 🟡 MOYENNE | À faire |

---

## 📝 Notes Importantes

1. **Mots de passe**: Admin@2026, HR@2026, etc. (sensible à la casse!)
2. **Teachers**: PIN = numéro sans hash pour tests, sinon hashé en prod
3. **Paie**: Formule = heures × taux, puis 5% BRS si >= 25k
4. **Design**: Vérifier responsive (375px, 768px, 1920px)
5. **Tests**: 95/95 passent, mais test ne teste pas UI

---

**Dernière mise à jour**: 2026-09-20  
**Prochaine vérification**: Tester chaque point de cette checklist
