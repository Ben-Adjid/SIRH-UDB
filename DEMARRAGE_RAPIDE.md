# 🚀 DÉMARRAGE RAPIDE - SIRH

## ⏱️ 30 secondes pour être connecté!

### Étape 1: Double-clic sur le script

📍 Ouvrir: `C:\Users\aboub\Desktop\DIT\`

Chercher le fichier **`start_sirh.bat`**

Double-clic dessus ➡️ Deux fenêtres s'ouvrent automatiquement

```
✅ Fenêtre 1: "SIRH Backend - Port 8000" (ne pas fermer)
✅ Fenêtre 2: Frontend va démarrer (attendre 5 sec)
```

### Étape 2: Attendre les messages

Vous verrez dans le terminal du frontend:
```
VITE v5.x.x ready in xxx ms

➜  Local:   http://localhost:5173
```

### Étape 3: Ouvrir le navigateur

Cliquer sur le lien dans le terminal:
```
http://localhost:5173
```

Ou entrer manuellement dans la barre d'adresse.

### Étape 4: Vous êtes sur le login! 🎉

L'écran affiche:
- Logo UDB
- Champ Email
- Champ Mot de passe
- 5 boutons rapides colorés (Admin, RH, Comptable, etc.)

---

## 🧪 TEST SIMPLE

### Option A: Boutons rapides (Facile)

1. **Cliquer** sur le bouton "Comptable" (orange)
2. **L'email s'auto-remplit**: `compta@sirh.local`
3. **Saisir le mot de passe**: `password`
4. **Cliquer** "Accéder au portail"
5. ✅ **Vous êtes connecté!**

### Option B: Manuel

1. **Email**: `admin@sirh.local`
2. **Password**: `password`
3. **Cliquer** "Accéder au portail"
4. ✅ **Vous êtes connecté!**

---

## 📚 TOUS LES COMPTES DE TEST

Password pour TOUS: `password`

| Email | Rôle |
|-------|------|
| admin@sirh.local | Admin (Accès complet) |
| rh@sirh.local | RH Manager |
| compta@sirh.local | Comptable |
| academic@sirh.local | Chef d'UFR |
| secretaire@sirh.local | Secrétaire |
| prof@sirh.local | Enseignant |
| prof1@sirh.local | Enseignant |
| prof2@sirh.local | Enseignant |
| prof3@sirh.local | Enseignant |

(+ 15 autres enseignants disponibles)

---

## ❌ AÏE! Ça ne marche pas?

### Problème 1: Le backend ne démarre pas

**Symptôme**: La fenêtre "SIRH Backend" ferme immédiatement

**Solution**:
1. Ouvrir PowerShell dans `C:\Users\aboub\Desktop\DIT\backend`
2. Taper: `php artisan serve --port=8000`
3. Vérifier le message: "Laravel development server started"

### Problème 2: "Connection refused" au login

**Symptôme**: Message "Erreur de connexion au serveur"

**Cause**: Le backend n'est pas en ligne

**Solution**: Vérifier qu'une fenêtre "SIRH Backend" est ouverte et affiche du texte

### Problème 3: Port 8000 déjà utilisé

**Symptôme**: Message "Address already in use"

**Solution**:
```bash
# Utiliser un autre port
cd C:\Users\aboub\Desktop\DIT\backend
php artisan serve --port=9000

# Puis modifier C:\Users\aboub\Desktop\DIT\frontend\.env:
# VITE_API_URL=http://localhost:9000
```

### Problème 4: "npm: command not found"

**Symptôme**: Erreur dans le terminal du frontend

**Solution**: 
- Node.js n'est pas installé
- Télécharger depuis: https://nodejs.org (version LTS)
- Installer et relancer le script

### Problème 5: Port 5173 déjà utilisé

**Symptôme**: Le frontend affiche "http://localhost:5174" au lieu de 5173

**C'est NORMAL!** Utiliser le port affiché (5174, 5175, etc.)

---

## 🔧 SCRIPTS ALTERNATIFS

Si le fichier `.bat` ne fonctionne pas:

### Utiliser PowerShell (Recommandé pour Windows)

```powershell
# Clic droit sur start_sirh.ps1
# Sélectionner: "Run with PowerShell"
```

### Ou démarrage manuel (2 terminaux)

**Terminal 1 - Backend**:
```bash
cd C:\Users\aboub\Desktop\DIT\backend
php artisan serve --port=8000
```

Attendre le message:
```
Laravel development server started: http://127.0.0.1:8000
```

**Terminal 2 - Frontend**:
```bash
cd C:\Users\aboub\Desktop\DIT\frontend
npm run dev
```

Attendre le message:
```
Local:   http://localhost:5173
```

Puis ouvrir le lien.

---

## 📊 VÉRIFIER QUE TOUT FONCTIONNE

### Test Backend (depuis n'importe quel terminal)

```bash
curl http://localhost:8000/api/health
```

Doit répondre:
```json
{"status":"ok"}
```

### Test Login (depuis n'importe quel terminal)

```bash
curl -X POST http://localhost:8000/api/auth/login ^
  -H "Content-Type: application/json" ^
  -d "{\"email\":\"admin@sirh.local\",\"password\":\"password\"}"
```

Doit répondre avec un token et les infos de l'utilisateur.

---

## ✨ FONCTIONNALITÉS DISPONIBLES APRÈS CONNEXION

Une fois connecté, vous pouvez tester:

### 1. **Pointage** (Prof/Enseignant)
- Générer un PIN temporaire
- Check-in/Check-out
- Voir l'historique des pointages

### 2. **Paie** (Comptable)
- Calcul du brut
- Déduction BRS
- Calcul du net

### 3. **Profil**
- Informations personnelles
- Modifier les données
- Changer le mot de passe

### 4. **Admin** (Admin seul)
- Gestion des utilisateurs
- Gestion des enseignants
- Voir les audit logs

### 5. **RH** (RH Manager)
- Gestion des contrats
- Gestion des absences
- Rapports

---

## 🎯 RÉSUMÉ

| Étape | Action |
|-------|--------|
| 1️⃣ | Double-clic `start_sirh.bat` |
| 2️⃣ | Attendre 5-10 secondes |
| 3️⃣ | Ouvrir http://localhost:5173 |
| 4️⃣ | Cliquer sur un rôle OU saisir email |
| 5️⃣ | Saisir: `password` |
| 6️⃣ | Cliquer "Accéder au portail" |
| ✅ | CONNECTÉ! |

---

## 🆘 AIDE SUPPLÉMENTAIRE

Voir le fichier: **`SOLUTION_COMPLETE.md`**

Pour:
- Diagnostics détaillés
- Scripts alternatifs
- Troubleshooting avancé
- Réinitialisation base de données

---

**Bonne connexion! Système 100% opérationnel! 🚀**
