FROM php:8.4-apache

# Aktualizacja i instalacja dodatkowych narzędzi
RUN apt-get update && apt-get upgrade -y && apt-get install -y \
      procps \
      nano \
      git \
      unzip \
      libicu-dev \
      zlib1g-dev \
      libxml2 \
      libxml2-dev \
      libreadline-dev \
      supervisor \
      cron \
      sudo \
      libzip-dev \
      wget \
      librabbitmq-dev \
      libpng-dev \
      libjpeg-dev \
      libfreetype6-dev \
    && pecl install amqp \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
      pdo_mysql \
      sockets \
      intl \
      opcache \
      zip \
      gd \
    && docker-php-ext-enable amqp \
    && rm -rf /tmp/* \
    && rm -rf /var/list/apt/* \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean

# Instalacja Composer'a
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Instalacja i konfiguracja rozszerzenia Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.discover_client_host=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Zainstaluj rozszerzenie redis
RUN pecl install redis && docker-php-ext-enable redis


COPY config/php/event-media.ini /usr/local/etc/php/conf.d/event-media.ini

# Skopiuj pliki aplikacji do katalogu Apache
COPY ./ /var/www/html

# Skopiuj własny plik konfiguracyjny Apache (opcjonalne)
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Włącz moduł Apache do obsługi URL rewrites
RUN a2enmod rewrite

# Rozpocznij serwer Apache
CMD ["apache2-foreground"]


