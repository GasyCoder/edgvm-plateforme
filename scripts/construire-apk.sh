#!/usr/bin/env bash
# Construit l'APK Android signé de l'application EDGVM et le place dans backend/public/application/,
# d'où il se télécharge (lien donné sur le site de l'École).
#
# Prérequis (une fois) : JDK 21, SDK Android (JAVA_HOME et ANDROID_HOME définis),
# et mobile/android/keystore.properties qui pointe vers la clé de signature :
#   storeFile=../edgvm-release.jks
#   storePassword=...
#   keyAlias=edgvm
#   keyPassword=...
# La clé (edgvm-release.jks) et ce fichier restent hors du dépôt : sauvegardez-les à part.
# Sans cette clé, une nouvelle version ne pourra pas remplacer l'ancienne sur les téléphones.
#
#   scripts/construire-apk.sh                       # plateforme en production
#   ADRESSE_PLATEFORME=http://192.168.1.20:8001 scripts/construire-apk.sh   # essai sur le réseau local
set -euo pipefail

racine="$(cd "$(dirname "$0")/.." && pwd)"
adresse="${ADRESSE_PLATEFORME:-https://plateforme.edgvm.mahajanga-univ.mg}"

: "${JAVA_HOME:?Définissez JAVA_HOME (JDK 21).}"
: "${ANDROID_HOME:?Définissez ANDROID_HOME (SDK Android).}"
if [ ! -f "$racine/mobile/android/keystore.properties" ]; then
  echo "Clé de signature absente : créez mobile/android/keystore.properties (voir l'en-tête de ce script)." >&2
  exit 1
fi

cd "$racine/mobile"
[ -d node_modules ] || npm ci
echo "window.ADRESSE_PLATEFORME = '$adresse'" > www/adresse.js
ADRESSE_PLATEFORME="$adresse" npx cap sync android

cd android
./gradlew --no-daemon assembleRelease

version="$(grep -m1 'versionName' app/build.gradle | sed -E 's/.*"(.*)".*/\1/')"
sortie="$racine/backend/public/application"
mkdir -p "$sortie"
cp app/build/outputs/apk/release/app-release.apk "$sortie/edgvm.apk"
printf '{"version":"%s","taille":%s,"construit_le":"%s"}\n' "$version" "$(stat -c %s "$sortie/edgvm.apk")" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$sortie/version.json"
echo "APK $version : $sortie/edgvm.apk (adresse : $adresse)"
