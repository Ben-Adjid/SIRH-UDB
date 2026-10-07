# 🎯 DÉMONSTRATION POINTAGE - VENDREDI 9 OCTOBRE 2026

## **SCÉNARIO DE DÉMO**

Montrer le **contrôle d'accès au pointage**: un professeur ne peut pointer que les jours où il a cours.

---

## **CONFIGURATION**

### **1️⃣ PROFESSEUR QUI A COURS LE VENDREDI (Peut pointer)**

**Détails:**
- **Nom:** Prof Moussa Ndiaye
- **Email:** prof3@sirh.local
- **Mot de passe:** password
- **Jour:** Vendredi 9 octobre 2026
- **Horaire du cours:** 08:00 - 10:00
- **Status:** ✅ **PEUT POINTER**

**Accès de test:**
```
Email: prof3@sirh.local
Mot de passe: password
PIN de pointage: À configurer dans le dashboard
```

---

### **2️⃣ PROFESSEUR SANS COURS LE VENDREDI (Ne peut pas pointer)**

**Détails:**
- **Nom:** Enseignant Test
- **Email:** enseignant@sirh.local
- **Mot de passe:** password
- **Jour:** Vendredi 9 octobre 2026
- **Horaire du cours:** AUCUN
- **Status:** ❌ **NE PEUT PAS POINTER**

**Accès de test:**
```
Email: enseignant@sirh.local
Mot de passe: password
PIN de pointage: À configurer dans le dashboard
```

---

## **WORKFLOW DE DÉMO**

### **ÉTAPE 1: Connexion Prof Moussa Ndiaye (Qui a cours)**

1. URL: `http://localhost:8000/spa`
2. Email: `prof3@sirh.local`
3. Mot de passe: `password`
4. ✅ **Connexion réussie**

### **ÉTAPE 2: Accéder au pointage**

1. Menu: **Pointage** (Attendance)
2. Date: **Vendredi 9 octobre 2026**
3. ✅ **Affiche le formulaire de pointage**
4. **Message:** "Vous avez un cours à 08:00-10:00 aujourd'hui"
5. ✅ **Permet de pointer (check-in)**

---

### **ÉTAPE 3: Tentative de pointage Enseignant Test (Sans cours)**

1. Se déconnecter
2. Email: `enseignant@sirh.local`
3. Mot de passe: `password`
4. ✅ **Connexion réussie** (compte actif)
5. Aller à: **Pointage**
6. Date: **Vendredi 9 octobre 2026**
7. ❌ **Message d'erreur:** "Vous n'avez pas cours aujourd'hui"
8. ❌ **Pointage bloqué** (cannot point out)

---

## **POINTS CLÉS À MONTRER AU JURY**

✅ **Contrôle d'accès intelligent**
- Système vérifie si le prof a cours ce jour
- Seuls les profs avec cours peuvent pointer
- Profs sans cours sont bloqués

✅ **Sécurité et intégrité**
- Pas de pointage fictif
- Impossible de tricher sur les heures
- Audit complet de qui a pointé quand

✅ **Facilité d'utilisation**
- Messages clairs expliquant pourquoi le pointage est bloqué
- Interface simple: juste 1 click pour pointer

✅ **Respect du planning**
- System respecte le planning académique
- Chaque prof ne peut pointer que ses jours de cours
- Automatique, pas de configuration manuelle

---

## **DONNÉES SUPPLÉMENTAIRES - TOUS LES PROFS LE VENDREDI**

```
PEUT POINTER (A COURS):
✅ Prof Moussa Ndiaye (08:00-10:00)
✅ Prof Amadou Diallo (14:00-16:00)  
✅ Prof Fatou Sow (08:00-10:00)
✅ Prof Moussa Ndiaye (16:00-18:00)
✅ Khadija Diop (08:00-10:00)
✅ Aïcha Ba (14:00-16:00)
✅ Oumar Faye (08:00-10:00)
✅ Khadija Diop (16:00-18:00)
✅ Aïcha Ba (14:00-16:00)
✅ Oumar Faye (16:00-18:00)
... (80 cours programmés le vendredi)

NE PEUT PAS POINTER (SANS COURS):
❌ Enseignant Test
❌ Mamadou Fall
❌ Rokhaya Gueye
❌ Ibrahima Cissé
... (autres profs sans cours ce jour)
```

---

## **RÉPONSES AUX QUESTIONS DU JURY**

**Q: Comment le système vérifie-t-il que le prof a cours?**
```
R: Il consulte la table `schedules` pour le jour spécifique.
   Si le prof figure dans le schedule du jour = peut pointer
   Sinon = accès refusé
```

**Q: Un admin peut-il forcer un pointage?**
```
R: Oui, l'admin a accès à une page d'administration
   pour corriger ou ajouter des pointages manuellement
   Mais c'est tracé dans l'audit log.
```

**Q: Que se passe-t-il si le planning change?**
```
R: Si on supprime le cours du planning,
   le prof ne peut plus pointer automatiquement
   (le système re-vérifie à chaque connexion)
```

---

## **SCRIPT DE DÉMO (4 minutes)**

```
TEMPS    | ACTION                                    | DURÉE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
0:00     | Connexion Prof Moussa (a cours)          | 30 sec
0:30     | Afficher le pointage (réussi)            | 1 min
1:30     | Déconnexion                               | 15 sec
1:45     | Connexion Enseignant Test (sans cours)   | 30 sec
2:15     | Tenter le pointage (bloqué)              | 1 min
3:15     | Montrer le message d'erreur              | 45 sec
4:00     | TOTAL                                     | 4 min ✅
```

---

**Bonne chance! 🚀**
