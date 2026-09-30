# 1. Limpiar caché de Laravel
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan optimize:clear

# 2. Limpiar caché de Spatie
php artisan permission:cache-reset

# 3. Re-sincronizar permisos
php artisan permissions:sync --show

# 4. Recargar autoload
composer dump-autoload