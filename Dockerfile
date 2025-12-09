FROM dunglas/frankenphp:latest

LABEL maintainer="antigraviti" \
      description="Document OCR & Archival System" \
      version="1.0.0"

# Install PHP extensions required for the application
RUN set -eux; \
    apt-get update; \
    apt-get install -y \
        libpq-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libxpm-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        libgmp-dev \
        libldap2-dev \
        libmemcached-dev \
        librdkafka-dev \
        tesseract-ocr \
        libtesseract-dev \
        libleptonica-dev \
        ghostscript \
        imagemagick \
        && \
    docker-php-ext-configure pgsql -with-pgsql=/usr/include/pgsql && \
    docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        zip \
        gd \
        bcmath \
        intl \
        gmp \
        pcntl \
        sockets \
        sysvmsg \
        sysvsem \
        sysvshm \
        && \
    pecl install \
        apcu \
        redis \
        xdebug \
        && \
    docker-php-ext-enable \
        apcu \
        redis \
        && \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false -o APT::AutoRemove::SuggestsImportant=false \
        libpq-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libxpm-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        libgmp-dev \
        && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader && \
    composer dump-autoload --optimize

# Create directories for storage and cache
RUN mkdir -p storage/logs storage/app storage/framework/cache storage/framework/sessions storage/framework/views && \
    chmod -R 777 storage

# Create non-root user
RUN groupadd -g 1000 app && \
    useradd -u 1000 -g app -s /bin/sh -d /app app && \
    chown -R app:app /app

# Switch to non-root user
USER app

# Expose port
EXPOSE 8000

# Set the default server
ENV FRANKENPHP_CONFIG="index.php"

# Set production environment
ENV APP_ENV=production \
    APP_DEBUG=false

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# Start FrankenPHP
ENTRYPOINT ["frankenphp", "serve", "--config", "frankenphp.Caddyfile"]