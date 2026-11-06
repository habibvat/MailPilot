#!/usr/bin/env bash
# Compile le binaire PHP embarqué dans l'application desktop (macOS / Linux).
#
# Le PHP fourni par NativePHP n'a pas xmlreader / xmlwriter, indispensables à
# PhpSpreadsheet pour lire les fichiers .xlsx. On compile donc le nôtre avec
# static-php-cli, et NativePHP le récupère via NATIVEPHP_PHP_BINARY_PATH.
#
# Usage : scripts/build-php.sh [version PHP, ex. 8.3]  (défaut : version du PHP local)
# Résultat : nativephp/php-bin/bin/<os>/<arch>/php-<version>.zip (NATIVEPHP_PHP_BINARY_PATH=nativephp/php-bin/)
set -euo pipefail

SPC_VERSION="2.8.5"
# Extensions du binaire officiel NativePHP (dont mbregex : mb_split, utilisé par Laravel),
# plus xmlreader et xmlwriter.
EXTENSIONS="bcmath,bz2,ctype,curl,dom,fileinfo,filter,gd,iconv,intl,mbregex,mbstring,opcache,openssl,pdo,pdo_sqlite,phar,session,simplexml,sockets,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,zip,zlib"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_VERSION="${1:-$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')}"

case "$(uname -s)" in
    Darwin) OS=mac; SPC_OS=macos ;;
    Linux) OS=linux; SPC_OS=linux ;;
    *) echo "Système non pris en charge : $(uname -s) (Windows : scripts/build-php.ps1)" >&2; exit 1 ;;
esac
case "$(uname -m)" in
    arm64|aarch64) ARCH=arm64; SPC_ARCH=aarch64 ;;
    x86_64) ARCH=x64; SPC_ARCH=x86_64 ;;
    *) echo "Architecture non prise en charge : $(uname -m)" >&2; exit 1 ;;
esac

# static-php-cli ne gère pas les espaces dans les chemins (il lance des commandes
# non échappées, rm -rf compris) : on compile hors du projet.
WORK="${SPC_WORKDIR:-$HOME/.cache/mailpilot-spc}"
case "$WORK" in
    *" "*) echo "SPC_WORKDIR ne doit pas contenir d'espace : $WORK" >&2; exit 1 ;;
esac
OUT="$ROOT/nativephp/php-bin/bin/$OS/$ARCH"
mkdir -p "$WORK" "$OUT"
cd "$WORK"

if [ ! -x ./spc ]; then
    curl -fsSL "https://github.com/crazywhalecc/static-php-cli/releases/download/$SPC_VERSION/spc-$SPC_OS-$SPC_ARCH.tar.gz" | tar -xz
    chmod +x ./spc
fi

# « spc doctor » installe les outils de compilation manquants (Homebrew / apt).
# Il n'est lancé qu'en CI ou si SPC_AUTO_FIX=1, jamais à l'insu de l'utilisateur.
if [ "${CI:-}" = "true" ] || [ "${SPC_AUTO_FIX:-}" = "1" ]; then
    ./spc doctor --auto-fix
fi
./spc download --with-php="$PHP_VERSION" --for-extensions="$EXTENSIONS" --prefer-pre-built
./spc build "$EXTENSIONS" --build-cli

BIN="$WORK/buildroot/bin/php"
# Liste lue une fois : « php -m | grep -q » échoue avec pipefail (SIGPIPE quand grep s'arrête).
MODULES="$("$BIN" -m)"
for ext in xmlreader xmlwriter zip gd pdo_sqlite; do
    grep -qix "$ext" <<< "$MODULES" || { echo "Extension manquante dans le binaire : $ext" >&2; exit 1; }
done
# mbregex n'apparaît pas dans « php -m » : on vérifie mb_split, utilisé par Str::studly.
"$BIN" -r 'exit(function_exists("mb_split") ? 0 : 1);' || { echo "mbregex absent (mb_split)" >&2; exit 1; }

rm -f "$OUT/php-$PHP_VERSION.zip"
(cd "$WORK/buildroot/bin" && zip -q "$OUT/php-$PHP_VERSION.zip" php)
echo "Binaire prêt : $OUT/php-$PHP_VERSION.zip ($("$BIN" -r 'echo PHP_VERSION;'))"
