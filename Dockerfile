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

# Создаём config.php из примера если его нет в контексте сборки
RUN if [ ! -f /var/www/html/src/php/config.php ]; then \
        cp /var/www/html/src/php/config.example.php /var/www/html/src/php/config.php; \
    fi

# Настраиваем права
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Настраиваем document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/src/php
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Установка конфигурационных параметров решения: замените на свои значения
# и не забудьте указать актуальный APP_SECRET_KEY при запуске (но не храните его в системе контроля версий!)
ENV APP_ID=195d5446-9da8-47ee-abb9-e808e4f283d7
ENV APP_UID=php-demo-app.moysklad
ENV APP_BASE_URL=https://php-demo.testms-test.lognex.ru
ENV APP_SECRET_KEY=secret-key-example
ENV APP_DB_PATH=/var/www/html/src/php/data/app.sqlite
# Ключ шифрования access token в БД. Сгенерировать: bin2hex(sodium_crypto_secretbox_keygen())
ENV APP_ENCRYPT_KEY=

# Порт, который будет слушать Apache
EXPOSE 80

# Запускаем Apache в foreground режиме
CMD ["apache2-foreground"]
