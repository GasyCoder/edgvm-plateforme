#!/usr/bin/env bash
# Construit l'interface Nuxt et la place dans backend/public, où Laravel la sert (même domaine que l'API).
set -euo pipefail

racine="$(cd "$(dirname "$0")/.." && pwd)"
public="$racine/backend/public"

cd "$racine/frontend"
npm run generate

sortie="$racine/frontend/.output/public"
rm -rf "$public/_nuxt" "$public/fonts" "$public/images" "$public/icones"
cp -r "$sortie/_nuxt" "$sortie/fonts" "$sortie/images" "$sortie/icones" "$public/"
cp "$sortie/favicon.ico" "$sortie/manifest.json" "$public/"
# robots.txt est servi par Laravel (réglage « indexation » dans Paramètres > Application)
rm -f "$public/robots.txt"
cp "$sortie/200.html" "$public/app.html"

echo "Interface construite dans backend/public (app.html + _nuxt)."
