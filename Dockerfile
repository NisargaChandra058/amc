# Use the official PHP image with Apache web server
FROM php:8.2-apache

# Enable Apache's rewrite module
RUN a2enmod rewrite

# Copy the custom Apache configuration
COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# --- THIS IS THE FIX ---
# Install system dependencies required for PHP extensions
# Added libraries for gd (images) and zip (Excel/Word) support
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure and install the gd, zip, and curl extensions
# 'gd' needs configuration to know where the jpeg/freetype libs are
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_pgsql zip curl
# --- END OF FIX ---

# Install Composer for PHP dependency management
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Set the working directory for our application
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json .
RUN composer install

# Copy the rest of the application source code
COPY . .

# --- PERMISSIONS FIX ---
# 1. Create a writable directory for PHP sessions
RUN mkdir -p /var/www/sessions

# 2. Change the owner of ALL files and folders to the Apache user
RUN chown -R www-data:www-data /var/www/html
RUN chown -R www-data:www-data /var/www/sessions

# 3. Explicitly grant read/write permissions
RUN chmod -R 755 /var/www/html
RUN chmod -R 775 /var/www/sessions
