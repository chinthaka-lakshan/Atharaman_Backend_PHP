#!/bin/bash

# Copy the custom Nginx config to the system folder
cp /home/site/wwwroot/default /etc/nginx/sites-available/default

# Reload Nginx to apply changes
service nginx reload

# Clear Laravel caches to be safe
php /home/site/wwwroot/artisan config:clear
php /home/site/wwwroot/artisan route:clear
