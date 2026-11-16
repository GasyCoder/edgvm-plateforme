#!/usr/bin/env bash
# Met en ligne la plateforme sur le serveur (o2switch) par SSH.
#
#   scripts/deployer.sh            # construit l'interface, envoie le code, migre, met en cache
#   scripts/deployer.sh --sans-construire
#
# Aucun secret ici : le .env de production n'existe que sur le serveur (~/edgvm-plateforme-app/.env, droits 600).
# Les fichiers déposés (photos, justificatifs, réglages) restent dans storage/ sur le serveur et ne sont jamais écrasés.
# L'APK (public/application/) s'envoie à part : scripts/publier-apk.sh.
set -euo pipefail

racine="$(cd "$(dirname "$0")/.." && pwd)"
hote="${HOTE_DEPLOIEMENT:-umg}"
dossier="${DOSSIER_DEPLOIEMENT:-edgvm-plateforme-app}"
ssh_cmd=(ssh -o RemoteCommand=none -o RequestTTY=no "$hote")

if [ "${1:-}" != "--sans-construire" ]; then
  "$racine/scripts/construire.sh"
fi

echo "Envoi du code vers $hote:~/$dossier ..."
rsync -az --delete --chmod=D755,F644 \
  -e "ssh -o RemoteCommand=none -o RequestTTY=no" \
  --exclude='.env' --exclude='.env.*' \
  --exclude='/storage/' --exclude='/vendor/' --exclude='/node_modules/' --exclude='/tests/' \
  --exclude='/bootstrap/cache/*.php' --exclude='/database/*.sqlite*' \
  --exclude='/public/hot' --exclude='/public/storage' --exclude='/public/.well-known/' --exclude='/public/application/' \
  --exclude='.phpunit*' --exclude='phpunit.xml' --exclude='.git*' --exclude='.idea' --exclude='.vscode' \
  --exclude='AGENTS.md' --exclude='CLAUDE.md' --exclude='.claude' --exclude='.agents' --exclude='boost.json' --exclude='.mcp.json' \
  "$racine/backend/" "$hote:$dossier/"

"${ssh_cmd[@]}" bash -s -- "$dossier" <<'DISTANT'
set -euo pipefail
chmod 711 "$HOME/$1"
cd "$HOME/$1"
chmod 755 artisan
mkdir -p storage/app/private storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chmod -R u+rwX,go-rwx storage bootstrap/cache
[ -f .env ] || { echo "Pas de .env sur le serveur : à créer avant le premier déploiement." >&2; exit 1; }
chmod 600 .env
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
php artisan migrate --force --no-interaction
php artisan optimize:clear --quiet
php artisan optimize --quiet
php artisan queue:restart --quiet
echo "Plateforme à jour : $(php artisan --version)"
DISTANT
