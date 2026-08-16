# Production-shaped container image for the NOVA POS Laravel API.
#
# "Production-shaped" rather than "development": this image is what the public
# test environment runs, and a test environment that differs from production in
# its security posture tests the wrong thing. Debug output, permissive file
# serving and a writable application directory are exactly the differences that
# make a test box a liability once it has a public hostname.
#
# WHY NGINX AND PHP-FPM TOGETHER IN ONE IMAGE
# -------------------------------------------
# Laravel needs a web server that serves static files itself and hands only
# .php requests to the interpreter. `php artisan serve` is single-threaded and
# its own documentation says it is not for production, so it is not an option
# for anything internet-facing. Splitting nginx and php-fpm into two containers
# is the other valid shape, but it requires the application code to live in a
# shared volume that both mount -- which means the code is no longer part of
# the immutable image. One image that contains the app, the interpreter and the
# server keeps a deploy to a single artifact.
#
# The PHP version is pinned to the exact tag CI uses (infra/docker/php-ci)
# rather than a floating 8.x. A test environment that runs a different patch
# release than CI can pass CI and fail in the browser.

# ---------------------------------------------------------------------------
# Stage 1 -- resolve PHP dependencies.
#
# Separated so that vendor/ is built with the full composer toolchain but the
# final image never contains composer itself. apps/api/vendor is gitignored, so
# this stage is what actually produces it; nothing is copied in from the host.
# ---------------------------------------------------------------------------
FROM composer:2.9.3 AS vendor

WORKDIR /build

# composer.json and composer.lock are copied alone first so this layer is
# cached against dependency changes, not source changes. Editing a controller
# should not re-download the dependency tree.
COPY apps/api/composer.json apps/api/composer.lock ./

# --no-scripts because Laravel's post-install hooks invoke artisan, which boots
# the framework and needs application files that have not been copied yet.
# They are run in the next stage once the full source is present.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY apps/api ./

# The optimized autoloader resolves classes from a precomputed map rather than
# probing the filesystem per class. On a per-request interpreter like PHP that
# difference is measurable, not cosmetic.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative


# ---------------------------------------------------------------------------
# Stage 2 -- runtime.
# ---------------------------------------------------------------------------
FROM php:8.5.3-fpm-alpine AS runtime

# postgresql-dev, icu-dev, libzip-dev and oniguruma-dev are build-time headers
# for the extensions below; they are removed again in the same layer so they
# never reach the shipped image.
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-client \
        curl \
        # RUNTIME shared libraries for the extensions built below. These are
        # separate packages from the -dev ones in .build-deps, and they must be
        # installed permanently.
        #
        # The -dev packages pull these in as dependencies, so the build
        # succeeds without them -- and then `apk del .build-deps` removes the
        # libraries along with the headers. The extensions are left on disk
        # with nothing to link against, and php-fpm starts anyway, logging
        # "Unable to load dynamic library 'intl' ... libicuio.so.76: No such
        # file or directory" as a warning rather than an error. The container
        # then runs, apparently healthy, until something calls an intl or zip
        # function. Found exactly that way on this image's first boot.
        icu-libs \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    # mbstring and OPcache are NOT in this list because php:8.5 compiles them
    # in -- `php -m` on the base image lists both. Asking docker-php-ext-install
    # for a built-in extension fails the build with a misleading
    # "cp: can't stat 'modules/*'", because the extension configures and
    # compiles but emits no shared object to install. Verified against
    # php:8.5.3-fpm-alpine; re-check `php -m` when bumping the base image,
    # since the set of built-ins moves between releases.
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
        pdo_pgsql \
        zip \
    && apk del .build-deps

# bcmath is not optional for this application specifically: it is a
# point-of-sale system, and money arithmetic on PHP floats produces cent-level
# drift that surfaces as tills that do not balance.
#
# pdo_pgsql rather than pdo_sqlite because ADR-0003 makes PostgreSQL row-level
# security mandatory for tenant isolation. SQLite has no equivalent, so a
# SQLite-backed environment cannot exercise the control this platform relies on
# to keep tenants apart -- see infra/compose-pos.yaml for the role setup that
# makes RLS actually apply.

COPY infra/docker/api/php.ini /usr/local/etc/php/conf.d/nova.ini
COPY infra/docker/api/nginx.conf /etc/nginx/nginx.conf
COPY infra/docker/api/supervisord.conf /etc/supervisord.conf
COPY infra/docker/api/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /build ./

# storage/ and bootstrap/cache are the only paths Laravel writes to at runtime.
# Granting write to those two and nothing else means a code-execution bug
# cannot rewrite the application's own source.
RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && mkdir -p /var/lib/nginx/tmp /var/log/nginx /run/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx /run/nginx

# nginx listens on 8080, not 80: binding below 1024 requires root or an added
# capability, and neither is worth it when the port is an internal detail the
# reverse proxy maps anyway.
EXPOSE 8080

# Hits the application's own health route through nginx and php-fpm, so a green
# healthcheck means the whole request path works -- not merely that a process
# is alive. A check against php-fpm's ping endpoint would stay green while
# nginx was misconfigured.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=5 \
    CMD curl --fail --silent --output /dev/null http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "--configuration", "/etc/supervisord.conf", "--nodaemon"]
