FROM phpdockerio/php:8.5-fpm
WORKDIR "/application"

RUN apt-get update \
    && apt-get -y --no-install-recommends install ca-certificates curl \
    && grep -rlE 'ppa.launchpad(content)?.net/ondrej' /etc/apt/sources.list /etc/apt/sources.list.d | xargs -r rm -f \
    && curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/keyrings/sury-php.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php noble main" > /etc/apt/sources.list.d/ondrej-php.list \
    && apt-get update \
    && apt-get -y --no-install-recommends --only-upgrade install 'php8.5-*' \
    && apt-get -y --no-install-recommends install \
        php8.5-pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY Entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm8.5", "-F"]