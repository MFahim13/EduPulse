FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev mariadb-client \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '# Root .htaccess is only an XAMPP fallback; Docker serves public/ directly.\n<Directory /var/www/html>\n    AllowOverride None\n</Directory>\n<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\nServerName localhost\nServerTokens Prod\nServerSignature Off\n' \
       > /etc/apache2/conf-available/edupulse.conf \
    && a2enconf edupulse

# Port 8081 (inside the container only) mimics XAMPP: it serves the project ROOT at /html/
# with .htaccess files enabled, so tests can prove only public/ is reachable in that layout.
RUN printf 'Listen 8081\n<VirtualHost *:8081>\n    DocumentRoot /var/www\n    <Directory /var/www/html>\n        AllowOverride All\n        Require all granted\n    </Directory>\n</VirtualHost>\n' \
       > /etc/apache2/sites-available/xampp-layout.conf \
    && a2ensite xampp-layout

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/edupulse.ini
COPY docker/php/entrypoint.sh /usr/local/bin/edupulse-entrypoint

RUN chmod +x /usr/local/bin/edupulse-entrypoint

WORKDIR /var/www/html
ENTRYPOINT ["edupulse-entrypoint"]
CMD ["apache2-foreground"]
