#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

HOST="127.0.0.1"
PORT="${1:-8080}"

exec php -S "${HOST}:${PORT}" -t public
