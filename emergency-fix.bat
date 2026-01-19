@echo off
echo ==========================================
echo   СРОЧНОЕ ИСПРАВЛЕНИЕ
echo ==========================================
echo.

echo Очистка кэша конфигурации...
php artisan config:clear

echo Очистка кэша приложения...
php artisan cache:clear

echo Очистка кэша маршрутов...
php artisan route:clear

echo Очистка кэша представлений...
php artisan view:clear

echo.
echo ==========================================
echo   ГОТОВО!
echo ==========================================
echo.
echo Теперь обновите браузер (Ctrl+F5)
echo и попробуйте снова:
echo.
echo - /cabinet/my/requests/create
echo - /manage/manage-requests/create
echo.
pause
