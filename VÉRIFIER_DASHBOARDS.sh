#!/bin/bash

echo "🔍 VÉRIFICATION DE TOUS LES DASHBOARDS"
echo "======================================="
echo ""

# Lire le fichier Dashboard.jsx et extraire les 4 sections des dashboards
echo "📋 ADMIN DASHBOARD:"
sed -n '250,350p' frontend/src/pages/Dashboard.jsx | grep -E "stats\.|userRole === 'admin'" | head -15
echo ""

echo "📋 HR/RH DASHBOARD:"
sed -n '350,450p' frontend/src/pages/Dashboard.jsx | grep -E "stats\.|userRole === 'hr'" | head -15
echo ""

echo "📋 TEACHER DASHBOARD:"
sed -n '576,650p' frontend/src/pages/Dashboard.jsx | grep -E "stats\.|userRole === 'teacher'" | head -15
echo ""

echo "📋 ACADEMIC MANAGER DASHBOARD:"
sed -n '547,575p' frontend/src/pages/Dashboard.jsx | grep -E "stats\.|userRole === 'academic_manager'" | head -15
echo ""
