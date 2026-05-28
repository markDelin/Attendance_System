# 🎓 QR Tools: Advanced Academic & Attendance Ecosystem

[![Version](https://img.shields.io/badge/version-2.5.0-blue.svg?style=for-the-badge)](https://github.com/markDelin/Attendance_System)
[![UI](https://img.shields.io/badge/UI-Neumorphic-indigo.svg?style=for-the-badge)](https://github.com/markDelin/Attendance_System)
[![Platform](https://img.shields.io/badge/Platform-Windows%20%7C%20Linux%20%7C%20Android-green.svg?style=for-the-badge)](https://github.com/markDelin/Attendance_System)

A high-performance, enterprise-grade attendance tracking and student management ecosystem. Built with a sophisticated **Neumorphic UI** and optimized for both desktop and ultra-wide (20:9) mobile displays, this system integrates QR technology, real-time analytics, and automated communication via Telegram.

---

## 🌟 Core Features

### 📡 Smart Ingress & Attendance
- **Blazing Fast QR Scanning**: Sub-millisecond scanning with instant feedback and audio cues.
- **Automated Status Engine**: Evaluates **Present/Late/Absent** status based on dynamic grace periods.
- **Subject-Specific Tracking**: Manage independent attendance logs for specific classes, events, or general daily entry.
- **System Re-attendance**: Smart correction module to re-evaluate logs or handle re-entry without data duplication.

### 📊 Advanced Analytics & Dashboard
- **Real-Time KPI Tracking**: Instant visibility into attendance rates, student population stats, and system health.
- **Daily Status Insights**: Visual heatmaps and status distributions for the current academic day.
- **Academic Progress Tracking**: Visual progress bars showing the current school year's completion status.

### 📦 Order & Inventory Management
- **Transaction Logs**: Track and manage ordered records and student fulfillments.
- **Inventory Control**: Dedicated module to manage products, supplies, or materials associated with the student body.
- **Status Tracking**: Monitor order statuses from pending to completed or cancelled.

### 👨‍🎓 Student & Group Management
- **Detailed Student Database**: Centralized hub for student profiles, academic history, and contact information.
- **Promotion System**: Seamlessly advance student year levels (1st→2nd, 2nd→3rd, etc.) with automated graduation tagging.
- **Group Management**: Organically organize students into sections, groups, or specialized teams.
- **Recycle Bin**: Fail-safe data management with a dedicated restoration system for deleted records.

### 🤖 Telegram Bot & Communication
- **Administrative CLI**: Control the system via Telegram. Query student data, generate PDF reports, and pull live stats.
- **Broadcast Announcements**: Send formatted announcements directly to student groups or administrative chats.
- **Birthday Alerts**: Automated countdowns and Telegram notifications for student birthdays.

---

## 🚀 Quick Start (Windows)

**Launch the entire ecosystem (Web Server + Telegram Bot) in one click:**

1.  Navigate to the project root.
2.  Execute **`START_SYSTEM.bat`**.
3.  **Web Access**: [http://localhost:8000](http://localhost:8000)
4.  **Bot Interface**: Watch the console for initialization logs.

> [!TIP]
> This launcher automatically configures the PHP environment and initializes the Python-based Telegram engine.

---

## 📱 Mobile & Linux Deployment

For Android (Termux) or dedicated Linux servers:

1.  **Initialize Environment**:
    ```bash
    bash scripts/mobile/setup_mobile.sh
    ```
2.  **Start Services**:
    ```bash
    bash scripts/mobile/start_mobile.sh
    ```

---

## 📂 System Architecture

```text
Attendance_System/
├── START_SYSTEM.bat         # Windows Master Launcher
├── index.php                # Pro Analytics Dashboard
├── scan.php                 # Real-time QR Engine
├── manage_students.php      # Student Lifecycle Management
├── orders.php               # Order & Transaction Log
├── manage_products.php      # Inventory & Product Management
├── groups.php               # Section & Group Management
├── reattendance.php         # System Re-run & Correction
├── api/                     # High-performance Backend Processors
│   ├── get_analytics.php    # Data Visualization API
│   ├── mark_absentees.php   # Automated Absentee Logic
│   └── export.php           # Excel/PDF Generation Engine
├── bot/                     # Python-based Telegram Integration
│   ├── attendance_bot.py    # Main Bot Controller
│   └── generate_report.py   # PDF Report Generator
├── database/                # SQLite3 Secure Data Storage
└── assets/                  # Neumorphic CSS & Dynamic JS
```

---

## 🛠️ Technology Stack

- **Core**: PHP 8.x (Backend), SQLite3 (Database)
- **UI/UX**: Vanilla CSS3 (Neumorphic Framework), Vanilla JS (ES6+)
- **Automation**: Python 3.10+ (Telegram Bot API)
- **Deployment**: Portable PHP Server, Termux Compatibility

---

## 📜 License & Contribution

This project is licensed under the **MIT License**. Contributions for UI/UX improvements or new API integrations are welcome via Pull Requests.

---
*Made with ❤️ by MCK.*
