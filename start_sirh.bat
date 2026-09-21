@echo off
REM ========================================
REM SIRH - Script de Démarrage Complet
REM ========================================

setlocal enabledelayedexpansion

echo.
echo ╔════════════════════════════════════════════╗
echo ║   SIRH - Système de Pointage              ║
echo ║   Démarrage complet...                    ║
echo ╚════════════════════════════════════════════╝
echo.

cd /d "%~dp0"

REM ========================================
REM 1. Vérifier les dépendances
REM ========================================

echo ⏳ Vérification des dépendances...

where php >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ PHP n'est pas installé ou pas dans le PATH
    echo    Installez PHP ou ajoutez-le au PATH
    pause
    exit /b 1
)

where node >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Node.js n'est pas installé ou pas dans le PATH
    echo    Installez Node.js depuis nodejs.org
    pause
    exit /b 1
)

where npm >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ npm n'est pas installé ou pas dans le PATH
    echo    npm devrait être installé avec Node.js
    pause
    exit /b 1
)

echo ✅ PHP détecté
echo ✅ Node.js détecté
echo ✅ npm détecté
echo.

REM ========================================
REM 2. Démarrer le Backend
REM ========================================

echo 🚀 Démarrage du Backend...
echo    Port: 8000
echo.

cd backend

REM Vérifier que la base de données existe
if not exist "database\database.sqlite" (
    echo ⚠️  Base de données non trouvée, création...
    php artisan migrate --seed
    if %errorlevel% neq 0 (
        echo ❌ Erreur lors de la création de la BD
        pause
        exit /b 1
    )
    echo ✅ Base de données créée avec succès
    echo.
)

REM Démarrer le backend en arrière-plan
start "SIRH Backend - Port 8000" php artisan serve --port=8000

echo ✅ Backend lancé (vérifier la fenêtre 'SIRH Backend - Port 8000')
echo.

REM Attendre que le backend démarre
echo ⏳ Attente du démarrage du backend...
timeout /t 3 /nobreak

REM ========================================
REM 3. Vérifier la connexion Backend
REM ========================================

echo.
echo 🔍 Vérification de la connexion Backend...
for /f %%i in ('curl -s -o /dev/null -w "%%{http_code}" http://localhost:8000/api/health') do (
    if "%%i"=="200" (
        echo ✅ Backend est en ligne (HTTP 200)
    ) else (
        echo ⚠️  Backend répond avec le code: %%i
        echo    Continuant quand même...
    )
)

echo.

REM ========================================
REM 4. Démarrer le Frontend
REM ========================================

cd ..\frontend

echo 🚀 Démarrage du Frontend...

REM Vérifier les dépendances npm
if not exist "node_modules" (
    echo ⚠️  Dependencies not found, installing with npm...
    call npm install
    if %errorlevel% neq 0 (
        echo ❌ Erreur lors de l'installation des dépendances
        pause
        exit /b 1
    )
    echo ✅ Dépendances installées
    echo.
)

REM Démarrer le frontend
echo    Démarre sur le port 5173 (ou supérieur si occupé)
echo.
call npm run dev

REM ========================================
REM Fin
REM ========================================

echo.
echo ╔════════════════════════════════════════════╗
echo ║   ✅ SIRH est démarré!                    ║
echo ║                                            ║
echo ║   🌐 Backend:  http://localhost:8000      ║
echo ║   🌐 Frontend: http://localhost:5173      ║
echo ║                                            ║
echo ║   📝 Identifiants de test:                ║
echo ║      Email: admin@sirh.local              ║
echo ║      Password: password                   ║
echo ╚════════════════════════════════════════════╝
echo.
echo Appuyez sur Ctrl+C pour arrêter tous les services.
echo.

pause
