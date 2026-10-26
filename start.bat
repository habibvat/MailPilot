@echo off
rem Démarre MailPilot : serveur web, file d'attente et planificateur (trois fenêtres).
chcp 65001 >nul
cd /d "%~dp0"

rem Ajoute php\mailpilot.ini aux réglages PHP (taille d'upload, mémoire).
set "PHP_INI_SCAN_DIR=%PHP_INI_SCAN_DIR%;%~dp0php"

if not exist .env (
    copy .env.example .env >nul
    php artisan key:generate --force
    php artisan mailpilot:configure-env
)

set FIRST_RUN=0
if not exist database\database.sqlite (
    type nul > database\database.sqlite
    set FIRST_RUN=1
)

php artisan migrate --force
if "%FIRST_RUN%"=="1" php artisan db:seed --force

if not exist public\build\manifest.json (
    call npm install
    call npm run build
)

rem Premier port libre à partir de 8000 (ou de MAILPILOT_PORT).
if not defined MAILPILOT_PORT set MAILPILOT_PORT=8000
for /f "usebackq" %%p in (`php -r "$p = (int) $argv[1]; while (@fsockopen('127.0.0.1', $p, $e, $s, 0.2)) { $p++; } echo $p;" %MAILPILOT_PORT%`) do set PORT=%%p

start "MailPilot - serveur web" php artisan serve --host=127.0.0.1 --port=%PORT%
rem Le worker s'arrete de lui-meme apres un gros import (seuil memoire) : la boucle le relance.
start "MailPilot - file d'attente" cmd /c "for /l %%i in (0,0,1) do php artisan queue:work --queue=sends,default --tries=1 --timeout=600 --sleep=3 --memory=448"
start "MailPilot - planificateur" php artisan schedule:work

timeout /t 3 /nobreak >nul
start "" http://127.0.0.1:%PORT%
echo MailPilot est disponible sur http://127.0.0.1:%PORT%
echo Fermez les trois fenetres MailPilot pour arreter l'application.
