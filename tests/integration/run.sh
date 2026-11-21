#!/usr/bin/env bash
#
# PHPUnit tests of HydroLink inside a disposable FreeScout (Docker: PHP 8.2 + Apache, MariaDB 10.11).
#
#   bash tests/integration/run.sh                     # downloads FreeScout (branch dist) from GitHub
#   HL_REAL_BINARY=dist/hydroxide-linux-amd64 bash tests/integration/run.sh   # + tests with the real binary
#
# No Proton account is used: HydroLink/Tests/Support/fake-hydroxide.php stands in for hydroxide in the sign-in,
# watchdog and health tests; the binary built by the CI is started for real when HL_REAL_BINARY is given.
# Only test data is created (addresses in .test); the containers are removed at the end.
#
set -Eeuo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
if (cd "$ROOT" && pwd -W) >/dev/null 2>&1; then
    export MSYS_NO_PATHCONV=1
    ROOT="$(cd "$ROOT" && pwd -W)"
fi
IMAGE="hl-freescout-test"
NET="hltest"
DB="hl-test-db"
APP="hl-test-app"
PHPUNIT_URL="https://phar.phpunit.de/phpunit-11.phar"
FREESCOUT_REF="${FREESCOUT_REF:-dist}"

log() { printf '\n== %s\n' "$*" >&2; }
cleanup() { docker rm -f "$APP" "$DB" >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; }
trap '[ -n "${KEEP:-}" ] || cleanup' EXIT

debug() {
    trap - ERR
    log "Debug: FreeScout setup and Laravel log"
    docker exec "$APP" sh -c 'tail -n 30 /tmp/setup.log 2>/dev/null' || true
    log "Debug: errors of the Laravel log (message and first frames)"
    docker exec "$APP" sh -c 'grep -h -A6 "\.ERROR\|\.WARNING\|\.CRITICAL" /var/www/html/storage/logs/*.log 2>/dev/null | cut -c1-1500 | tail -n 120' || true
}
trap debug ERR

log "Image and containers"
docker image inspect "$IMAGE" >/dev/null 2>&1 || docker build -q -t "$IMAGE" "$ROOT/tests/integration" >/dev/null
cleanup
docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" \
    -e MARIADB_ROOT_PASSWORD=test-root -e MARIADB_DATABASE=freescout -e MARIADB_USER=freescout -e MARIADB_PASSWORD=test-only \
    mariadb:10.11 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci >/dev/null
docker run -d --name "$APP" --network "$NET" "$IMAGE" >/dev/null

log "FreeScout + HydroLink"
docker exec "$APP" sh -c "curl -fsSL https://codeload.github.com/freescout-helpdesk/freescout/tar.gz/refs/heads/$FREESCOUT_REF | tar xz --strip-components=1 -C /var/www/html"
docker cp "$ROOT/HydroLink" "$APP:/var/www/html/Modules/HydroLink"
if [ -n "${HL_REAL_BINARY:-}" ]; then
    docker cp "$ROOT/$HL_REAL_BINARY" "$APP:/opt/hydroxide"
    docker exec "$APP" chmod 755 /opt/hydroxide
fi
docker exec "$APP" sh -c 'rm -rf /var/www/html/.git && chown -R www-data:www-data /var/www/html'

for _ in $(seq 1 60); do
    docker exec "$DB" healthcheck.sh --connect >/dev/null 2>&1 && break
    sleep 1
done

docker exec -u www-data -w /var/www/html "$APP" bash -c '
set -e
exec >/tmp/setup.log 2>&1
cat > .env <<ENV
APP_URL=http://localhost
APP_ENV=production
APP_KEY=
DB_CONNECTION=mysql
DB_HOST=hl-test-db
DB_PORT=3306
DB_DATABASE=freescout
DB_USERNAME=freescout
DB_PASSWORD=test-only
ENV
php artisan key:generate --force
php artisan migrate --force
php artisan module:enable HydroLink
php artisan freescout:module-install hydrolink >/dev/null 2>&1 || true
php artisan migrate --force
php artisan config:clear
' || { docker exec "$APP" cat /tmp/setup.log >&2; exit 1; }

log "hydrolink:health, hydrolink:tick, hydrolink:diagnose, scheduler"
docker exec -u www-data -w /var/www/html "$APP" php artisan hydrolink:health --json
docker exec -u www-data -w /var/www/html "$APP" php artisan hydrolink:tick
docker exec -u www-data -w /var/www/html "$APP" php artisan hydrolink:diagnose
docker exec -u www-data -w /var/www/html "$APP" php artisan schedule:list 2>/dev/null | grep -q hydrolink:tick \
    || docker exec -u www-data -w /var/www/html "$APP" php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $s = $app->make(Illuminate\Console\Scheduling\Schedule::class); $s = \Eventy::filter("schedule", $s); $c = implode(" ", array_map(function ($e) { return $e->command; }, $s->events())); exit(strpos($c, "hydrolink:tick") !== false && strpos($c, "hydrolink:auth") !== false ? 0 : 1);'
log "hydrolink:uninstall --dry-run"
docker exec -u www-data -w /var/www/html "$APP" php artisan hydrolink:uninstall --dry-run

log "PHPUnit"
docker exec "$APP" sh -c "curl -sSL -o /tmp/phpunit.phar $PHPUNIT_URL"
set +e
docker exec -u www-data -w /var/www/html -e HL_REAL_BINARY="${HL_REAL_BINARY:+/opt/hydroxide}" "$APP" php /tmp/phpunit.phar -c Modules/HydroLink/Tests/phpunit.xml --do-not-cache-result --testdox "$@"
code=$?
set -e
[ "$code" -eq 0 ] || debug

log "Versions"
docker exec -w /var/www/html "$APP" sh -c 'php -r "echo \"PHP \".PHP_VERSION.PHP_EOL;"; php artisan --version'
exit $code
