FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app

RUN mkdir -p /app/assets/images/products /app/assets/orders \
    && chown -R www-data:www-data \
        /app/assets/images/products \
        /app/assets/orders \
    && chmod -R 775 \
        /app/assets/images/products \
        /app/assets/orders

ENV PORT=8080

EXPOSE 8080

USER www-data

CMD ["sh", "-c", "php scripts/init-db.php && php scripts/seed-catalog.php && php scripts/seed-galleries.php && php scripts/classify-product-types.php && if [ -n \"$ADMIN_EMAIL\" ] && [ -n \"$ADMIN_PASSWORD\" ]; then php scripts/create-admin.php; fi && exec php -S 0.0.0.0:${PORT:-8080} -t /app"]
