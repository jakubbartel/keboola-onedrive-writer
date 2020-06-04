FROM php:7.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libicu-dev \
        libonig-dev && \
    docker-php-ext-install \
        sockets \
        mbstring \
        intl && \
    apt-get clean

COPY ./docker/php/php.ini /usr/local/etc/php/php.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer
ENV COMPOSER_ALLOW_SUPERUSER 1

COPY . /app
WORKDIR /app
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader

CMD ["php", "run.php"]
