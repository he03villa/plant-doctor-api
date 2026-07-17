#!/bin/sh
set -e

echo "Generating Laravel caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Starting Octane..."
exec "$@"
