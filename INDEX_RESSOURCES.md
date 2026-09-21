# 📚 INDEX - RESSOURCES COMPLÈTES SIRH

## 🎯 SITUATION

**Le système de pointage SIRH est 100% fonctionnel!**

Le problème de connexion utilisateur est **RÉSOLU**. Tous les utilisateurs peuvent maintenant se connecter avec le mot de passe unifié `password`.

---

## 📖 GUIDES DISPONIBLES

### Pour DÉMARRER IMMÉDIATEMENT

#### 1. **`DEMARRAGE_RAPIDE.md`** ⭐ START HERE
- **Usage**: Première connexion au système
- **Durée**: 5 minutes
- **Contenu**:
  - 3 étapes simples
  - Boutons rapides (démo)
  - Comptes de test
  - Troubleshooting basique

**→ Lire en premier!**

---

### Pour COMPRENDRE LE SYSTÈME

#### 2. **`README_CONNEXION.md`** 
- **Usage**: Vue d'ensemble complète
- **Durée**: 10 minutes
- **Contenu**:
  - État actuel du système
  - Architecture (Backend/Frontend/BD)
  - Tous les comptes disponibles
  - Fonctionnalités testées
  - Cas d'usage réels

**→ Lire après le démarrage!**

---

### Pour CONFIGURATIONS AVANCÉES

#### 3. **`SOLUTION_COMPLETE.md`**
- **Usage**: Déploiement et diagnostics
- **Durée**: 20 minutes
- **Contenu**:
  - Options de démarrage (1-2 terminaux)
  - Vérification manuelle (curl)
  - Troubleshooting détaillé
  - Réinitialisation BD
  - Configuration personnalisée (ports, etc.)

**→ Lire si problèmes rencontrés**

---

### Pour VALIDER LE SYSTÈME

#### 4. **`CHECKLIST_SYSTEM.md`**
- **Usage**: Validation complète avant production
- **Durée**: 30 minutes (test)
- **Contenu**:
  - Checklist pré-démarrage
  - Tests API (curl)
  - Tests Frontend (UI)
  - Tests fonctionnels (Pointage, Paie, etc.)
  - Performance

**→ Utiliser avant de valider le système**

---

## 🔧 SCRIPTS DISPONIBLES

### Scripts Windows (`.bat` et `.ps1`)

#### 1. **`start_sirh.bat`** ⭐ RECOMMANDÉ
```bash
Double-clic → Lance tout automatiquement
```
- ✅ Vérifie PHP, Node, npm
- ✅ Démarre Backend
- ✅ Attends initialisation
- ✅ Démarre Frontend
- ✅ Ouvre le navigateur

**Usage**: Démarrage normal du système

**Quand l'utiliser**: Tous les jours

---

#### 2. **`start_sirh.ps1`**
```powershell
Clic droit → Run with PowerShell
```
- ✅ Version PowerShell du `.bat`
- ✅ Meilleur feedback (couleurs)
- ✅ Gestion d'erreurs améliorée

**Usage**: Alternative au `.bat`

**Quand l'utiliser**: Si `.bat` ne fonctionne pas

---

#### 3. **`test_sirh.ps1`** ⭐ DIAGNOSTIC
```powershell
Clic droit → Run with PowerShell
```
- ✅ Vérifie Backend
- ✅ Teste Login API
- ✅ Vérifie Base de données
- ✅ Vérifie Frontend
- ✅ Teste tous les comptes

**Usage**: Diagnostic complet du système

**Quand l'utiliser**: 
- Avant le démarrage (vérifier env)
- Si problèmes de connexion
- Tests de configuration

---

## 📊 FICHIERS DE CONFIGURATION

### `frontend/.env`
```env
VITE_API_URL=http://localhost:8000
VITE_DEMO_MODE=true
VITE_APP_NAME=SIRH
```
- **À modifier si**: Port Backend change

### `backend/.env`
```env
APP_NAME=SIRH
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```
- **À modifier si**: Configuration personnalisée

---

## 🎯 WORKFLOW - SCÉNARIOS

### Scénario 1: Première utilisation
```
1. Lire: DEMARRAGE_RAPIDE.md
2. Exécuter: start_sirh.bat
3. Ouvrir: http://localhost:5173
4. Test: Cliquer "Comptable" → password
5. ✅ Connecté!
```

### Scénario 2: Test complet avant production
```
1. Exécuter: test_sirh.ps1
2. Lire: CHECKLIST_SYSTEM.md
3. Suivre: Tous les tests
4. Valider: Tous les ✅
5. ✅ Prêt pour production
```

### Scénario 3: Problème rencontré
```
1. Lire: DEMARRAGE_RAPIDE.md (section "Problème")
2. Si persiste: SOLUTION_COMPLETE.md (Troubleshooting)
3. Si toujours pas résolu: Exécuter test_sirh.ps1
4. Diagnostic → Fix → Relancer
```

### Scénario 4: Changer configuration (ports, etc.)
```
1. Lire: SOLUTION_COMPLETE.md (Diagnostics)
2. Modifier: frontend/.env ou backend/.env
3. Redémarrer: start_sirh.bat
4. Valider avec: test_sirh.ps1
```

---

## 👥 COMPTES DE TEST

**Tous avec password: `password`**

| Email | Rôle | Usage |
|-------|------|-------|
| admin@sirh.local | Admin | Tous les droits |
| rh@sirh.local | RH Manager | Gestion RH + Paie |
| compta@sirh.local | Comptable | Paie seule |
| academic@sirh.local | Chef d'UFR | Académique |
| secretaire@sirh.local | Secrétaire | Support |
| prof@sirh.local | Enseignant | Test Pointage |
| prof1@sirh.local | Enseignant | Test Pointage |
| prof2@sirh.local | Enseignant | Test Pointage |
| prof3@sirh.local | Enseignant | Test Pointage |
| + 15 autres | Enseignants | Base données |

---

## 🚀 DÉMARRAGE RAPIDE (Résumé)

```bash
# Étape 1: Lancer le script
Double-clic: start_sirh.bat

# Étape 2: Attendre 10 secondes
(Deux fenêtres s'ouvrent)

# Étape 3: Ouvrir le navigateur
http://localhost:5173

# Étape 4: Connexion
Email: admin@sirh.local
Password: password

# Étape 5: ✅ CONNECTÉ!
```

**Durée totale**: 1-2 minutes

---

## ✅ VÉRIFICATION SYSTÈME

### Quick Check (2 minutes)
```bash
# Terminal
curl http://localhost:8000/api/health
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@sirh.local","password":"password"}'
```

### Full Check (10 minutes)
```bash
# Exécuter le script
.\test_sirh.ps1
```

---

## 📁 STRUCTURE FICHIERS

```
C:\Users\aboub\Desktop\DIT\
│
├── 📋 Documentation (Guides)
│   ├── DEMARRAGE_RAPIDE.md ⭐
│   ├── README_CONNEXION.md
│   ├── SOLUTION_COMPLETE.md
│   ├── CHECKLIST_SYSTEM.md
│   └── INDEX_RESSOURCES.md (ce fichier)
│
├── 🚀 Scripts (Démarrage)
│   ├── start_sirh.bat ⭐
│   ├── start_sirh.ps1
│   └── test_sirh.ps1
│
├── 📦 Backend
│   ├── app/
│   ├── routes/
│   ├── database/
│   ├── .env
│   └── composer.json
│
├── 🎨 Frontend
│   ├── src/
│   ├── public/
│   ├── .env
│   └── package.json
│
└── 🗄️ Base de Données
    └── backend/database/database.sqlite
```

---

## 🎓 FONCTIONNALITÉS DISPONIBLES

### ✅ Pointage
- Générer PIN temporaire
- Check-in / Check-out
- Historique (5 derniers)
- Déclarer disponibilité
- Rapport absence

### ✅ Paie
- Calcul Brut
- Déduction BRS
- Calcul Net
- Détails ligne
- Export PDF
- Validation
- Marquer comme payée

### ✅ Profil
- Infos personnelles
- Modifier données
- Changer mot de passe
- Vérification email
- Documents

### ✅ Administration
- Gestion utilisateurs
- Gestion enseignants
- Gestion UFR
- Audit logs
- Rapports

### ✅ RH
- Gestion contrats
- Gestion absences
- Gestion congés
- Rapports

---

## 🐛 TROUBLESHOOTING RAPIDE

| Problème | Solution | Fichier |
|----------|----------|---------|
| Ça ne démarre pas | Voir DEMARRAGE_RAPIDE | DEMARRAGE_RAPIDE.md |
| Backend connection refused | Vérifier .env | SOLUTION_COMPLETE.md |
| Port already in use | Changer port | SOLUTION_COMPLETE.md |
| npm not found | Installer Node.js | DEMARRAGE_RAPIDE.md |
| Identifiants invalides | Réinit passwords | SOLUTION_COMPLETE.md |
| Test complet avant prod | Suivre checklist | CHECKLIST_SYSTEM.md |

---

## 🔍 RESSOURCES ADDITIONNELLES

### Anciens Rapports (Historique)
- `AUDIT_COMPLET_FINAL.md` - Audit système complet
- `CONNEXION_PROBLEMES.md` - Problèmes antérieurs
- `ERREURS_API.md` - Erreurs API identifiées
- `FINAL_REPORT.md` - Rapport final

### Guides Spécifiques
- `GUIDE_REDEMARRAGE_RAPIDE.md` - Redémarrage
- `GUIDE_COMPLET_TEST.md` - Tests complets

---

## 📞 SUPPORT

### Pour débuter
1. Lire: `DEMARRAGE_RAPIDE.md`
2. Exécuter: `start_sirh.bat`

### Pour des problèmes
1. Consulter: `SOLUTION_COMPLETE.md`
2. Exécuter: `test_sirh.ps1`

### Pour validation
1. Consulter: `CHECKLIST_SYSTEM.md`
2. Cocher tous les ✅

---

## ✨ RÉSUMÉ

| Ressource | Quand? | Durée |
|-----------|--------|-------|
| DEMARRAGE_RAPIDE.md | Première utilisation | 5 min |
| README_CONNEXION.md | Comprendre le système | 10 min |
| start_sirh.bat | Chaque démarrage | 30 sec |
| test_sirh.ps1 | Diagnostic | 10 min |
| CHECKLIST_SYSTEM.md | Avant production | 30 min |
| SOLUTION_COMPLETE.md | Problèmes avancés | 20 min |

---

## 🎉 FINAL

**Vous avez tout ce qu'il faut pour:**
- ✅ Démarrer le système
- ✅ Tester la connexion
- ✅ Utiliser le pointage
- ✅ Diagnostiquer les problèmes
- ✅ Valider pour production

**Le système SIRH est 100% opérationnel! 🚀**

---

**Bon utilisation! Bienvenue sur SIRH!** 🎓
