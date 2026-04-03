# Attendance System

A QR code-based attendance tracking system with automated late/absent marking functionality. The system records attendance via QR scan and automatically categorizes status (Present, Late, Absent) based on configurable time rules.

## 🚀 Quick Start (Windows)

**The easiest way to run the application:**

1.  Open the project folder.
2.  Double-click the **`run.bat`** file.
3.  The application will start at **[http://localhost:8000](http://localhost:8000)**.

> **Note**: This script automatically applies the correct PHP configuration to ensure the database works correctly.

---

## 🛠️ Manual Run (Command Line)

If you prefer using the terminal, run the following command in the project directory:

```bash
C:\php\php.exe -S localhost:8000 -c php.ini
```

_The `-c php.ini` flag is critical as it loads the required SQLite database drivers._

## Features

- **QR Scanning**: Real-time scanning for Desktop and Mobile.
- **Automated Status**: Smart calculation of Present/Late/Absent status.
- **Configurable**: Adjust Call Time, Grace Period, and Absent Thresholds in Settings.
- **User Management**: Auto-registration for new QR codes.
- **Billing**: Track payments and billing history (if enabled).
- **Schedule Maker**: Create, edit, and export class schedules with multiple premium templates.
- **Unified Navigation**: Glassmorphism navbar and mobile-ready bottom navigation.

## 📂 File Structure

```
attendance-system/
├── db.php               # Database connection
├── index.php            # Dashboard
├── scan.php             # Scanner Interface
├── view_attendance.php  # Records & Reports
├── settings.php         # Configuration
├── run.bat              # Windows Launcher
├── php.ini              # Local PHP Config
└── attendance.db        # SQLite Database
```

## ❓ Troubleshooting

### "Could not find driver" / Database Errors

This usually means PHP cannot find the SQLite extension.
**Solution**: Always use `run.bat` or include `-c php.ini` when running the server manually.

### Camera Not Working

- **Desktop**: Check if your browser has blocked camera access (look for an icon in the address bar).
- **Mobile**: Ensure you are on a secure context (localhost always works). If accessing via network IP, some browsers might block the camera on HTTP.

### System Requirements

- Modern Web Browser (Chrome, Edge, Firefox, Safari)

## 📱 Mobile/Linux (Termux) Deployment

If you are running the system on an Android device via Termux or a Linux server, follow these steps:

1.  **Run the Setup Script**:
    ```bash
    bash setup_mobile.sh
    ```
    _This will automatically install PHP, Python, SQLite, and necessary libraries._

2.  **Start the Services**:
    ```bash
    bash start_mobile.sh
    ```

3.  **Access the System**:
    Open Chrome/Browser and navigate to `http://localhost:8000`.

> **Tip**: To access student records from your phone's camera, ensure you use `localhost`. If accessing from another device on the same Wi-Fi, you may need to use `https` or configure browser flags to allow camera access on insecure origins.

## License

[MIT License](LICENSE)
