#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${SSH_ORIGINAL_COMMAND:-}" =~ ^deploy\ ([0-9a-f]{40})$ ]]; then
    exec /opt/stack/apps/rys-events/scripts/deploy-production.sh "${BASH_REMATCH[1]}"
fi

echo 'Only a RYS deployment by commit SHA is permitted.' >&2
exit 1
