#!/usr/bin/env bash
set -Eeuo pipefail

umask 077

ROOT=/opt/stack/apps/rys-events
COMPOSE="$ROOT/docker-compose.prod.yml"
BACKUP_ROOT=/opt/stack/backups/rys-events
IMAGE_REPOSITORY=ghcr.io/alexlondon07/rys-events

if [[ $# -ne 1 || ! "$1" =~ ^[0-9a-f]{40}$ ]]; then
    echo 'Usage: deploy-production.sh <40-character commit SHA>' >&2
    exit 2
fi

mkdir -p "$BACKUP_ROOT"
exec 9>"$BACKUP_ROOT/deploy.lock"
flock -n 9 || { echo 'A RYS deployment is already running.' >&2; exit 1; }

cd "$ROOT"
test -s .env
test -f "$COMPOSE"

sha="$1"
git fetch --quiet origin main
if [[ "$(git rev-parse origin/main)" != "$sha" ]]; then
    echo 'Refusing to deploy a commit that is not the current origin/main.' >&2
    exit 1
fi

previous_image=''
if [[ -s .deployed-image ]]; then
    previous_image="$(cat .deployed-image)"
fi

export RYS_IMAGE="$IMAGE_REPOSITORY:$sha"
docker pull "$RYS_IMAGE"
git checkout --quiet --detach "$sha"
docker compose -f "$COMPOSE" config --quiet

# Keep a database snapshot before every migration. This does not stop MySQL.
stamp="$(date +%Y%m%d-%H%M%S)"
backup="$BACKUP_ROOT/$stamp-alex_rys_db.sql.gz"
# shellcheck disable=SC1091
source /opt/stack/.env
test -n "${MYSQL_ROOT_PASSWORD:-}"
docker exec -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql \
    mysqldump -uroot --single-transaction --routines --triggers --events \
    --hex-blob --column-statistics=0 alex_rys_db | gzip -9 > "$backup"
gzip -t "$backup"
unset MYSQL_ROOT_PASSWORD

docker compose -f "$COMPOSE" run --rm --no-deps app php artisan migrate --force

if ! docker compose -f "$COMPOSE" up -d --wait; then
    if [[ -n "$previous_image" ]]; then
        echo 'New containers failed health checks; restoring the previous image.' >&2
        RYS_IMAGE="$previous_image" docker compose -f "$COMPOSE" up -d --wait || true
    fi
    echo "Database backup: $backup" >&2
    exit 1
fi

docker compose -f "$COMPOSE" exec -T app php artisan migrate:status >/dev/null
docker compose -f "$COMPOSE" exec -T app curl -fsS http://127.0.0.1/login >/dev/null
printf '%s\n' "$RYS_IMAGE" > .deployed-image
echo "RYS deployed: $sha"
echo "Database backup: $backup"
