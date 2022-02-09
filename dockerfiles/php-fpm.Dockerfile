FROM docker.io/php:8.0.12-fpm
ARG UID
ARG GID

ENV UID=${UID}
ENV GID=${GID}
WORKDIR /var/www/html
COPY ../server/.env.example /var/www/html/server/.env
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN sed -i 's,^post_max_size =.*$,post_max_size = 1024M,' "$PHP_INI_DIR/php.ini"
RUN sed -i 's,^upload_max_filesize =.*$,upload_max_filesize = 1024M,' "$PHP_INI_DIR/php.ini"
RUN sed -i 's,^memory_limit =.*$,memory_limit = 1024M,' "$PHP_INI_DIR/php.ini"

RUN apt-get update\
      && apt-get upgrade -y\
      && apt-get -y --force-yes install libbz2-dev libjpeg62-turbo-dev libpng-dev libjpeg-dev libmcrypt-dev libzip-dev libtidy-dev\
      && docker-php-ext-install bcmath bz2 pdo_mysql gettext mysqli pdo tidy zip
RUN docker-php-ext-configure gd \
    --with-jpeg
RUN docker-php-ext-install gd 
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
