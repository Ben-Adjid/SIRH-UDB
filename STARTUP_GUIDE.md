# 🚀 GUIDE DE DÉMARRAGE - SYSTÈME SIRH

## ⚠️ PROBLÈME COURANT: Erreur 401 au login

**Cause**: Le backend Laravel n'est pas démarré!

---

## 📋 CHECKLIST DE DÉMARRAGE

### **ÉTAPE 1: Ouvrir deux terminaux**

**Terminal 1** → Backend Laravel
**Terminal 2** → Frontend React

---

### **ÉTAPE 2: Démarrer le Backend (Terminal 1)**

```bash
# Aller dans le dossier backend
cd C:\Users\aboub\Desktop\DIT\backend

# Démarrer le serveur Laravel
php artisan serve
```

**Vous devriez voir:**
```
Starting Laravel development server: http://127.0.0.1:8000
```

✅ Le backend tourne maintenant sur: **http://localhost:8000**

---

### **ÉTAPE 3: Démarrer le Frontend (Terminal 2)**

```bash
# Aller dans le dossier frontend
cd C:\Users\aboub\Desktop\DIT\frontend

# Démarrer le serveur Vite
npm run dev
```

**Vous devriez voir:**
```
VITE v8.1.5 ready in XXX ms

➜  Local:   http://localhost:5173/
```

✅ Le frontend tourne maintenant sur: **http://localhost:5173**

---

### **ÉTAPE 4: Ouvrir le navigateur**

Aller à: **http://localhost:5173**

Vous devriez voir la page de login! 🎉

---

## 🔑 CREDENTIALS DE TEST

**Mot de passe pour tous: `password`**

| Email | Role | Description |
|-------|------|-------------|
| `admin@sirh.local` | Admin | Accès complet |
| `rh@sirh.local` | RH | Gestion RH |
| `compta@sirh.local` | Comptable | Gestion paie |
| `academic@sirh.local` | Chef UFR | Gestion académique |
| `prof@sirh.local` | Enseignant | Voir ses infos |
| `diop@sirh.local` | Enseignant | Voir ses infos |
| `sarr@sirh.local` | Enseignant | Voir ses infos |
| `secretaire@sirh.local` | Secrétaire | Support administratif |

---

## ✅ COMMENT TESTER

### **Test 1: Connexion**
1. Ouvrir http://localhost:5173
2. Cliquer sur bouton "Comptable"
3. Email s'auto-remplit: `compta@sirh.local`
4. Entrer password: `password`
5. Cliquer "Accéder au portail"
6. ✅ Vous devriez arriver au Dashboard

### **Test 2: Pointage**
1. Connecter avec `prof@sirh.local` / `password`
2. Aller à "Pointage" (si accessible)
3. Voir 5 pointages (2026-09-16 à 2026-09-20)
4. Chaque pointage: 08:00-10:00 = 2h

### **Test 3: Paie**
1. Connecter avec `compta@sirh.local` / `password`
2. Aller à "Paie"
3. Voir 3 paies calculées
4. Brut: 50,000 F
5. BRS: 2,500 F (5%)
6. Net: 47,500 F

---

## 🔧 TROUBLESHOOTING

### **Erreur: "Failed to load resource 401"**
❌ **Cause**: Backend pas démarré  
✅ **Solution**: Exécuter `php artisan serve` dans le terminal du backend

### **Erreur: "Port 8000 already in use"**
❌ **Cause**: Port déjà utilisé  
✅ **Solution**: Tuer le processus existant ou utiliser port différent:
```bash
php artisan serve --port=8001
```

### **Erreur: "npm: command not found"**
❌ **Cause**: Node.js pas installé  
✅ **Solution**: Installer Node.js depuis https://nodejs.org/

### **Erreur: "Module not found"**
❌ **Cause**: Dépendances pas installées  
✅ **Solution**: 
```bash
# Backend
composer install

# Frontend
npm install
```

### **Erreur: "Database connection error"**
❌ **Cause**: MySQL pas démarré ou DB pas créée  
✅ **Solution**:
```bash
# Vérifier MySQL est démarré
# Créer la DB
php artisan migrate
php artisan db:seed --class=CompleteSeeder
```

---

## 📊 VÉRIFICATION FINALE

Avant de commencer à tester, vérifier:

```bash
# Dans le terminal du backend
php artisan tinker --execute="
echo 'Users: ' . \App\Models\User::count() . \"\\n\";
echo 'Attendances: ' . \App\Models\Attendance::count() . \"\\n\";
echo 'Payrolls: ' . \App\Models\Payroll::count() . \"\\n\";
"
```

Vous devriez voir:
```
Users: 8
Attendances: 15
Payrolls: 3
```

✅ Si vous voyez ça = Système prêt!

---

## 🎯 RÉSUMÉ RAPIDE

| Étape | Commande | URL |
|-------|----------|-----|
| 1. Backend | `php artisan serve` | http://localhost:8000 |
| 2. Frontend | `npm run dev` | http://localhost:5173 |
| 3. Login | Email + password | http://localhost:5173/login |
| 4. Test | Naviguer dans l'app | Voir Pointage, Paie, etc. |

---

## 💡 TIPS

- **Logs backend**: Regardez le terminal du backend pour les erreurs
- **Logs frontend**: Ouvrez DevTools (F12) → Console
- **Reload page**: Ctrl+Shift+R (reload sans cache)
- **Password**: Tous les comptes utilisent `password`
- **Demo mode**: QUICK_ACCOUNTS aide à se connecter rapidement

---

**🚀 Vous êtes prêt! Bonne utilisation du système SIRH!**
