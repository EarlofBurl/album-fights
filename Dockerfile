FROM php:8.2-apache

# Copy all app files into the container's web directory
COPY . /var/www/html/

# Create necessary directories and give the Apache user (www-data) permission to write to them
RUN mkdir -p /var/www/html/data /var/www/html/cache \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/cache \
    && chmod -R 775 /var/www/html/data /var/www/html/cache

# Expose port 80
EXPOSE 80