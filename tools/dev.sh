#!/usr/bin/env bash
set -euo pipefail

SERVICE=${1:-tools}
shift || true

docker compose run --rm "$SERVICE" "$@"


