#!/usr/bin/env bash
# Deploys a branch (main by default) on the Satisworld server.
# Stops at the first failure: nothing goes live if the quality checks fail.
set -euo pipefail
cd "$(dirname "$0")"

BRANCH="${1:-main}"

echo "→ Récupération du code ($BRANCH)"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "→ Construction des images"
docker compose build
docker compose --profile test build test

echo "→ Démarrage de MySQL"
docker compose up -d --wait mysql

echo "→ Contrôles qualité : Pint, Larastan, tests"
docker compose --profile test run --rm test composer check

echo "→ Migrations"
docker compose run --rm app php artisan migrate --force

echo "→ Mise en service"
docker compose up -d --remove-orphans app worker scheduler web
docker compose exec -T app php artisan optimize

echo "✓ Déploiement terminé"
