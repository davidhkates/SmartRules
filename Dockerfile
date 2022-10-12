FROM php:8.1-apache
RUN apt-get update

RUN buildDeps=" \
        libicu-dev \
        zlib1g-dev \
        libsqlite3-dev \
        libpq-dev \
    " \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        $buildDeps \
        zlib1g \
        sqlite3 \
        git

RUN docker-php-ext-install pgsql

COPY docker-run.php /var/www/html
COPY / /var/www/html
CMD ["php", "docker-run.php"]
