#!/bin/sh
set -e

# Wait for the database to accept connections.
tries=0
until php -r '
    require "/var/www/html/config/env.php";
    load_env("/var/www/html/.env");
    try {
        new PDO("mysql:host=" . env("DB_HOST", "db") . ";port=" . env("DB_PORT", "3306"), env("DB_USER"), env("DB_PASS"));
    } catch (Throwable $e) { exit(1); }
' 2>/dev/null; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
        echo "Database not reachable after 60s" >&2
        exit 1
    fi
    sleep 1
done

# Create and seed the database the first time.
if [ -f /var/www/html/database/seed.php ]; then
    php /var/www/html/database/seed.php --if-empty
fi

# Make sure Apache can write uploads.
for dir in /var/www/html/storage/uploads /var/www/html/public/assets/uploads; do
    [ -d "$dir" ] && chown -R www-data:www-data "$dir" 2>/dev/null || true
done

exec "$@"
