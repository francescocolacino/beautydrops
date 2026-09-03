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

# mod_php richiede mpm_prefork: l'immagine base carica mpm_event di default,
# e avere più MPM abilitati contemporaneamente manda in crash Apache all'avvio
# ("AH00534: More than one MPM loaded"). Rimuoviamo prima gli MPM alternativi,
# poi abilitiamo solo mpm_prefork e rewrite. AllowOverride All rispetta i file
# .htaccess già presenti nel progetto (es. assets/images/products/.htaccess).
# apache2ctl configtest fa fallire subito la build se la config è invalida.
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_worker.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && apache2ctl configtest

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
