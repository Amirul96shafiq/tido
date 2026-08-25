#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.." || exit 1

npm run dev:all
exec bash -l
