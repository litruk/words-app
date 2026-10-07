# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# runtime - php-fpm plus the extensions the app needs, without any application
# code.
# ---------------------------------------------------------------------------
FROM php:8.5-fpm-alpine AS runtime

# Identity the containers run as (see securityContext in k8s/40-app.yaml).
ARG APP_UID=1000
ARG APP_GID=1000

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_pgsql pcntl opcache bcmath intl zip \
    && apk add --no-cache fcgi postgresql-client \
    && rm /usr/local/bin/install-php-extensions

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Give the uid/gid a passwd entry so tools that call getpwuid() behave.
RUN set -eux; \
    if getent group "${APP_GID}" >/dev/null; then \
        group="$(getent group "${APP_GID}" | cut -d: -f1)"; \
    else \
        addgroup -g "${APP_GID}" app; group=app; \
    fi; \
    if ! getent passwd "${APP_UID}" >/dev/null; then \
        adduser -u "${APP_UID}" -G "${group}" -s /bin/sh -D app; \
    fi

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/entrypoint

ENV APP_UID=${APP_UID} \
    APP_GID=${APP_GID} \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

EXPOSE 9000

# ---------------------------------------------------------------------------
# app - the runtime with the application and its production dependencies baked
# in. This is the image the Kubernetes manifests in k8s/ run: the code ships
# inside the image and a release is just a new tag.
# ---------------------------------------------------------------------------
FROM runtime AS app

ARG APP_UID=1000
ARG APP_GID=1000

# Dependencies first, so editing application code does not re-resolve them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

# storage/ and bootstrap/cache are .dockerignore'd (they are runtime state), so
# they must be recreated before package:discover runs as part of dump-autoload -
# it writes bootstrap/cache/packages.php.
RUN set -eux; \
    mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    composer dump-autoload --no-dev --optimize; \
    chown -R "${APP_UID}:${APP_GID}" /var/www/html; \
    rm -rf /tmp/composer

# Artisan caches are built per pod on start, since the configuration arrives
# from the environment (see OPTIMIZE_ON_BOOT in docker/php/entrypoint.sh).
ENV OPTIMIZE_ON_BOOT=1

USER ${APP_UID}:${APP_GID}

# ---------------------------------------------------------------------------
# web - nginx serving public/ and talking to php-fpm over localhost inside the
# same pod. Runs unprivileged on port 8080.
# ---------------------------------------------------------------------------
FROM nginxinc/nginx-unprivileged:1.29-alpine AS web

USER root
RUN rm /etc/nginx/conf.d/default.conf
COPY docker/nginx/in-cluster.conf /etc/nginx/conf.d/app.conf
COPY --from=app /var/www/html/public /var/www/html/public
USER 101

EXPOSE 8080
