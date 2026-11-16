#!/usr/bin/env bash
# Enregistre les identifiants WhatsApp Business (API Cloud de Meta) dans le .env du serveur,
# sans les afficher ni les écrire sur ce poste. À lancer depuis le poste qui a l'accès SSH :
#
#   scripts/configurer-whatsapp.sh
#
# Le jeton est saisi en masqué, transmis par SSH (chiffré) sur l'entrée standard, jamais en argument,
# puis écrit dans ~/edgvm-plateforme-app/.env (droits 600). L'ancien .env est sauvegardé dans ~/docroot-backups.
set -euo pipefail

hote="${HOTE_DEPLOIEMENT:-umg}"
dossier="${DOSSIER_DEPLOIEMENT:-edgvm-plateforme-app}"
ssh_cmd=(ssh -o RemoteCommand=none -o RequestTTY=no "$hote")

read -r -p "Identifiant du numéro de téléphone (Phone number ID, chiffres) : " numero_id
[[ "$numero_id" =~ ^[0-9]{6,30}$ ]] || { echo "Identifiant invalide : uniquement des chiffres." >&2; exit 1; }
read -r -s -p "Jeton d'accès permanent (System User token) : " jeton; echo
[[ ${#jeton} -ge 50 && "$jeton" =~ ^[A-Za-z0-9]+$ ]] || { echo "Jeton invalide : utilisez le jeton permanent d'un utilisateur système." >&2; exit 1; }
read -r -p "Nom du modèle de message [code_verification] : " modele
modele="${modele:-code_verification}"
[[ "$modele" =~ ^[a-z0-9_]+$ ]] || { echo "Nom de modèle invalide." >&2; exit 1; }
read -r -p "Langue du modèle [fr] : " langue
langue="${langue:-fr}"
[[ "$langue" =~ ^[a-z]{2}(_[A-Z]{2})?$ ]] || { echo "Langue invalide (ex. fr ou fr_FR)." >&2; exit 1; }

# 1) Le script distant, sans aucun secret
"${ssh_cmd[@]}" 'umask 077; cat > "$HOME/.configurer-whatsapp.sh"' <<'DISTANT'
set -euo pipefail
umask 077
read -r numero_id; read -r jeton; read -r modele; read -r langue
cd "$HOME/$1"
mkdir -p "$HOME/docroot-backups"
cp -p .env "$HOME/docroot-backups/plateforme-env-$(date +%Y%m%d-%H%M%S)"
export WA_NUMERO_ID="$numero_id" WA_JETON="$jeton" WA_MODELE="$modele" WA_LANGUE="$langue"
php -r '
$f = ".env";
$env = file_get_contents($f);
$valeurs = ["WHATSAPP_PILOTE" => "meta", "WHATSAPP_NUMERO_ID" => getenv("WA_NUMERO_ID"), "WHATSAPP_JETON" => getenv("WA_JETON"), "WHATSAPP_MODELE_CODE" => getenv("WA_MODELE"), "WHATSAPP_LANGUE" => getenv("WA_LANGUE")];
foreach ($valeurs as $cle => $valeur) {
    $ligne = $cle."=".$valeur;
    $env = preg_match("/^".$cle."=.*$/m", $env) ? preg_replace("/^".$cle."=.*$/m", $ligne, $env) : rtrim($env)."\n".$ligne."\n";
}
file_put_contents($f, $env);
'
chmod 600 .env
if [ -f artisan ]; then php artisan config:cache --quiet; fi
echo "WhatsApp configuré : pilote meta, numéro $numero_id, modèle $modele ($langue)."
DISTANT

# 2) Les valeurs, sur l'entrée standard ; le script distant est effacé ensuite
printf '%s\n%s\n%s\n%s\n' "$numero_id" "$jeton" "$modele" "$langue" \
  | "${ssh_cmd[@]}" "bash \"\$HOME/.configurer-whatsapp.sh\" '$dossier'; rm -f \"\$HOME/.configurer-whatsapp.sh\""
unset jeton
