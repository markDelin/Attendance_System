@echo off
title Attendance System - PUBLIC HTTPS (Localtunnel)
echo 🚀 Attempting to start a Secure Public HTTPS tunnel...
echo.
echo [IMPORTANT]: 
echo 1. Keep your PHP server running in the other window.
echo 2. When you open the link on your phone, click 'Continue' if you see a landing page.
echo 3. This link is SECURE and will allow your mobile camera to work!
echo.
echo Press Ctrl+C then Y to stop the tunnel.
echo.

npx localtunnel --port 8000

echo.
echo [ERROR]: If the command failed, make sure you have Node.js installed.
pause
