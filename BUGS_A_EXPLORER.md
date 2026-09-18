# 🔍 Bugs à Explorer et Corrections Recommandées

## 1. **Notifications en temps réel** - CRITIQUE
**Pages affectées:** Toutes  
**Problème:** "Y'a problème de notification d'événement en temps réel lors d'une demande de confirmation, annulation, rejet etc..."

### Diagnostic:
- Les notifications sont envoyées au backend mais ne sont pas reçues en temps réel au frontend
- Possibles causes:
  - [ ] WebSocket/SignalR non configuré
  - [ ] Service de notifications non initialisé
  - [ ] Événements Laravel non écoutés correctement

### Action recommandée:
- Vérifier `backend/app/Events/` pour les événements disponibles
- Vérifier si Laravel Echo est configuré en frontend
- Vérifier les fichiers `frontend/src/services/` pour les listeners

### Fichiers à vérifier:
- `backend/app/Events/`
- `frontend/src/services/notifications.js` (ou équivalent)
- `frontend/src/main.jsx` (initialisation Echo)

---

## 2. **Bug de création - liste vs. recherche**
**Pages affectées:** Homeworks.jsx (possiblement), Schedules.jsx  
**Problème:** "Quand je crée X, au lieu d'afficher la liste, ça remplit le champ recherche"

### Diagnostic possible:
- Peut-être un problème de focus qui va sur le champ de recherche
- Ou un rendu partiel de la page
- Ou le `setSearchTerm('')` ne déclenche pas immédiatement le rerender

### Investigation:
1. Tester manuellement en créant un devoir
2. Observer si la liste est affichée ou si le champ de recherche a le focus
3. Vérifier les useEffect pour les dépendances manquantes

### Fichiers à vérifier:
- `frontend/src/pages/Homeworks.jsx` (lignes 96-100)
- `frontend/src/pages/Schedules.jsx`

---

## 3. **Gestion des honoraires - Heures non prise en compte**
**Page:** Payroll.jsx  
**Problème:** "Pour gestion des honoraires, il prend pas l'heure du prof et applique la règle qui a été fixée"

### Diagnostic possible:
- Les heures pointées ne sont pas chargées correctement
- Le calcul du backend ne respecte pas les heures fournis
- Le formulaire n'envoie pas les heures au backend

### Code à vérifier:
**Payroll.jsx ligne 93-96:**
```javascript
api.get('/payroll/attendance-hours', { 
    params: { teacher_id: salaryForm.teacher_id, year, month }, 
    signal: controller.signal 
})
.then(response => setAttendanceSummary({ 
    hours: Number(response.data?.hours || 0),  // ← Vérifier si cela est correct
    incomplete: Number(response.data?.incomplete || 0) 
}))
```

**Payroll.jsx ligne 106:**
```javascript
hours_worked: attendanceSummary.hours,  // ← Est-ce que cela est bien envoyé?
```

### Actions recommandées:
1. [ ] Vérifier l'API `/payroll/attendance-hours` pour voir ce qu'elle retourne
2. [ ] Vérifier l'API `/payroll/calculate` pour voir comment elle traite `hours_worked`
3. [ ] Ajouter des logs pour debugger les valeurs
4. [ ] Tester manuellement avec des heures connues

### Backend à vérifier:
- `backend/app/Http/Controllers/Api/PayrollController.php`
- `backend/app/Models/Payroll.php`

---

## 4. **Téléversement de documents - Permissions**
**Page:** Documents.jsx  
**Problème:** "Y'a problème lors que je veux téléverser un document et je peux pas ni supprimer ou modifier ça"

### Diagnostic possible:
- Permissions insuffisantes pour certains rôles
- Le formulaire d'édition n'affiche pas correctement
- Le téléversement échoue silencieusement

### Code à vérifier:
**Documents.jsx ligne 15:**
```javascript
const canSelectTeacher = hasAnyRole(user, ['admin', 'hr_manager', 'secretary', 'accountant']);
```

### Actions recommandées:
1. [ ] Vérifier que le rôle de l'utilisateur autorise le téléversement
2. [ ] Vérifier les droits d'API dans le backend
3. [ ] Tester avec différents rôles
4. [ ] Ajouter plus de logs d'erreur

---

## 5. **Pointage - Certains rôles ne peuvent pas pointer**
**Page:** Attendance.jsx  
**Problème:** "Est-ce logique que un chef UFR/RH/Secrétaire/Comptable ne pointe pas?"

### Diagnostic:
- C'est peut-être volontaire (logique métier)
- Ou c'est un oubli dans les permissions

### Vérifications:
1. [ ] Clarifier avec l'utilisateur si c'est intentionnel
2. [ ] Vérifier les permissions dans Attendance.jsx
3. [ ] Vérifier les endpoints d'API pour les restrictions

### Fichiers à vérifier:
- `frontend/src/pages/Attendance.jsx`
- `backend/app/Http/Controllers/Api/AttendanceController.php`

---

## 6. **Affichage emploi du temps - Ne s'affiche pas**
**Page:** Schedules.jsx  
**Problème:** "Y'a aussi le problème lors de creation d'emploi du temps, rien s'affiche"

### Investigation:
1. [ ] Vérifier que les données sont bien chargées
2. [ ] Vérifier que le composant rend correctement
3. [ ] Regarder la console du navigateur pour les erreurs

### Fichiers à vérifier:
- `frontend/src/pages/Schedules.jsx`

---

## 📋 Checklist de Testage

Après chaque correction, tester:

- [ ] **Créer un élément**: UFR, Matière, Salle, Utilisateur
- [ ] **Voir la liste** s'afficher (pas la recherche)
- [ ] **Modifier un élément** avec succès
- [ ] **Modifier un élément** avec erreur (ex: champ vide) - vérifier que la modale reste
- [ ] **Supprimer un élément** avec succès
- [ ] **Supprimer un élément** avec erreur - vérifier que la modale se ferme
- [ ] **Demander un congé** et voir la liste
- [ ] **Approuver/Rejeter un congé** avec erreur
- [ ] **Téléverser un document** et modifier/supprimer
- [ ] **Ajouter prime/retenue** et vérifier le formulaire se réinitialise

---

## 🎯 Priorité

1. **HAUTE:** Notifications en temps réel (affecte UX critique)
2. **HAUTE:** Gestion des honoraires (affecte paie)
3. **MOYENNE:** Téléversement documents (affecte workflows)
4. **BASSE:** Bug de création/liste (cosmétique possible)
5. **BASSE:** Pointage de certains rôles (logique à clarifier)

---

**Mise à jour:** 2026-09-18
