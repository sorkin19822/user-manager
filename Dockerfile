FROM php:8.5-apache

# Install system deps + PHP extensions
RUN apt-get update && apt-get install -y unzip git \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache config
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Copy project files
COPY composer.json composer.lock* /var/www/html/
RUN composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

COPY . /var/www/html/

# Entrypoint: run migrations then start Apache
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
