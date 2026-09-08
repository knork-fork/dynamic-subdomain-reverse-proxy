#!/bin/sh
set -e

# Ensure Symfony writable dirs exist with correct permissions
mkdir -p /application/var/cache
chown -R www-data:www-data /application/var

# Ensure log dir exists with correct permissions
mkdir -p /var/log
chown -R www-data:www-data /var/log

exec "$@"
