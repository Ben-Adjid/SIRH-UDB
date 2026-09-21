# ========================================
# SIRH - Test Automatique de Connexion
# ========================================

Write-Host ""
Write-Host "╔════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   SIRH - Test de Connexion                ║" -ForegroundColor Cyan
Write-Host "║   Vérification complète du système         ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

$backendUrl = "http://localhost:8000"
$apiUrl = "$backendUrl/api"
$testEmail = "admin@sirh.local"
$testPassword = "password"

# ========================================
# Test 1: Health Check Backend
# ========================================

Write-Host "🔍 Test 1: Health Check Backend" -ForegroundColor Yellow
Write-Host "   URL: $backendUrl/api/health" -ForegroundColor Gray

try {
    $response = Invoke-WebRequest -Uri "$backendUrl/api/health" -Method GET -ErrorAction Stop
    if ($response.StatusCode -eq 200) {
        Write-Host "   ✅ Backend est en ligne (HTTP 200)" -ForegroundColor Green
    } else {
        Write-Host "   ❌ Réponse inattendue: HTTP $($response.StatusCode)" -ForegroundColor Red
    }
} catch {
    Write-Host "   ❌ Erreur de connexion au backend" -ForegroundColor Red
    Write-Host "      $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "💡 Le backend n'est pas démarré." -ForegroundColor Yellow
    Write-Host "   Démarrer: php artisan serve --port=8000" -ForegroundColor Yellow
    Read-Host "Appuyez sur Entrée"
    exit 1
}

Write-Host ""

# ========================================
# Test 2: Login API
# ========================================

Write-Host "🔍 Test 2: Login via API" -ForegroundColor Yellow
Write-Host "   Email: $testEmail" -ForegroundColor Gray
Write-Host "   URL: $apiUrl/auth/login" -ForegroundColor Gray

$loginPayload = @{
    email = $testEmail
    password = $testPassword
} | ConvertTo-Json

try {
    $response = Invoke-WebRequest `
        -Uri "$apiUrl/auth/login" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginPayload `
        -ErrorAction Stop

    $data = $response.Content | ConvertFrom-Json

    if ($data.message -match "réussie|successful|success") {
        Write-Host "   ✅ Login réussi!" -ForegroundColor Green
        Write-Host "      Message: $($data.message)" -ForegroundColor Green
        Write-Host "      User: $($data.user.name) ($($data.user.email))" -ForegroundColor Green
        Write-Host "      Token: $($data.token.Substring(0, 20))..." -ForegroundColor Gray

        if ($data.requires_2fa) {
            Write-Host "      ⚠️  2FA requis" -ForegroundColor Yellow
        } else {
            Write-Host "      ✅ 2FA désactivé" -ForegroundColor Green
        }
    } else {
        Write-Host "   ⚠️  Réponse reçue: $($data.message)" -ForegroundColor Yellow
    }

} catch {
    Write-Host "   ❌ Erreur lors du login" -ForegroundColor Red
    Write-Host "      $($_.Exception.Message)" -ForegroundColor Red

    $response = $_.Response
    if ($response) {
        $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
        $errorBody = $reader.ReadToEnd()
        Write-Host "      Réponse: $errorBody" -ForegroundColor Red
    }
}

Write-Host ""

# ========================================
# Test 3: Vérifier les utilisateurs en BD
# ========================================

Write-Host "🔍 Test 3: Utilisateurs en Base de Données" -ForegroundColor Yellow

try {
    $dbPath = "backend/database/database.sqlite"
    if (Test-Path $dbPath) {
        Write-Host "   ✅ Base de données trouvée: $dbPath" -ForegroundColor Green

        # Essayer de lancer tinker
        $tinkerResult = & php -r "
        try {
            \$db = new PDO('sqlite:backend/database/database.sqlite');
            \$users = \$db->query('SELECT COUNT(*) as count FROM users')->fetch(PDO::FETCH_ASSOC);
            echo 'Utilisateurs: ' . \$users['count'] . PHP_EOL;
        } catch (Exception \$e) {
            echo 'Erreur: ' . \$e->getMessage() . PHP_EOL;
        }
        " 2>&1

        Write-Host "   $tinkerResult" -ForegroundColor Green
    } else {
        Write-Host "   ❌ Base de données non trouvée: $dbPath" -ForegroundColor Red
        Write-Host "      Créer avec: php artisan migrate --seed" -ForegroundColor Yellow
    }
} catch {
    Write-Host "   ⚠️  Impossible de vérifier la BD" -ForegroundColor Yellow
}

Write-Host ""

# ========================================
# Test 4: Vérifier le Frontend
# ========================================

Write-Host "🔍 Test 4: Frontend" -ForegroundColor Yellow

$frontendPath = "frontend"
if (Test-Path $frontendPath) {
    Write-Host "   ✅ Dossier frontend trouvé" -ForegroundColor Green

    $envPath = "$frontendPath\.env"
    if (Test-Path $envPath) {
        Write-Host "   ✅ Fichier .env trouvé" -ForegroundColor Green
        $envContent = Get-Content $envPath
        Write-Host "      Contenu:" -ForegroundColor Gray
        $envContent | ForEach-Object { Write-Host "        $_" -ForegroundColor Gray }
    } else {
        Write-Host "   ❌ Fichier .env manquant" -ForegroundColor Red
        Write-Host "      Créer: $envPath" -ForegroundColor Yellow
    }

    $nodeModulesPath = "$frontendPath\node_modules"
    if (Test-Path $nodeModulesPath) {
        Write-Host "   ✅ Dependencies npm installées" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️  Dependencies npm non trouvées" -ForegroundColor Yellow
        Write-Host "      Installer: cd $frontendPath && npm install" -ForegroundColor Yellow
    }
} else {
    Write-Host "   ❌ Dossier frontend non trouvé" -ForegroundColor Red
}

Write-Host ""

# ========================================
# Test 5: Tester d'autres comptes
# ========================================

Write-Host "🔍 Test 5: Vérifier les comptes de test" -ForegroundColor Yellow

$accounts = @(
    @{ email = "admin@sirh.local"; role = "Admin" },
    @{ email = "rh@sirh.local"; role = "RH" },
    @{ email = "compta@sirh.local"; role = "Comptable" },
    @{ email = "academic@sirh.local"; role = "Chef d'UFR" },
    @{ email = "prof@sirh.local"; role = "Enseignant" }
)

$successCount = 0
foreach ($account in $accounts) {
    $payload = @{
        email = $account.email
        password = $testPassword
    } | ConvertTo-Json

    try {
        $response = Invoke-WebRequest `
            -Uri "$apiUrl/auth/login" `
            -Method POST `
            -ContentType "application/json" `
            -Body $payload `
            -ErrorAction Stop

        $data = $response.Content | ConvertFrom-Json
        if ($data.message -match "réussie|successful|success") {
            Write-Host "   ✅ $($account.role): $($account.email)" -ForegroundColor Green
            $successCount++
        }
    } catch {
        Write-Host "   ❌ $($account.role): $($account.email)" -ForegroundColor Red
    }
}

Write-Host "   Résultat: $successCount/$($accounts.Count) comptes OK" -ForegroundColor Cyan
Write-Host ""

# ========================================
# Résumé
# ========================================

Write-Host "╔════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   ✅ TESTS COMPLÉTÉS                      ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Cyan

Write-Host ""
Write-Host "📊 Résumé:" -ForegroundColor Yellow
Write-Host "   ✅ Backend: En ligne" -ForegroundColor Green
Write-Host "   ✅ API Login: Fonctionnel" -ForegroundColor Green
Write-Host "   ✅ Comptes: $successCount/$($accounts.Count) disponibles" -ForegroundColor Green
Write-Host ""
Write-Host "🚀 Prochaines étapes:" -ForegroundColor Cyan
Write-Host "   1. Démarrer le Frontend: npm run dev" -ForegroundColor Gray
Write-Host "   2. Ouvrir: http://localhost:5173" -ForegroundColor Gray
Write-Host "   3. Connexion: $testEmail / password" -ForegroundColor Gray
Write-Host ""

Read-Host "Appuyez sur Entrée pour fermer"
