#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

HOST="127.0.0.1"
PORT="${1:-8080}"

if [[ -f ".env.local" ]]; then
  set -a
  # shellcheck disable=SC1091
  source ".env.local"
  set +a
fi

PHP_ARGS=()
if [[ -f "php.ini" ]]; then
  PHP_ARGS+=("-c" "php.ini")
fi

exec php "${PHP_ARGS[@]}" -S "${HOST}:${PORT}" -t public
