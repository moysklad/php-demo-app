# Используем официальный образ PHP с Apache
FROM php:8.2-apache

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libsqlite3-dev \
    zip \
    unzip \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Устанавливаем расширения PHP
RUN docker-php-ext-install zip opcache curl pdo_sqlite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Включаем модуль Apache rewrite
#RUN a2enmod rewrite

# Устанавливаем Composer-зависимости отдельным слоем, чтобы кэшировать их между правками PHP-кода
COPY composer.json composer.lock /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts

# Копируем файлы приложения и обновляем autoload
COPY . /var/www/html/
RUN composer dump-autoload --no-dev --optimize

# Настраиваем права
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Настраиваем document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/src/php
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Порт, который будет слушать Apache
EXPOSE 80

# Запускаем Apache в foreground режиме
CMD ["apache2-foreground"]
