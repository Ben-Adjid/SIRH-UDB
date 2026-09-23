#!/bin/bash

echo "🔍 RECHERCHE DÉTAILLÉE DE BUGS"
echo "======================================"
echo ""

echo "1️⃣ DEBUG LOGS RESTANTS:"
grep -r "console\.log\|console\.warn" frontend/src --include="*.jsx" --include="*.js" | grep -v node_modules | wc -l
echo "   $(grep -r "console\.log\|console\.warn" frontend/src --include="*.jsx" --include="*.js" | grep -v node_modules | wc -l) debug logs trouvés"

echo ""
echo "2️⃣ VÉRIFIER LES ASYNC/AWAIT SANS TRY-CATCH:"
grep -A3 "const.*=.*await" frontend/src/pages/*.jsx | grep -B3 "catch" | wc -l
echo "   $(grep -r "await" frontend/src/pages/*.jsx | grep -v "catch\|try" | wc -l) await sans protection"

echo ""
echo "3️⃣ VÉRIFIER LES SETSTATE SANS GUARD:"
grep -n "setState\|set[A-Z]" frontend/src/pages/Dashboard.jsx | head -10

echo ""
echo "4️⃣ MEMORY LEAKS - useEffect sans cleanup:"
grep -B5 "setInterval\|setTimeout" frontend/src/pages/*.jsx | grep -A3 "useEffect" | head -10

echo ""
echo "5️⃣ UNDEFINED VARIABLES:"
grep -r "\.data\?" frontend/src --include="*.jsx" | wc -l
echo "   $(grep -r "\.data\?" frontend/src --include="*.jsx" | wc -l) optional chaining utilisés"

echo ""
echo "6️⃣ TRY SANS RETURN - ERREURS AVALÉES:"
grep -B2 "catch.*=>" frontend/src --include="*.js" --include="*.jsx" | grep -v ".catch(e =>" | head -5

echo ""
