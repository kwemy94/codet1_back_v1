#!/usr/bin/env bash
#
# Installation de l'API CODET I v1.
#
# Ce dossier ne contient QUE les fichiers métier (app/, database/, routes/,
# config/, docs/). Le squelette Laravel doit être créé d'abord : c'est lui qui
# fournit le fichier `artisan`. Ce script enchaîne les deux opérations.
#
# Usage :
#   bash installer.sh [chemin/du/projet]
#
# Sans argument, le projet est créé dans ../codet1-api-laravel
#
set -euo pipefail

SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CIBLE="${1:-$(dirname "$SOURCE")/codet1-api-laravel}"

echo "→ Vérification des prérequis"
command -v php >/dev/null      || { echo "PHP 8.3+ est requis."; exit 1; }
command -v composer >/dev/null || { echo "Composer est requis : https://getcomposer.org"; exit 1; }

php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
  || { echo "PHP 8.2 minimum requis (8.3 recommandé). Version détectée : $(php -r 'echo PHP_VERSION;')"; exit 1; }

if [ -e "$CIBLE" ]; then
  echo "Le dossier $CIBLE existe déjà. Choisissez un autre chemin ou supprimez-le."
  exit 1
fi

echo "→ Création du squelette Laravel 12 dans $CIBLE"
composer create-project laravel/laravel "$CIBLE" "12.*" --no-interaction

cd "$CIBLE"

echo "→ Activation de l'API et de Sanctum"
php artisan install:api --no-interaction

echo "→ Retrait de la migration users par défaut (remplacée par celle du projet)"
rm -f database/migrations/0001_01_01_000000_create_users_table.php

echo "→ Copie des fichiers métier"
for dossier in app database routes config docs lang; do
  mkdir -p "$dossier"
  cp -R "$SOURCE/$dossier/." "$dossier/"
done

echo "→ Complément du fichier .env"
# La table sessions n'est pas créée par ce projet : on bascule sur le pilote fichier.
if grep -q '^SESSION_DRIVER=' .env; then
  sed -i.sauvegarde 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env
fi

# Locale française : sans elle, les messages de validation restent en anglais.
sed -i.sauvegarde 's/^APP_LOCALE=.*/APP_LOCALE=fr/' .env
sed -i.sauvegarde 's/^APP_FALLBACK_LOCALE=.*/APP_FALLBACK_LOCALE=fr/' .env
grep -q '^APP_LOCALE=' .env || echo 'APP_LOCALE=fr' >> .env

if ! grep -q 'ADMIN_EMAIL' .env; then
  cat >> .env <<'VARIABLES'

# --- CODET I : compte administrateur initial
ADMIN_EMAIL=admin@codet1.org
ADMIN_TELEPHONE=+237600000000
ADMIN_MOT_DE_PASSE=ChangezMoi2026!

# --- CODET I : passerelles de paiement (à renseigner avec le contrat marchand)
OM_CLIENT_ID=
OM_CLIENT_SECRET=
OM_CLE_MARCHAND=
OM_SECRET_WEBHOOK=

MOMO_UTILISATEUR_API=
MOMO_CLE_API=
MOMO_CLE_ABONNEMENT=
MOMO_ENVIRONNEMENT=mtncameroon
MOMO_IPS_AUTORISEES=

# --- CODET I : frontend autorisé (CORS / Sanctum)
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
VARIABLES
fi

cat <<'SUITE'

────────────────────────────────────────────────────────────────────
Squelette prêt. Il reste deux étapes, à faire à la main :

1. Renseignez l'accès à la base dans .env :
     DB_CONNECTION=mysql
     DB_DATABASE=codet1
     DB_USERNAME=...
     DB_PASSWORD=...

   puis créez la base : CREATE DATABASE codet1 CHARACTER SET utf8mb4;

2. Créez le schéma et les données de référence :
     php artisan migrate --seed
     php artisan serve

L'API répondra sur http://localhost:8000/api/v1
Connexion initiale : ADMIN_EMAIL / ADMIN_MOT_DE_PASSE (à changer aussitôt).
────────────────────────────────────────────────────────────────────
SUITE
