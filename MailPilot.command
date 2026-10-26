#!/usr/bin/env bash
# Double-cliquez sur ce fichier dans le Finder pour lancer MailPilot (macOS).
# Fermez la fenêtre du Terminal ou faites Ctrl+C pour arrêter.
cd "$(dirname "$0")"

# Le Finder ne charge pas le PATH du shell : on ajoute les emplacements Homebrew habituels.
export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"

if ! command -v php >/dev/null 2>&1; then
    echo "PHP est introuvable. Installez-le (brew install php) puis relancez."
    read -r -p "Appuyez sur Entrée pour fermer..."
    exit 1
fi

if [ ! -d vendor ]; then
    echo "Première installation : installation des dépendances PHP..."
    composer install --no-interaction || { read -r -p "Échec de composer install. Entrée pour fermer..."; exit 1; }
fi

./start.sh
