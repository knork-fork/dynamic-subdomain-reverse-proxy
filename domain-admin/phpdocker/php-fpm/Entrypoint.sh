#!/bin/sh
set -e

# Ensure Symfony writable dirs exist with correct permissions
mkdir -p /application/var/cache
chown -R www-data:www-data /application/var

# Ensure log dir exists with correct permissions
mkdir -p /var/log
chown -R www-data:www-data /var/log

# /config is the project root's config/ dir, bind-mounted read-write so this
# app can edit domains.json. It's owned by the host user (not www-data), so
# make sure www-data can still write to it.
if [ -d /config ]; then
    chmod -R a+rwX /config
fi

exec "$@"
