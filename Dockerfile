FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Africa/Johannesburg

RUN apt-get update && apt-get install -y --no-install-recommends \
    apache2 \
    ca-certificates \
    curl \
    git \
    jq \
    libapache2-mod-php8.3 \
    mariadb-client \
    php8.3 \
    php8.3-bcmath \
    php8.3-cli \
    php8.3-common \
    php8.3-curl \
    php8.3-gd \
    php8.3-imap \
    php8.3-intl \
    php8.3-ldap \
    php8.3-mbstring \
    php8.3-mysql \
    php8.3-opcache \
    php8.3-soap \
    php8.3-xml \
    php8.3-zip \
    poppler-utils \
    rsync \
    sudo \
    tzdata \
    unzip \
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-suitecrm.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php-suitecrm.ini /etc/php/8.3/apache2/conf.d/99-suitecrm.ini
COPY docker/php-suitecrm.ini /etc/php/8.3/cli/conf.d/99-suitecrm.ini
COPY docker/entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

RUN mkdir -p /var/www/html /srv/myriad_exports/myriad_daily_logs /srv/radio_crm_backups /var/run/apache2 \
    && chown -R www-data:www-data /var/www/html /srv/myriad_exports

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["/docker-entrypoint.sh"]
