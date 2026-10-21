FROM phpdockerio/php:8.5-fpm
WORKDIR "/var/www/html"

RUN apt-get update \
    && apt-get -y --no-install-recommends install ca-certificates curl \
    && curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/keyrings/sury-php.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php noble main" > /etc/apt/sources.list.d/ondrej-php.list \
    && apt-get update \
    && apt-get -y --no-install-recommends --only-upgrade install 'php8.5-*' \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

CMD ["php-fpm8.5", "-F"]
