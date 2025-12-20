#!/usr/bin/env bash

if [ ! -z "$WWWUSER" ]; then
    usermod -u $WWWUSER sail
fi

if [ ! -d /.composer ]; then
    mkdir /.composer
fi

chmod -R ugo+rw /.composer

if [ $# -gt 0 ]; then
    exec gosu $WWWUSER "$@"
else
    # Create log directory if it doesn't exist
    mkdir -p /var/www/html/storage/logs
    mkdir -p /var/log/supervisor

    # Wait for MySQL to be ready
    echo "Waiting for MySQL..."
    while ! mysqladmin ping -h"${DB_HOST:-mysql}" -u"${DB_USERNAME:-sail}" -p"${DB_PASSWORD:-password}" --silent 2>/dev/null; do
        sleep 1
    done
    echo "MySQL is ready!"

    # Run migrations
    echo "Running migrations..."
    gosu sail php /var/www/html/artisan migrate --force

    # Start supervisor
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
