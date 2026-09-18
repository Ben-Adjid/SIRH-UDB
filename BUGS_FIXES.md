# Bugs Identifiés et Corrections

## ✅ Bug #1: Modale de suppression qui ne se ferme pas après erreur
**Pages affectées:** Ufrs.jsx, Subjects.jsx, Classrooms.jsx, Teachers.jsx, Users.jsx, Homeworks.jsx
**Problème:** Quand on confirme une suppression et qu'une erreur se produit, la modale reste affichée
**Cause:** Dans ConfirmModal.jsx, `onClose()` n'était appelé que dans le `try` block, pas en cas d'erreur
**Fix:** Déplacer `onClose()` dans le `finally` block pour qu'il se ferme toujours
**Status:** ✅ FIXÉ (ConfirmModal.jsx ligne 99)

---

## Bug #2: Validation de congé échoue - modale reste ouverte
**Page:** Leaves.jsx
**Problème:** "Quand je valide la demande de congé ou si je rejette, la demande reste toujours sous demande de validation"
**Cause:** Si le PATCH échoue, `setRejecting(null)` n'est pas appelé et `loadLeaves()` n'est pas en finally
**Fix:** Mettre `setRejecting(null)` et `loadLeaves()` dans finally block
**Status:** ❌ À FIXER

---

## Bug #3: Création affiche recherche au lieu de liste
**Pages affectées:** Ufrs.jsx, Subjects.jsx, Classrooms.jsx, Homeworks.jsx, Teachers.jsx, Users.jsx
**Problème:** "Quand je crée un UFR/matière/salle, au lieu d'afficher la liste, ça remplit le champ de recherche"
**Cause:** À identifier - possiblement un problème avec le render/layout après création
**Status:** 🔍 ANALYSE EN COURS

---

## Bug #4: Télé-versement de documents ne fonctionne pas
**Pages affectées:** Documents.jsx, Leaves.jsx (possible)
**Problème:** "Y'a problème lors que je veux téléverser un document et je peux pas ni supprimer ou modifier ça"
**Status:** 🔍 À EXPLORER

---

## Bug #5: Pas de notifications en temps réel
**Toutes les pages**
**Problème:** "Y'a problème de notification d'événement en temps réel lors d'une demande de confirmation, annulation, rejet etc."
**Status:** 🔍 À EXPLORER

---

## Bug #6: Pointage non disponible pour certains rôles
**Pages affectées:** Attendance.jsx
**Problème:** Chef UFR, RH, Secrétaire, Comptable ne peuvent pas pointer
**Question:** "Est-ce logique qu'un chef UFR/RH/Secrétaire/Comptable ne pointe pas?"
**Status:** 🔍 À CLARIFIER - Logique métier à vérifier

---

## Bug #7: Gestion des honoraires ne fonctionne pas
**Pages:** Payroll.jsx
**Problème:** "Pour gestion des honoraires, il prend pas l'heure du prof et applique la règle qui a été fixée"
**Status:** 🔍 À EXPLORER
