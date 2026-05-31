# 🎓 QR Tools: Advanced Academic & Attendance Ecosystem

[![Version](https://img.shields.io/badge/version-2.6.0-blue.svg?style=for-the-badge)](https://github.com/markDelin/Attendance_System)
[![Platform](https://img.shields.io/badge/Platform-Windows%20%7C%20Linux%20%7C%20Android-green.svg?style=for-the-badge)](https://github.com/markDelin/Attendance_System)

A high-performance, enterprise-grade attendance tracking and student management ecosystem. This system integrates QR technology, real-time analytics, subject-based attendance, automated Telegram communication, and a complete student dossier system.

---

## 🌟 Core Features

### 📡 Smart Attendance & QR Scanning
- **Blazing Fast QR Scanning**: Sub-millisecond scanning with instant feedback, audio cues, and visual overlays.
- **Automated Status Engine**: Evaluates **Present/Late/Absent** status based on dynamic grace periods and call time.
- **Subject-Specific & Daily Mode**: Independent attendance logs per subject/event or general daily entry.
- **Manual QR Input**: Type or paste QR codes directly when camera scanning is unavailable.
- **System Re-attendance**: Smart correction module to re-evaluate logs or handle re-entry for past dates.
- **Context Selection**: Filter attendance by academic subject or special event.

### 📊 Real-Time Dashboard & Analytics
- **Live KPI Tracking**: Birthday countdowns, daily attendance rates, population stats, and system health.
- **Daily Status Insights**: Visual heatmaps and status distributions for the current academic day.
- **School Year Progress**: Visual progress bars showing the current academic year's completion percentage.
- **Subject Matrix**: Per-subject attendance breakdown with visual trend bars, stats, and status badges.
- **Master Progress Bar**: Aggregated attendance overview across all enrolled subjects.

### 👨‍🎓 Student Profile & Dossier System
- **Comprehensive Student Profiles**: Centralized hub for academic info, personal details, contact channels, and emergency contacts.
- **Attendance Timeline**: Last 10 logged activities with status badges and timestamps.
- **Attendance Performance Chart**: Circular conic-gradient chart showing present/late/absent ratio.
- **Weekly Schedule Modal**: Day-by-day view of all class schedules.
- **Edit Dossier**: Full CRUD for all student fields including birthday media thumbnails.
- **QR Code Download**: One-click download of student QR codes.

### 📚 Subject & Enrollment Management
- **Subject Matrix Table**: Single-line row layout with Code, Subject, Schedule, Stats, Trend bar, Lecturer, and Status columns.
- **Semester Grouping**: Subjects grouped by semester with divider badges and counts.
- **Filter Chips**: Filter by semester or attendance status (All, 1st Sem, 2nd Sem, Excellent, Good, At Risk).
- **Enrollment System**: Irregular students get manual subject management; Regular students auto-enroll in all current SY subjects.
- **Auto-Enroll Button**: One-click enrollment of all regular students across both semesters.
- **Subject CRUD**: Create, edit, delete subjects with schedules, lecturers, rooms, and categories.

### 📅 Schedule Management
- **Visual Weekly Schedule**: 7-column grid showing Monday through Saturday class slots.
- **Rowspan Merging**: Consecutive same-day classes auto-merge for cleaner display.
- **Export to Image**: Download schedule as PNG via html2canvas.
- **Theme Preview**: Real-time color theme previews for schedule customization.
- **Inline Editing**: Edit class slots directly with day, time, room, and lecturer fields.

### 📦 Order & Inventory Management
- **Transaction Logs**: Track and manage ordered records and student fulfillments.
- **Inventory Control**: Dedicated module to manage products and supplies.
- **Status Tracking**: Monitor order statuses from pending to completed or cancelled.

### 👥 Student & Group Management
- **Advanced Student Directory**: Searchable, filterable student list with avatars and quick actions.
- **Promotion System**: Seamlessly advance student year levels (1st→2nd, 2nd→3rd, etc.) with automated graduation tagging.
- **Group Shuffler**: Random group generator with animated slot-machine effect and confetti celebration.
- **Group Saving/Loading**: Save group configurations and load them later.
- **Recycle Bin**: Fail-safe data management with dedicated restoration for deleted records.

### 🤖 Telegram Bot & Communication
- **Administrative CLI**: Control the system via Telegram. Query student data, generate PDF reports, and pull live stats.
- **Broadcast Announcements**: Send formatted announcements with template presets.
- **Birthday Alerts**: Automated countdowns and Telegram notifications for student birthdays.
- **Live Bot Status**: Dashboard indicator showing bot connectivity state.

### 🎂 Birthday Celebration System
- **Upcoming Birthdays Widget**: Dashboard card showing nearest birthdays with countdown timers.
- **Birthday Media**: Per-student birthday greeting thumbnails/images.
- **Today's Birthdays**: Special highlight cards with glow animation for current-day birthdays.

### ⚙️ System Administration
- **Settings Panel**: Manage active school year, date ranges, call time, grace period, maintenance mode, registration lock, time-in/time-out toggle, and birthday image.
- **Subject Portal Link**: Quick-access button linking to subject management.
- **Theme Toggle**: Dark/light mode with system preference detection.
- **Maintenance Mode**: Lock scanner and manual entry during system maintenance.
- **Database**: SQLite3 with automated backup and restore capabilities.

---

## 🚀 Quick Start (Windows)

**Launch the entire ecosystem (Web Server + Telegram Bot) in one click:**

1.  Navigate to the project root.
2.  Execute **`START_SYSTEM.bat`** or **`scripts/windows/run.bat`**.
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
├── START_SYSTEM.bat              # Windows Master Launcher
├── index.php                     # Analytics Dashboard
├── profile.php                   # Student Profile & Dossier
├── scan.php                      # QR Scanner Engine
├── manage_students.php           # Student Lifecycle Manager
├── subjects.php                  # Subject & Schedule CRUD
├── schedule.php                  # Visual Weekly Schedule
├── manual.php                    # Manual Attendance Entry
├── reattendance.php              # Re-attendance for Past Dates
├── groups.php                    # Section & Group Shuffler
├── announcements.php             # Telegram Broadcast System
├── settings.php                  # System Configuration
├── recycle_bin.php               # Deleted Records Restoration
├── student_history.php           # Per-student Attendance History
├── view_attendance.php           # Daily Attendance View
├── view_subjects_list.php        # Subject Attendance Lists
├── view_subject_attendance.php   # Per-subject Attendance Logs
├── orders.php                    # Order & Transaction Log
├── manage_products.php           # Inventory Management
├── calendar.php                  # Academic Calendar
├── markdown_editor.php           # Markdown-based Editor
├── wifi.php                      # WiFi Configuration
├── api/                          # Backend Processors
│   ├── process.php               # QR Scan Processing
│   ├── subject_process.php       # Subject Attendance Processing
│   ├── auto_enroll.php           # Auto-Enroll Regular Students
│   ├── manage_enrollment.php     # Student-Subject Enrollment
│   ├── mark_absentees.php        # Automated Absentee Logic
│   ├── export_all_subjects.php   # Bulk Subject Export
│   ├── export_subject.php        # Single Subject Export
│   ├── get_analytics.php         # Dashboard Analytics API
│   ├── dashboard_stats.php       # Live Stats Endpoint
│   ├── fetch_stats.php           # Real-time Stat Fetching
│   ├── get_daily_status.php      # Daily Status Distribution
│   ├── get_recent.php            # Recent Activity Feed
│   ├── get_calendar_events.php   # Calendar Events API
│   ├── save_schedule.php         # Schedule Save Endpoint
│   ├── manage_users.php          # User CRUD Operations
│   ├── manage_orders.php         # Order Management
│   ├── process_announcement.php  # Broadcast Processing
│   ├── update_attendance_status.php
│   ├── promote_students.php      # Year-Level Promotion
│   ├── delete.php                # Soft Delete Handler
│   ├── restore_process.php       # Restore Deleted Records
│   ├── import_data.php           # Data Import
│   ├── export.php                # Excel/PDF Generation
│   ├── export_data.php           # Data Export API
│   ├── search_students.php       # Live Search API
│   ├── download_dossier.php      # Dossier Download
│   ├── upload_image.php          # Birthday Image Upload
│   └── groups_process.php        # Group Management API
├── bot/                          # Python Telegram Bot
│   ├── attendance_bot.py         # Main Bot Controller
│   ├── generate_report.py        # PDF Report Generator
│   └── test_applied_bot.py       # Bot Testing Scripts
├── database/                     # SQLite3 Storage
│   └── attendance.db             # Main Database
├── assets/                       # Frontend Assets
│   ├── css/
│   │   ├── style.css             # Main Stylesheet
│   │   └── AnimatedList.css       # List Animations
│   ├── js/
│   │   ├── toast.js              # Toast Notifications
│   │   ├── AnimatedList.js       # Animated List Controller
│   │   └── html5-qrcode.min.js   # QR Scanner Library
│   ├── audio/                    # Scan Sound Effects
│   ├── vendor/                   # Bootstrap Icons
│   └── images/                   # Static Assets
├── config/
│   └── php.ini                   # PHP Server Configuration
├── includes/
│   ├── db.php                    # Database Connection
│   ├── navbar.php                # Navigation Bar
│   ├── footer.php                # Page Footer
│   ├── theme_loader.php          # Theme Initialization
│   ├── search_overlay.php        # Global Search
│   └── telegram.php              # Telegram API Wrapper
├── scripts/
│   ├── windows/
│   │   ├── run.bat               # Windows Server Launcher
│   │   └── start_public_https.bat # HTTPS Public Server
│   └── mobile/
│       ├── setup_mobile.sh       # Termux Setup
│       └── start_mobile.sh       # Mobile Server Launcher
└── docs/
    └── sqlite.txt                # Database Documentation
```

---

## 🛠️ Technology Stack

- **Core**: PHP 8.x (Backend), SQLite3 (Database)
- **UI/UX**: Vanilla CSS3 (Dark/Light Mode), Vanilla JS (ES6+)
- **QR**: html5-qrcode (Camera Scanning)
- **Automation**: Python 3.10+ (Telegram Bot API)
- **Charts**: Pure CSS3 Conic Gradients, Inline SVG
- **Export**: html2canvas (Schedule PNG), PHPExcel-compatible HTML tables
- **Deployment**: Portable PHP Server, Termux Compatibility

---

## 📜 License & Contribution

This project is licensed under the **MIT License**. Contributions for UI/UX improvements or new API integrations are welcome via Pull Requests.

---

*Made with ❤️ by MCK.*
