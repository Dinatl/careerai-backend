#!/bin/sh

php artisan config:clear
php artisan cache:clear
php artisan migrate --force

apache2-foreground