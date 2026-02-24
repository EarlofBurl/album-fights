#!/bin/sh

# Fix permissions for the folders we know the app needs to write to
# We do this every time the container starts to ensure new volumes work
chown -R www-data:www-data /var/www/html/data
chown -R www-data:www-data /var/www/html/cache

# Hand control back to the standard Apache web server process
exec apache2-foreground