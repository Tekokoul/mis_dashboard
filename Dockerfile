# Africa CDC DHIS Performance Monitor
#
# One application image: nginx and PHP-FPM in the same container, with FPM as
# an internal implementation detail on 127.0.0.1 (nginx has no in-process PHP,
# so FastCGI is how nginx runs PHP - but nothing outside this container ever
# sees it). Tuning follows the 16 GB (tier M) enterprise profile.
#
# PHP 8.2 is deliberate: public/requirements.php requires >= 8.2.0, and
# app/configuration/settings.php still calls error_reporting(E_ALL | E_STRICT),
# which emits a deprecation on every request from 8.4 onwards.

# =============================================================================
# Stage 1 - the application tree, shared by both runtime images
# =============================================================================
FROM debian:bookworm-slim AS code
WORKDIR /src
COPY . /src
# settings.local.php is generated at container start from the environment. It is
# excluded by .dockerignore too; this is the belt to that braces, because a copy
# of it in any layer is a credential leak that `docker history` can read.
RUN rm -f /src/app/configuration/settings.local.php \
 && rm -rf /src/tools/dev /src/.git \
 && mkdir -p /src/public/cache /src/public/media /src/logs

# =============================================================================
# Stage 2 - PHP-FPM (executes all PHP)
# =============================================================================
FROM php:8.2-fpm AS app

# Debian's default mirrors are plain http. Some corporate networks refuse
# outbound :80 while allowing :443, which fails the build with "Unable to
# connect to deb.debian.org:80". Switch apt to https before the first update.
# Handles both the classic sources.list and the deb822 .sources format.
RUN set -eux; \
    sed -i 's|http://deb.debian.org|https://deb.debian.org|g; s|http://security.debian.org|https://security.debian.org|g' \
        /etc/apt/sources.list 2>/dev/null || true; \
    if [ -d /etc/apt/sources.list.d ]; then \
        find /etc/apt/sources.list.d -type f \( -name '*.list' -o -name '*.sources' \) \
            -exec sed -i 's|http://deb.debian.org|https://deb.debian.org|g; s|http://security.debian.org|https://security.debian.org|g' {} +; \
    fi; \
    printf 'Acquire::Retries "10";\nAcquire::http::Timeout "30";\nAcquire::https::Timeout "30";\n' \
        > /etc/apt/apt.conf.d/99retries

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libxml2-dev libzip-dev libonig-dev \
        mariadb-client; \
    rm -rf /var/lib/apt/lists/*

# pdo_mysql : app/db.class.php connects via PDO
# gd        : public/ngine_resize.php and the image helpers
# mbstring  : mb_* throughout the app
# soap, xml : the integration classes
# zip, exif, opcache : checked by public/requirements.php
RUN set -eux; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mysqli gd mbstring soap xml zip exif opcache

# nginx from Debian's repo, in the same layer as the client tools.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends nginx curl; \
    rm -rf /var/lib/apt/lists/*; \
    rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx.conf.tpl      /etc/nginx/templates/nginx.conf.tpl
COPY docker/nginx-site.conf.tpl /etc/nginx/templates/nginx-site.conf.tpl
COPY docker/opcache.ini        /usr/local/etc/php/conf.d/10-opcache.ini
COPY docker/php.ini.tpl        /usr/local/etc/php/conf.d/zz-africacdc.ini.tpl
COPY docker/php-fpm-pool.conf  /usr/local/etc/php-fpm.d/zz-www.conf.tpl
# The base image ships a www.conf that would fight ours.
RUN rm -f /usr/local/etc/php-fpm.d/www.conf.default /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
COPY --from=code --chown=www-data:www-data /src /var/www/html
RUN chown -R www-data:www-data public/cache public/media logs \
 && chmod -R 755 public/cache public/media logs

ARG BUILD_ID=dev
ENV APP_BUILD_ID=${BUILD_ID}

COPY docker/entrypoint-app.sh /usr/local/bin/entrypoint-app.sh
RUN chmod +x /usr/local/bin/entrypoint-app.sh

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/health.php || exit 1

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint-app.sh"]
CMD ["nginx", "-g", "daemon off;"]

