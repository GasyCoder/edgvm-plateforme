#!/usr/bin/env bash
# Envoie l'APK construit (scripts/construire-apk.sh) sur la plateforme en ligne :
# https://plateforme.edgvm.mahajanga-univ.mg/application/edgvm.apk, annoncé par la page « Application mobile » du site.
set -euo pipefail

racine="$(cd "$(dirname "$0")/.." && pwd)"
hote="${HOTE_DEPLOIEMENT:-umg}"
dossier="${DOSSIER_DEPLOIEMENT:-edgvm-plateforme-app}"
source="$racine/backend/public/application"

[ -f "$source/edgvm.apk" ] && [ -f "$source/version.json" ] || { echo "Aucun APK : lancez d'abord scripts/construire-apk.sh." >&2; exit 1; }

rsync -az --chmod=D755,F644 -e "ssh -o RemoteCommand=none -o RequestTTY=no" \
  "$source/edgvm.apk" "$source/version.json" "$hote:$dossier/public/application/"
echo "APK publié : $(cat "$source/version.json")"
