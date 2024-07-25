FROM php:8.1-fpm
RUN apt-get update  \
    && apt-get install -y --no-install-recommends --no-install-suggests \
    libxslt1-dev \
    libxml2  \
    libcurl4-gnutls-dev \
    libmagickwand-dev \
    zip \
    libzip-dev \
    && pecl install imagick \
RUN pecl install imagick && \
    docker-php-ext-enable imagick
RUN docker-php-ext-install curl gd dom fileinfo iconv pdo pdo_mysql posix simplexml xml xsl zip
RUN docker-php-ext-enable curl gd dom fileinfo iconv imagick pdo pdo_mysql posix simplexml xml xsl zip


