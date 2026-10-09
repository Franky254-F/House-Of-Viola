# Online Shop Now: PHP 8.2 + Apache. Used by Render (and any Docker host).
FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev ca-certificates \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd exif \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-shop.ini
COPY docker/apache.conf /etc/apache2/conf-available/shop.conf
RUN a2enconf shop

COPY . /var/www/html/
RUN rm -f /var/www/html/install.lock /var/www/html/Dockerfile /var/www/html/render.yaml \
 && chown -R www-data:www-data /var/www/html/uploads

COPY docker/entrypoint.sh /usr/local/bin/shop-entrypoint
RUN chmod +x /usr/local/bin/shop-entrypoint

ENV PORT=10000
EXPOSE 10000
ENTRYPOINT ["shop-entrypoint"]
CMD ["apache2-foreground"]
