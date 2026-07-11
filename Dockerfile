FROM php:8.5-cli

ARG UID=1000
ARG GID=1000

ENV PATH=/app/vendor/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
ENV COMPOSER_HOME=/home/app/.composer
ENV COMPOSER_ROOT_VERSION=1.0.0@dev
ENV HOME=/home/app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpq-dev \
        unzip \
        git \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pcntl pdo pdo_pgsql zip

COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer

RUN groupadd -g ${GID} app \
    && useradd -u ${UID} -g ${GID} -m -d /home/app -s /bin/bash app

WORKDIR /app

RUN chown -R app:app /app

USER app

RUN git config --global --add safe.directory /app

CMD ["php", "-a"]
