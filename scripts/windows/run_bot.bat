@echo off
echo ==============================================
echo    Attendance System - Telegram Bot Service
echo ==============================================
echo.
echo Installing requirements (this may take a moment)...
pip install pyTelegramBotAPI markdown2 fpdf2 psutil
echo.
cd /d "%~dp0..\.."

echo Starting bot...
python bot/attendance_bot.py
pause
