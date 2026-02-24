# Use the official PHP + Apache image
FROM php:8.2-apache

# Install any PHP extensions if needed (usually none for this app, but good to have)
RUN docker-php-ext-install pdo pdo_mysql

# Copy your project files into the web server directory
COPY . /var/www/html/

# Set permissions so the app can write to the data and cache folders
# We create them first to ensure they exist with the right owner
RUN mkdir -p /var/www/html/data /var/www/html/cache \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/cache \
    && chmod -R 775 /var/www/html/data /var/www/html/cache

# Expose port 80
EXPOSE 80