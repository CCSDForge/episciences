FROM php:8.1-fpm

# Set timezone
ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update  \
    && apt-get install -y --no-install-recommends --no-install-suggests \
    libxslt1-dev \
    libxml2  \
    libcurl4-gnutls-dev \
    libmagickwand-dev \
    zip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    unzip \
    git \
    && pecl install imagick \
RUN pecl install imagick && \
    docker-php-ext-enable imagick
RUN docker-php-ext-install curl gd dom fileinfo iconv intl pdo pdo_mysql posix simplexml xml xsl zip
RUN docker-php-ext-enable  curl gd dom fileinfo iconv intl pdo pdo_mysql posix simplexml xml xsl zip

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY ./src/apache/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
# Run the default php-fpm command
CMD ["php-fpm"]

