FROM php:8.2-cli-alpine3.17

RUN apk add --no-cache $PHPIZE_DEPS libpq-dev curl supercronic \
  && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
  && docker-php-ext-install pgsql \
  && docker-php-source delete \
  && rm -rf /tmp/*

RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

COPY . /app

WORKDIR /app

RUN composer install -n --ansi  \
    && composer dump-autoload