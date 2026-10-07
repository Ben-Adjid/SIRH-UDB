# 🎯 DÉMONSTRATION - RÈGLE POINTAGE 15 MINUTES

## ✅ RÈGLE IMPLÉMENTÉE: Fenêtre de ±15 minutes

Un professeur ne peut pointer que:
- **15 minutes AVANT** le début du cours
- **15 minutes APRÈS** la fin du cours

---

## 📋 TEST SCÉNARIO

### **Cas 1: Pointage AUTORISÉ (dans la fenêtre)**

**Professeur:** Prof Moussa Ndiaye  
**Email:** prof3@sirh.local  
**Mot de passe:** password  
**PIN:** 1690  

**Cours du mercredi 7 octobre:**
```
1️⃣ 08:00 - 10:00 (Fenêtre: 07:45 - 10:15)
2️⃣ 09:00 - 11:00 (Fenêtre: 08:45 - 11:15)  
3️⃣ 14:00 - 16:00 (Fenêtre: 13:45 - 16:15) ✅
4️⃣ 15:00 - 17:00 (Fenêtre: 14:45 - 17:15) ✅
5️⃣ 16:00 - 18:00 (Fenêtre: 15:45 - 18:15)
6️⃣ 17:00 - 19:00 (Fenêtre: 16:45 - 19:15)
```

**Heure actuelle:** 15:22  
**Résultat:** ✅ **POINTAGE ACCEPTÉ** (dans fenêtre du cours 14h-16h)

---

### **Cas 2: Pointage REJETÉ (hors fenêtre)**

Pour tester le rejet, essayer de pointer à:
- **07:30** → ❌ Hors fenêtre (avant 07:45)
- **11:30** → ❌ Hors fenêtre (après 11:15)  
- **17:30** → ❌ Hors fenêtre (après 17:15)

Message d'erreur:  
```
"Pointage possible entre: 07:45 - 10:15, 08:45 - 11:15, 13:45 - 16:15, 14:45 - 17:15, 15:45 - 18:15, 16:45 - 19:15"
```

---

### **Cas 3: Pas de cours aujourd'hui**

**Professeur:** Enseignant Test  
**Email:** prof@sirh.local  
**Mot de passe:** password

**Résultat:** ❌ **POINTAGE REJETÉ**  
Message: "Vous n'avez pas cours aujourd'hui"

---

## 🔍 MESSAGES AFFICHÉS

### ✅ Succès:
```
"Pointage arrivée enregistré à HH:MM — [Nom du cours]"
```

### ❌ Erreur - Pas de cours:
```
"Vous n'avez pas cours aujourd'hui"
Error: no_courses_today
```

### ❌ Erreur - Hors fenêtre:
```
"Pointage possible entre: HH:MM - HH:MM, ..."
Error: outside_time_window
Valid times: [heures]
```

---

## 📊 IMPACT

✅ **Sécurité:** Impossible de pointer en dehors des cours  
✅ **Précision:** Heures de travail exactes (±15 min tolérance)  
✅ **Intégrité:** Pas de fraude possible  
✅ **Transparence:** Messages clairs en français  

---

**Bonne démo! 🚀**
