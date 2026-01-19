@echo off
echo Clearing Laravel caches...
echo.

php artisan config:clear
echo Config cache cleared

php artisan cache:clear
echo Application cache cleared

php artisan route:clear
echo Route cache cleared

php artisan view:clear
echo View cache cleared

echo.
echo All caches cleared successfully!
echo Please refresh your browser.
pause
