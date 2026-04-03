@echo off

echo Starting Attendance System...
echo Server running at http://localhost:8000
echo Press Ctrl+C to stop the server.

if exist "C:\php\php.exe" (
    "C:\php\php.exe" -S localhost:8000 -c php.ini
) else (
    php -S localhost:8000 -c php.ini
)

pause