FROM docker.io/php:8.0.12-fpm
ARG UID
ARG GID

ENV UID=${UID}
ENV GID=${GID}
WORKDIR /var/www/html

RUN apt-get update\
      && apt-get upgrade -y\
      && apt-get -y --force-yes install libbz2-dev libjpeg62-turbo-dev libpng-dev libjpeg-dev libmcrypt-dev libzip-dev libtidy-dev\
      && docker-php-ext-install bcmath bz2 pdo_mysql gettext mysqli pdo tidy zip
RUN docker-php-ext-configure pdo_mysql
RUN docker-php-ext-install sockets pdo_mysql 
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY . .
EXPOSE 9000
CMD [ "php index.php" ]