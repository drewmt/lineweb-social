#!/usr/bin/env sh

set -eu

wait_for_database() {
    attempts=0

    until php -r '
        try {
            new PDO(
                sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE")),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD"),
                [PDO::ATTR_TIMEOUT => 2]
            );
        } catch (Throwable) {
            exit(1);
        }
    ' 2>/dev/null; do
        attempts=$((attempts + 1))

        if [ "$attempts" -ge 60 ]; then
            echo "Database did not become ready in time." >&2
            exit 1
        fi

        sleep 2
    done
}

case "${1:-}" in
    serve)
        wait_for_database
        php artisan migrate --force --no-interaction
        php artisan optimize
        exec php artisan serve --host=0.0.0.0 --port=8000
        ;;
    worker)
        wait_for_database
        exec php artisan queue:work --queue=notifications,default --sleep=1 --tries=3 --timeout=90
        ;;
    scheduler)
        wait_for_database
        exec php artisan schedule:work
        ;;
    artisan)
        shift
        exec php artisan "$@"
        ;;
    *)
        exec "$@"
        ;;
esac
