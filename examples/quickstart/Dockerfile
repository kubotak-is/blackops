FROM php:8.5-cli-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git ca-certificates \
    && docker-php-ext-install pcntl pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid "${GID}" app \
    && useradd --uid "${UID}" --gid "${GID}" --create-home app

WORKDIR /app
USER app

CMD ["php", "-a"]
