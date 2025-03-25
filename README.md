# Attendance System Documentation

## Overview
A QR code-based attendance tracking system with automated late/absent marking functionality. The system allows users to scan QR codes to record attendance and automatically determines if the attendee is present, late, or absent based on configurable time rules.

## Features
- **QR Code Attendance Scanning**: Real-time scanning of QR codes to record attendance
- **Automated Status Calculation**: Automatically marks attendance as Present, Late, or Absent
- **Configurable Time Settings**: Adjustable call time, grace period, and absent threshold
- **User Management**: Automatically registers new users when scanning unknown QR codes
- **Attendance Records**: View historical attendance data with status indicators
- **Responsive Design**: Works on both desktop and mobile devices

## System Requirements
- PHP 7.4 or higher
- SQLite (included with PHP)
- Web server (Apache, Nginx, etc.)
- Modern web browser with camera access

## Installation
1. Clone the repository:
   ```bash
   git clone https://github.com/markDelin/Attendance_System.git
   ```
2. Navigate to the project directory:
   ```bash
   cd attendance-system
   ```
3. Ensure the web server has write permissions to the directory (for SQLite database)
4. Configure your web server to point to the project directory

## File Structure
```
attendance-system/
├── db.php               # Database connection and initialization
├── index.php            # Main landing page
├── scan.php             # QR code scanning interface
├── process.php          # Backend processing for attendance scans
├── view_attendance.php  # Attendance records viewer
├── settings.php         # Time settings configuration
├── delete.php           # Record deletion handler
├── attendance.db        # SQLite database (created automatically)
└── README.md            # This documentation file
```

## Database Schema
The system uses SQLite with the following tables:

### users
- `qr_code` (TEXT, PRIMARY KEY): Unique QR code identifier
- `name` (TEXT): User's name
- `created_at` (TIMESTAMP): When the user was registered

### attendance
- `id` (INTEGER, PRIMARY KEY): Record ID
- `qr_code` (TEXT): Reference to users table
- `date` (TEXT): Date of attendance (YYYY-MM-DD)
- `time` (TEXT): Time of scan (HH:MM:SS)
- `status` (TEXT): Attendance status (present/late/absent)
- `created_at` (TIMESTAMP): When record was created

### settings
- `id` (INTEGER, PRIMARY KEY): Settings ID
- `call_time` (TEXT): Official start time (HH:MM)
- `grace_period` (INTEGER): Minutes before late status (default: 20)
- `absent_after` (INTEGER): Additional minutes before absent status (default: 30)
- `updated_at` (TIMESTAMP): When settings were last updated

## Usage Guide

### 1. Home Page (`index.php`)
- **Scan Attendance**: Navigate to the QR scanner
- **View Attendance**: View historical attendance records
- **Settings**: Configure time rules

### 2. Scanning Attendance (`scan.php`)
- Point the camera at a QR code to scan
- New users will be prompted to enter their name
- System automatically records time and determines status

### 3. Viewing Records (`view_attendance.php`)
- Displays attendance grouped by date
- Color-coded status indicators (green=present, yellow=late, red=absent)
- Options to delete individual records or entire days

### 4. Configuring Settings (`settings.php`)
- Set the official call time (24-hour format)
- Adjust grace period (minutes before late status)
- Set absent threshold (minutes after grace period before absent)

## API Endpoints

### `process.php`
Handles QR code processing. Accepts POST requests with:
- `qr_code`: The scanned QR code
- `name`: (Optional) For new user registration

Returns JSON response with:
- `status`: "success", "new", or "error"
- `message`: Descriptive message
- `attendance_status`: (On success) present/late/absent
- `time_recorded`: Time of recording

## Security Considerations
- Input sanitization on all user-provided data
- SQL injection prevention using prepared statements
- No sensitive data is stored in the system
- Recommended to run behind HTTPS in production

## Troubleshooting
- **Camera not working**: Ensure browser has camera permissions
- **Database errors**: Check write permissions on directory
- **QR codes not scanning**: Ensure good lighting and camera focus

## License
[MIT License](LICENSE)

## Screenshots
(Include screenshots of main interfaces in your GitHub repository)

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss proposed changes.

## Future Enhancements
- User roles and permissions
- Reporting and analytics
- Bulk import/export functionality
- Email notifications

---

This documentation provides a comprehensive overview of your system for GitHub. You can add screenshots to make it more visually appealing. The documentation covers all key aspects while excluding the download and events features as requested.
