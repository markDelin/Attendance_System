#!/bin/bash
# setup_mobile.sh - Automated dependency installer for Termux/Linux
echo "🚀 Starting Attendance System Setup (Termux/Mobile)..."

# Check if running in Termux
if [ -d "/data/data/com.termux" ]; then
    echo "📱 Termux environment detected."
    # Update and Install system packages
    pkg update && pkg upgrade -y
    pkg install php python python-pip sqlite -y
    # Install Python dependencies
    pip install pyTelegramBotAPI markdown2 fpdf2
else
    echo "🐧 Linux environment detected."
    # Try Debian/Ubuntu style
    if command -v apt-get &> /dev/null; then
        sudo apt-get update
        sudo apt-get install php-cli php-sqlite3 python3 python3-pip sqlite3 -y
    fi
    # Install Python dependencies
    pip3 install pyTelegramBotAPI markdown2 fpdf2
fi

# Ensure correct permissions
chmod +x start_mobile.sh
chmod +x setup_mobile.sh

echo ""
echo "✅ Setup Complete!"
echo "👉 You can now run: ./start_mobile.sh"
echo "💡 Tip: If you are in Termux, remember to run 'termux-setup-storage' to access your phone storage."
