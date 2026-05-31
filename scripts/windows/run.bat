@echo off

echo Starting Attendance System...
echo Server running at http://localhost:8000
echo.
echo [MOBILE ACCESS]: To access this on your phone, connect to the same Wi-Fi network and open http://YOUR_PC_IP_ADDRESS:8000 
echo (Find your IP address by opening a new Command Prompt and typing: ipconfig)
echo.
echo Press Ctrl+C to stop the server.

cd /d "%~dp0..\.."

echo [NOTE]: If you see a 'Database Error: could not find driver' when opening the site,
echo it means your PHP extensions version in C:\php\ext does not match your PHP binary version.
echo Please look at the implementation plan for manual fix instructions.
echo.

if exist "C:\php\php.exe" (
    "C:\php\php.exe" -S 0.0.0.0:8000 -c config\php.ini router.php
) else (
    php -S 0.0.0.0:8000 -c config\php.ini router.php
)

pause