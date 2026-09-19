# 📋 SPÉCIFICATIONS PRÉCISES – CALCUL DE PAIE SIRH UDB
## Document de Référence – Corrections et Clarifications

**Date de mise à jour :** 19 Septembre 2026  
**Status :** ✅ CORRIGÉ ET VALIDÉ  

---

## 🔴 CORRECTION IMPORTANTE

**Erreur détectée dans la documentation initiale :**

| Élément | Avant | Après | Impact |
|---|---|---|---|
| Seuil BRS | > 25 000 FCFA | **≥ 25 000 FCFA** | Cas limites affectés |
| 25 000 FCFA exact | Pas de BRS | **BRS = 1 250 FCFA** | +1.25% de retenue |
| Test unitaire | Manquant | **Cas limite validé** | Couverture complète |

---

## 1️⃣  CALCUL DES HONORAIRES – ENSEIGNANTS VACATAIRES

### Définition

Pour les **vacataires**, la rémunération correspond à un **volume d'heures effectuées** (honoraires), **jamais à un salaire fixe**.

### Étapes du Calcul Automatique

#### **Étape 1 : Cumul des Heures Travaillées**

Le système additionne les heures de cours **effectivement réalisées et validées** à partir des données de pointage.

```
total_heures = Σ (heure_sortie - heure_entrée) pour chaque jour
```

**Exemple :**
- Lundi : 09:00-17:00 = 8 heures
- Mardi : 09:00-17:00 = 8 heures
- Mercredi : 09:00-12:00 = 3 heures
- **Total semaine = 19 heures**

---

#### **Étape 2 : Récupération du Taux Horaire**

L'application extrait le **tarif horaire** de l'enseignant, déterminé par :

| Type de Cours | Taux Horaire |
|---|---|
| Cours Magistral (CM) | **10 000 FCFA/h** |
| Travaux Dirigés (TD) | **6 000 FCFA/h** |
| Travaux Pratiques (TP) | **6 000 FCFA/h** |

**Note :** Le taux peut être variable selon :
- Grade de l'enseignant (Professeur, Maître de conférences, etc.)
- Nature du cours (CM vs TD/TP)
- Discipline (STEM vs Lettres)
- Configuration UDB

---

#### **Étape 3 : Calcul du Montant Brut**

**Formule :**
```
Montant Brut = total_heures × taux_horaire
```

**Exemple Concret :**
- Enseignant : Abdou Diallo
- Type : Cours Magistral (CM)
- Heures effectuées : 40 heures
- Taux horaire : 10 000 FCFA
- **Montant Brut = 40 × 10 000 = 400 000 FCFA**

---

#### **Étape 4 : Application de la Retenue Fiscale (BRS)**

**Règle :** Retenue obligatoire au Sénégal

**Formule :**
```
Si Montant Brut ≥ 25 000 FCFA :
    Montant BRS = Montant Brut × 5%
Sinon :
    Montant BRS = 0 FCFA
```

**Attention :** Seuil est **supérieur ou égal (≥)**, pas seulement supérieur (>)

**Cas Limites Importants :**

| Montant Brut | Condition | BRS | Net |
|---|---|---|---|
| 20 000 FCFA | 20k < 25k | 0 | 20 000 |
| **25 000 FCFA** | **25k = 25k ✅** | **1 250** | **23 750** |
| 25 001 FCFA | 25.001 > 25k | 1 250.05 | 23 750.95 |
| 50 000 FCFA | 50k > 25k | 2 500 | 47 500 |
| 100 000 FCFA | 100k > 25k | 5 000 | 95 000 |

---

#### **Étape 5 : Calcul du Montant Net**

**Formule :**
```
Montant Net = Montant Brut - Retenue BRS
```

**Exemple :**
- Montant Brut : 400 000 FCFA
- BRS (5%) : 20 000 FCFA
- **Montant Net = 400 000 - 20 000 = 380 000 FCFA**

---

#### **Étape 6 : Détermination du Mode de Paiement**

**Basé sur le montant NET :**

```
Si Montant Net ≥ 100 000 FCFA :
    Mode = CHÈQUE
Sinon :
    Mode = ESPÈCES
```

**Rationale :** Les montants importants requièrent une traçabilité bancaire pour conformité.

**Exemples :**

| Montant Net | Condition | Mode |
|---|---|---|
| 50 000 FCFA | 50k < 100k | Espèces |
| 100 000 FCFA | 100k = 100k | Chèque |
| 150 000 FCFA | 150k > 100k | Chèque |

---

#### **Étape 7 : Enregistrement en Base de Données**

Le système crée un enregistrement **Payroll** avec toutes les données calculées :

```php
$payroll = Payroll::create([
    'teacher_id' => $teacher->id,
    'year' => $year,
    'month' => $month,
    'base_salary' => 0,                    // 0 pour vacataires
    'honorarium_amount' => $montantBrut,   // 400 000
    'brs_amount' => $brsAmount,            // 20 000
    'gross_salary' => $montantBrut - $brsAmount,  // 380 000
    'net_salary' => $montantNet,           // 380 000
    'payment_method' => $modePayment,      // 'check'
    'status' => 'calculated',
    'calculation_details' => [
        'type' => 'vacataire',
        'hours_worked' => 40,
        'hourly_rate' => 10000,
        'brs_applied' => true,
        'payment_method' => 'check',
    ]
]);
```

---

#### **Étape 8 : Création des Détails de Paie**

Le système génère des **lignes détail** dans la table `PayrollDetail` :

```
Ligne 1: Type=Earning, Description="Honoraire : 40.0h × 10 000 F", Amount=400 000
Ligne 2: Type=Deduction, Description="BRS (5% sur 400 000 F)", Amount=20 000
```

**Résultat :**
- Total Gains : 400 000 FCFA
- Total Déductions : 20 000 FCFA
- **Net à Payer : 380 000 FCFA** ✅

---

## 2️⃣  PERSONNEL PERMANENT (Futur)

### Contexte

Actuellement, UDB n'emploie **que des vacataires**. Le personnel permanent n'existe pas.

Cependant, la structure est **prête** pour l'évolution future.

### Calcul pour Permanents

**Différence majeure :** Salaire **fixe** + éléments **variables** manuels.

```
Montant Net = Salaire Fixe 
              + Primes
              + Indemnités
              - Absences (retenues)
              - Cotisations
              - Autres déductions
```

### Processus Mensuel

1. **Saisie données variables** par comptable
   - Primes (performance, ancienneté)
   - Indemnités (transport, logement)
   - Avances sur salaire
   - Retenues d'absences

2. **Calcul cotisations** (CNSS, impôt)
3. **Génération bulletin**
4. **Validation et clôture**

---

## 3️⃣  VALIDATION ET GÉNÉRATION DES BULLETINS

### Vérification et Ajustement (Comptable)

Le comptable :

1. **Consulte** les lignes calculées dans l'interface
2. **Vérifie** la cohérence des montants
3. **Applique** d'éventuels ajustements :
   - Primes supplémentaires (bonus performance)
   - Retenues spécifiques (amendes, absences)
   - Avances ou acomptes

4. **Valide** pour confirmer que tout est correct

### Clôture de la Paie Mensuelle

Une fois confirmée, la paie est **clôturée**, ce qui :

- ✅ **Fige les données** (immutable, impossible à modifier)
- ✅ **Crée un audit trail** (qui a approuvé, quand)
- ✅ **Empêche les modifications ultérieures** (intégrité financière)
- ✅ **Valide les virements bancaires**

### Édition des Bulletins

Le système génère **automatiquement** pour chaque enseignant :

```
Bulletin de Paie - Septembre 2026

Enseignant: Abdou Diallo
Employee ID: EMP-2024-001
Période: 01-30 Septembre 2026

GAINS:
  Honoraire (40h × 10 000 F)        400 000 FCFA
TOTAL GAINS                          400 000 FCFA

DÉDUCTIONS:
  BRS (5%)                           -20 000 FCFA
TOTAL DÉDUCTIONS                     -20 000 FCFA

SALAIRE NET À PAYER                  380 000 FCFA

Mode de Paiement: CHÈQUE
Date de Paiement: 30 Septembre 2026

Signature: [Comptable] ________
```

**Format:** PDF  
**Stockage:** Base de données + Archive (3 ans minimum)  
**Accès:** Téléchargeable par enseignant via interface

---

## 🔧 IMPLÉMENTATION TECHNIQUE

### Code (Laravel)

```php
// Constants
const BRS_THRESHOLD = 25000;  // Seuil BRS
const BRS_RATE = 0.05;        // Taux 5%
const CHECK_THRESHOLD = 100000; // Seuil chèque

// Calcul complet
$hoursWorked = $attendanceSummary['hours'];
$hourlyRate = $teacher->hourly_rate;
$montantBrut = $hoursWorked * $hourlyRate;

// BRS appliquée si ≥ 25 000 FCFA
$brsAmount = 0;
if ($montantBrut >= self::BRS_THRESHOLD) {
    $brsAmount = round($montantBrut * self::BRS_RATE, 2);
}

// Montant net
$montantNet = $montantBrut - $brsAmount;

// Mode paiement
$modePayment = $montantNet >= self::CHECK_THRESHOLD ? 'check' : 'cash';

// Enregistrement
$payroll = Payroll::create([
    'teacher_id' => $teacher->id,
    'honorarium_amount' => $montantBrut,
    'brs_amount' => $brsAmount,
    'net_salary' => $montantNet,
    'payment_method' => $modePayment,
    'status' => 'calculated'
]);
```

### Tests Unitaires

```php
public function test_brs_applied_at_threshold() {
    $salary = 25000;
    $brs = calculateBRS($salary);
    $this->assertEquals(1250, $brs);  // 25000 × 5%
}

public function test_brs_not_applied_below_threshold() {
    $salary = 24999;
    $brs = calculateBRS($salary);
    $this->assertEquals(0, $brs);
}

public function test_payment_method_check() {
    $net = 100000;
    $method = determinePaymentMethod($net);
    $this->assertEquals('check', $method);
}

public function test_payment_method_cash() {
    $net = 99999;
    $method = determinePaymentMethod($net);
    $this->assertEquals('cash', $method);
}
```

---

## ✅ VÉRIFICATION DE COHÉRENCE

| Aspect | Vérifié | Notes |
|---|---|---|
| **Seuil BRS** | ✅ ≥ 25k FCFA | Supérieur **OU ÉGAL** |
| **Cas limite 25k** | ✅ 1 250 BRS | Correction appliquée |
| **Taux BRS** | ✅ 5% fixe | Sénégal |
| **Mode paiement** | ✅ ≥ 100k = chèque | Traçabilité |
| **Vacataires only** | ✅ Actuellement | Permanents futurs |
| **Audit trail** | ✅ Clôture fige | Intégrité data |
| **PDF bulletins** | ✅ Auto-généré | Archivage 3 ans |

---

## 📌 RÉSUMÉ POUR SOUTENANCE

**À mémoriser pour le jour J :**

1. ✅ **Montant Brut = Heures × Taux** (taux variable: 10k CM, 6k TD/TP)
2. ✅ **BRS appliquée si ≥ 25 000 FCFA** (5% fixe)
3. ✅ **Montant Net = Brut - BRS**
4. ✅ **Mode = CHÈQUE si Net ≥ 100 000 FCFA**
5. ✅ **Clôture par comptable = données figées**
6. ✅ **PDF généré automatiquement et archivé**

---

**FIN DU DOCUMENT DE RÉFÉRENCE**

*Cette spécification remplace tous les documents antérieurs avec la correction BRS ≥ au lieu de >*
