#!/bin/bash
# start_mobile.sh - Start Attendance System on Termux/Linux
echo ">> Starting Attendance System (Mobile/Linux Mode)..."

# Dependency Checks
if ! command -v php &> /dev/null; then
    echo "[ERROR] PHP NOT FOUND. Running setup..."
    bash setup_mobile.sh
fi

if ! command -v python3 &> /dev/null; then
    echo "[ERROR] Python3 NOT FOUND. Running setup..."
    bash setup_mobile.sh
fi

# Kill any existing processes
pkill -f "php -S"
pkill -f "python3 attendance_bot.py"

# Ensure log files exist
touch php_server.log bot.log

# Ensure we are in the script's directory
cd "$(dirname "$0")"

# Start PHP Server in background with router.php
php -S 0.0.0.0:8000 -c php-mobile.ini router.php > php_server.log 2>&1 &
echo "[OK] PHP Server started on port 8000"

# Start Python Bot in background
python3 attendance_bot.py > bot.log 2>&1 &
echo "[OK] Telegram Bot started"

echo ""
echo "[i] Both services are running in the background."
echo "Access at: http://localhost:8000"
echo "Check logs with: tail -f php_server.log or tail -f bot.log"
echo "To stop: pkill -f php && pkill -f python3"
