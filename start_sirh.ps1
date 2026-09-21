# ========================================
# SIRH - Script de Démarrage Complet (PowerShell)
# ========================================

Write-Host ""
Write-Host "╔════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   SIRH - Système de Pointage              ║" -ForegroundColor Cyan
Write-Host "║   Démarrage complet...                    ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

# ========================================
# 1. Vérifier les dépendances
# ========================================

Write-Host "⏳ Vérification des dépendances..." -ForegroundColor Yellow

$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    Write-Host "❌ PHP n'est pas installé ou pas dans le PATH" -ForegroundColor Red
    Write-Host "   Installez PHP ou ajoutez-le au PATH" -ForegroundColor Red
    Read-Host "Appuyez sur Entrée pour fermer"
    exit 1
}

$node = Get-Command node -ErrorAction SilentlyContinue
if (-not $node) {
    Write-Host "❌ Node.js n'est pas installé ou pas dans le PATH" -ForegroundColor Red
    Write-Host "   Installez Node.js depuis nodejs.org" -ForegroundColor Red
    Read-Host "Appuyez sur Entrée pour fermer"
    exit 1
}

$npm = Get-Command npm -ErrorAction SilentlyContinue
if (-not $npm) {
    Write-Host "❌ npm n'est pas installé ou pas dans le PATH" -ForegroundColor Red
    Write-Host "   npm devrait être installé avec Node.js" -ForegroundColor Red
    Read-Host "Appuyez sur Entrée pour fermer"
    exit 1
}

Write-Host "✅ PHP détecté" -ForegroundColor Green
Write-Host "✅ Node.js détecté" -ForegroundColor Green
Write-Host "✅ npm détecté" -ForegroundColor Green
Write-Host ""

# ========================================
# 2. Démarrer le Backend
# ========================================

Write-Host "🚀 Démarrage du Backend..." -ForegroundColor Cyan
Write-Host "   Port: 8000" -ForegroundColor Gray
Write-Host ""

Set-Location backend

# Vérifier que la base de données existe
if (-not (Test-Path "database\database.sqlite")) {
    Write-Host "⚠️  Base de données non trouvée, création..." -ForegroundColor Yellow
    & php artisan migrate --seed | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Erreur lors de la création de la BD" -ForegroundColor Red
        Read-Host "Appuyez sur Entrée pour fermer"
        exit 1
    }
    Write-Host "✅ Base de données créée avec succès" -ForegroundColor Green
    Write-Host ""
}

# Démarrer le backend en arrière-plan
$backendProcess = Start-Process -PassThru -FilePath "php" -ArgumentList "artisan serve --port=8000" -WindowStyle Normal -ErrorAction SilentlyContinue
Write-Host "✅ Backend lancé (PID: $($backendProcess.Id))" -ForegroundColor Green
Write-Host ""

# Attendre que le backend démarre
Write-Host "⏳ Attente du démarrage du backend..." -ForegroundColor Yellow
Start-Sleep -Seconds 3
Write-Host ""

# ========================================
# 3. Vérifier la connexion Backend
# ========================================

Write-Host "🔍 Vérification de la connexion Backend..." -ForegroundColor Cyan
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000/api/health" -ErrorAction SilentlyContinue
    if ($response.StatusCode -eq 200) {
        Write-Host "✅ Backend est en ligne (HTTP 200)" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Backend répond avec le code: $($response.StatusCode)" -ForegroundColor Yellow
        Write-Host "   Continuant quand même..." -ForegroundColor Yellow
    }
} catch {
    Write-Host "⚠️  Backend non accessible encore, continuant..." -ForegroundColor Yellow
}

Write-Host ""

# ========================================
# 4. Démarrer le Frontend
# ========================================

Set-Location ..\frontend

Write-Host "🚀 Démarrage du Frontend..." -ForegroundColor Cyan

# Vérifier les dépendances npm
if (-not (Test-Path "node_modules")) {
    Write-Host "⚠️  Dépendances non trouvées, installation avec npm..." -ForegroundColor Yellow
    & npm install | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Erreur lors de l'installation des dépendances" -ForegroundColor Red
        Read-Host "Appuyez sur Entrée pour fermer"
        exit 1
    }
    Write-Host "✅ Dépendances installées" -ForegroundColor Green
    Write-Host ""
}

# Démarrer le frontend
Write-Host "   Démarre sur le port 5173 (ou supérieur si occupé)" -ForegroundColor Gray
Write-Host ""
& npm run dev

# ========================================
# Fin
# ========================================

Write-Host ""
Write-Host "╔════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║   ✅ SIRH est démarré!                    ║" -ForegroundColor Green
Write-Host "║                                            ║" -ForegroundColor Green
Write-Host "║   🌐 Backend:  http://localhost:8000      ║" -ForegroundColor Green
Write-Host "║   🌐 Frontend: http://localhost:5173      ║" -ForegroundColor Green
Write-Host "║                                            ║" -ForegroundColor Green
Write-Host "║   📝 Identifiants de test:                ║" -ForegroundColor Green
Write-Host "║      Email: admin@sirh.local              ║" -ForegroundColor Green
Write-Host "║      Password: password                   ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
Write-Host "Appuyez sur Ctrl+C pour arrêter tous les services." -ForegroundColor Yellow
Write-Host ""

Read-Host "Appuyez sur Entrée"
