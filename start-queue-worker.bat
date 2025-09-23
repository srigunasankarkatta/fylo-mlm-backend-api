@echo off
echo Starting Laravel Queue Worker...
echo Press Ctrl+C to stop
php artisan queue:work --verbose --tries=3 --timeout=90
pause
