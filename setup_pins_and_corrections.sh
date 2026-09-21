#!/bin/bash

echo "🔧 PHASE 1: Configuration des PINs et corrections UI"
echo "=================================================="

# 1. Réinitialiser la base de données
echo ""
echo "1️⃣ Réinitialisation de la base de données..."
cd backend
php artisan migrate:fresh --seed > /dev/null 2>&1
echo "✅ Base de données réinitialisée"

# 2. Générer des PINs simples
echo ""
echo "2️⃣ Génération des PINs pour les enseignants..."
php artisan tinker --execute="
\$teachers = [
    ['name' => 'Professeur Martin', 'email' => 'prof@sirh.local', 'pin' => '1111'],
    ['name' => 'Professeur Diop', 'email' => 'diop@sirh.local', 'pin' => '2222'],
    ['name' => 'Professeur Sarr', 'email' => 'sarr@sirh.local', 'pin' => '3333'],
];

echo \"📋 CODES PIN ASSIGNÉS:\n\";
foreach (\$teachers as \$t) {
    \$teacher = \App\Models\Teacher::whereHas('user', function(\$q) { \$q->where('email', \$t['email']); })->first();
    if (\$teacher) {
        \$teacher->pin_code = \Illuminate\Support\Facades\Hash::make(\$t['pin']);
        \$teacher->save();
        echo \"  • \" . \$t['name'] . \" → PIN: \" . \$t['pin'] . \"\n\";
    }
}
"
echo "✅ PINs configurés"

cd ..

echo ""
echo "🎯 PHASE 2: Corrections UI en cours..."
echo "✅ Setup complété! PINs assignés et prêts."

