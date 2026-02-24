# Use the official PHP + Apache image
FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Copy your project files into the container
COPY . /var/www/html/

# Copy the permission-fix script
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Document that this container uses port 80
EXPOSE 80

# Start the container using our script to fix volumes
ENTRYPOINT ["entrypoint.sh"]