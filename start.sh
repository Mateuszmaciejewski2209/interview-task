#!/usr/bin/env bash
set -euo pipefail

cp -n .env.example .env
touch database/database.sqlite

# fresh clone: the Docker build context is inside vendor/, so install it first
if [ ! -d vendor/laravel/sail ]; then
  docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app composer:2 composer install --ignore-platform-reqs --no-scripts
fi

docker compose up --build --remove-orphans -d
docker compose exec -T app composer install
docker compose exec -T app php artisan migrate:fresh --seed
