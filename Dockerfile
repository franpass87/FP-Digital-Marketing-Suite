FROM wordpress:latest

# Install additional PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/master/bin/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Copy plugin files
COPY . /var/www/html/wp-content/plugins/fp-digital-marketing-suite/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/wp-content/plugins/fp-digital-marketing-suite/

# Install plugin dependencies
WORKDIR /var/www/html/wp-content/plugins/fp-digital-marketing-suite/
RUN composer install --no-dev --optimize-autoloader

WORKDIR /var/www/html