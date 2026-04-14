#!/bin/bash
set -e

# Executa as migrations no boot
php artisan migrate --force

# Inicia o Apache
apache2-foreground
