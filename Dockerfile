FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql gd mbstring bcmath

WORKDIR /var/www/html

COPY . .

EXPOSE 8000

CMD ["sh", "-c", "php database/migrations/migrate.php && php -S 0.0.0.0:8000 -t public"]
