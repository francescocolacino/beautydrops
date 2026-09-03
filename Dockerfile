# BeautyDrops — immagine per il deploy su Koyeb (o qualsiasi host Docker)
# Porta esposta: 80 (Apache). Configura la stessa porta nel servizio Koyeb.

FROM php:8.3-apache

# Estensione PDO PostgreSQL. libpq-dev resta installata (non solo la fase di
# build): rimuoverla dopo la compilazione trascinerebbe via anche libpq5,
# la libreria condivisa da cui pdo_pgsql dipende a runtime.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# mod_rewrite abilitato; AllowOverride All per rispettare i file .htaccess
# già presenti nel progetto (es. assets/images/products/.htaccess).
RUN a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . /var/www/html

# Solo le directory che devono restare scrivibili a runtime (upload prodotto
# e PDF ordini) vengono rese scrivibili da www-data. Vedi README per il
# limite di persistenza di questi file sul filesystem del container.
RUN chown -R www-data:www-data \
        /var/www/html/assets/images/products \
        /var/www/html/assets/orders \
    && chmod -R 775 \
        /var/www/html/assets/images/products \
        /var/www/html/assets/orders

EXPOSE 80

CMD ["apache2-foreground"]
