# 🎓 QR Tools - Advanced Attendance System

A comprehensive, high-performance QR code-based attendance tracking system. Designed with a sleek "Swiss Academic" minimalist UI, this system automates entry logging, subject-specific attendance, robust Telegram integrations, and school year progress tracking.

## 🌟 Key Features

- **Blazing Fast QR Scanning**: Real-time scanning for both Desktop and Mobile devices.
- **Smart Status Calculation**: Automated evaluation of Present/Late/Absent status based on configurable Grace Periods.
- **Telegram Bot Integration**: Full administrative control from Telegram. Query student dossiers, generate PDF reports, pull latest stats, and send broadcast announcements directly to group chats.
- **School Year Management**: Seamlessly switch between academic years or semesters and track overall progress inside the main dashboard.
- **Subject & Event Specific Tracking**: Record attendance independently for daily general ingress, specific class subjects, or special events.
- **Ambagan (Billing) Module**: Track payments, classroom contributions, and billing histories.
- **Swiss Academic UI**: A focused, dark-mode-first aesthetic with high-density data tables and fluid micro-animations.

---

## 🚀 Quick Start (Windows)

**The easiest way to launch both the Web Server and the Telegram Bot:**

1. Open the project folder.
2. Double-click the **`START_SYSTEM.bat`** file.
3. The Web Application will be available at **[http://localhost:8000](http://localhost:8000)**.
4. The Telegram Bot will be operational (check the console for details).

> **Note**: This batch file automatically manages the PHP configuration and loads the bot logic to ensure everything runs seamlessly together.

## 📱 Mobile/Linux Deployment (Termux)

If you are running the system on an Android device via Termux or a Linux server, follow these steps:

1. **Run the Setup Script**:
   ```bash
   bash scripts/mobile/setup_mobile.sh
   ```
   *This automatically installs PHP, Python, SQLite, and necessary Telegram bot libraries.*

2. **Start the Services**:
   ```bash
   bash scripts/mobile/start_mobile.sh
   ```

3. **Access the System**:
   Open a browser and navigate to `http://localhost:8000`.

---

## 📂 Project Structure

Following a modular directory structure for enhanced maintainability:

```text
attendance-system/
├── START_SYSTEM.bat         # Master Launcher (Windows)
├── index.php                # Main Dashboard
├── scan.php                 # Scanner Interface
├── settings.php             # Core Configuration
├── api/                     # Backend API Processors
├── assets/                  # CSS, JS, and Media assets
├── bot/                     # Python logic for Telegram Bot (attendance_bot.py) 
├── config/                  # Server configuration (php.ini)
├── database/                # Local SQLite Database (attendance.db)
├── docs/                    # Additional Documentation
├── includes/                # Shared UI components and DB connector
└── scripts/                 # OS-specific sub-scripts (Windows/unix)
```

## ❓ Troubleshooting

### "Could not find driver" / Database Errors
This usually means PHP cannot find the SQLite extension.
**Solution**: Always use the provided `START_SYSTEM.bat` or ensure you run the server using `php -S 0.0.0.0:8000 -c config/php.ini`.

### Camera Not Working
- **Desktop**: Ensure your browser has not blocked camera access (check the icon near the address bar).
- **Mobile Network Access**: If accessing via a local network IP (e.g., `192.168.1.x`), browsers often block the camera on unencrypted `http`. Navigate to `chrome://flags` on your mobile browser and add your IP to "Insecure origins treated as secure".

## 📜 License

[MIT License](LICENSE)
