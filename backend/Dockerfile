FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

CMD ["sh", "-c", "composer install && [ -f .env ] || cp .env.example .env; php artisan key:generate --force; touch database/database.sqlite; php artisan migrate --force --seed; php artisan serve --host=0.0.0.0 --port=8000"]