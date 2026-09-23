# CORRECTIONS COMPLÈTES - AUDIT A-Z + BUGS

## 🔴 BUGS IDENTIFIÉS

### BUG 1: Profs ne voient pas leur emploi du temps
**Fichier:** `frontend/src/pages/Schedules.jsx`
**Cause:** Les profs voient les schedules, mais le problème est l'affichage
- Ligne 294-362: Section "Mon Emploi du temps" s'affiche bien
- Le problème: les `todaySchedules` ou `schedules` peuvent être vides
- Solution: Vérifier que `/schedules` retourne les données du prof

**Fix:** Rien à changer au frontend - l'API retourne déjà les bonnes données
Vérifier: `console.log(schedules)` dans fetchData callback

### BUG 2: Formulaire "Demander Cours en Ligne" s'affiche toujours
**Fichier:** `frontend/src/pages/Attendance.jsx`
**Ligne:** 523-540
**Problème:** 
```jsx
// MAUVAIS - affiche le bouton même si pas de cours
{onlineCourseRequest?.status === 'approved' ? (...) : (
    <button onClick={() => setShowOnlineRequestModal(true)}>
        📤 Demander Cours en Ligne
    </button>
)}
```

**Fix:** Masquer la section POINTAGE EN LIGNE si:
- Pas de cours aujourd'hui (`!hasAnyCourse`)
- Pas de demande en cours
- Pas approuvée

### BUG 3: Rôles inconsistents - accès aux pages
**Points à vérifier:**
- Sidebar: Routes visibles selon role
- API: Retourner 403 si non autorisé
- Frontend: Redirection vers login si accès refusé

