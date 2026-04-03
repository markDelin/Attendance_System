@echo off
echo ==============================================
echo    Attendance System - Telegram Bot Service
echo ==============================================
echo.
echo Installing requirements...
pip install pyTelegramBotAPI markdown2 fpdf2 > nul 2>&1
echo.
cd /d "%~dp0..\.."

echo Starting bot...
python bot/attendance_bot.py
pause
