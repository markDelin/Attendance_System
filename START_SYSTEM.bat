@echo off
title Attendance System - ALL IN ONE
echo Starting PHP Server and Telegram Bot...
echo.

start "" "scripts\windows\run.bat"
timeout /t 2
start "" "scripts\windows\run_bot.bat"

echo.
echo Both services are starting in separate windows.
echo Check the dashboard for your Mobile Connection IP.
timeout /t 5
exit
