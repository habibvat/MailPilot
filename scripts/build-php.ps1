# Compile le binaire PHP embarqué dans l'application desktop (Windows).
# Même rôle que scripts/build-php.sh : ajoute xmlreader / xmlwriter au PHP de NativePHP.
#
# Usage : scripts\build-php.ps1 [-PhpVersion 8.3]  (défaut : version du PHP local)
# Résultat : nativephp\php-bin\bin\win\x64\php-<version>.zip
param([string]$PhpVersion = "")
$ErrorActionPreference = "Stop"

$SpcVersion = "2.8.5"
# Sans intl : avec static-php-cli 2.8.5, ICU 79 ne compile pas avec PHP 8.3 sous MSVC (C++17
# requis). MailPilot n'en dépend pas (polyfills Symfony / portable-ascii) ; la CI le vérifie
# en lançant les tests avec ce binaire.
$Extensions = "bcmath,bz2,ctype,curl,dom,fileinfo,filter,gd,iconv,mbregex,mbstring,opcache,openssl,pdo,pdo_sqlite,phar,session,simplexml,sockets,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,zip,zlib"

$Root = Resolve-Path "$PSScriptRoot\.."
if (-not $PhpVersion) { $PhpVersion = (php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;') }

# static-php-cli ne gère pas les espaces dans les chemins : on compile hors du projet.
$Work = if ($env:SPC_WORKDIR) { $env:SPC_WORKDIR } else { "C:\mailpilot-spc" }
if ($Work -match " ") { throw "SPC_WORKDIR ne doit pas contenir d'espace : $Work" }
$Out = Join-Path $Root "nativephp\php-bin\bin\win\x64"
New-Item -ItemType Directory -Force -Path $Work, $Out | Out-Null
Set-Location $Work

if (-not (Test-Path .\spc.exe)) {
    Invoke-WebRequest "https://github.com/crazywhalecc/static-php-cli/releases/download/$SpcVersion/spc-windows-x64.exe" -OutFile spc.exe
}

# « spc doctor » installe les outils de compilation manquants : seulement en CI ou si SPC_AUTO_FIX=1.
if ($env:CI -eq "true" -or $env:SPC_AUTO_FIX -eq "1") { .\spc.exe doctor --auto-fix; if ($LASTEXITCODE) { exit $LASTEXITCODE } }
.\spc.exe download --with-php=$PhpVersion --for-extensions=$Extensions --prefer-pre-built
if ($LASTEXITCODE) { exit $LASTEXITCODE }
.\spc.exe build $Extensions --build-cli
if ($LASTEXITCODE) { exit $LASTEXITCODE }

$Bin = Join-Path $Work "buildroot\bin\php.exe"
$Modules = & $Bin -m
foreach ($ext in "xmlreader", "xmlwriter", "zip", "gd", "pdo_sqlite") {
    if (-not ($Modules -contains $ext)) { throw "Extension manquante dans le binaire : $ext" }
}
# mbregex n'apparaît pas dans « php -m » : on vérifie mb_split, utilisé par Str::studly.
& $Bin -r 'exit(function_exists("mb_split") ? 0 : 1);'
if ($LASTEXITCODE) { throw "mbregex absent (mb_split)" }

$Zip = Join-Path $Out "php-$PhpVersion.zip"
if (Test-Path $Zip) { Remove-Item $Zip }
Compress-Archive -Path $Bin -DestinationPath $Zip
Write-Host "Binaire prêt : $Zip"
