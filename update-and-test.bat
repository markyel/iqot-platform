@echo off
echo ==========================================
echo   Обновление и тестирование n8n API
echo ==========================================
echo.

echo [1/3] Очистка кэша Laravel...
php artisan config:clear
php artisan cache:clear
echo.

echo [2/3] Проверка конфигурации...
php artisan config:show services.n8n
echo.

echo [3/3] Тестирование аутентификации n8n...
php test-n8n-auth.php
echo.

echo ==========================================
echo   Готово!
echo ==========================================
echo.
echo Теперь попробуйте использовать AI-парсинг
echo в админке: /manage/manage-requests/create
echo.
pause
