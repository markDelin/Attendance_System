<?php
// api/process.php
date_default_timezone_set("Asia/Manila");
header("Content-Type: application/json");
require "../includes/db.php";
require_once "../includes/telegram.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") exit(json_encode(["status" => "error", "message" => "Invalid request method"]));
if (empty(trim($_POST["qr_code"]))) exit(json_encode(["status" => "error", "message" => "QR code is required"]));

// Get Settings
$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$defaults = ['call_time'=>'08:00', 'grace_period'=>20, 'absent_after'=>30, 'am_in'=>'08:00', 'am_out'=>'11:00', 'pm_in'=>'13:00', 'pm_out'=>'16:00', 'time_in_out_enabled'=>0];
$s = array_merge($defaults, $settings ?: []);

$qr = trim($_POST["qr_code"]);
$name = trim($_POST["name"] ?? "");
$subjectId = isset($_POST["subject_id"]) && $_POST["subject_id"] !== "" ? intval($_POST["subject_id"]) : null;

$today = $_POST['custom_date'] ?? date("Y-m-d");
$now = date("H:i:s");
$nowDisplay = date("h:i A");

// If custom date is used, we might want to set time to 12:00 PM or keep current time?
// Let's keep current time for log, but date is custom.
// Or if it's a past date, maybe user wants to set specific time? 
// For now, simple re-attendance usually implies just marking it for that day.

try {
  // Check if user exists
  $stmt = $pdo->prepare("SELECT * FROM users WHERE qr_code = ? AND deleted_at IS NULL");
  $stmt->execute([$qr]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  // Handle new users
  if (!$user) {
    // Check Database Lock
    if (!empty($settings['registration_lock'])) {
        echo json_encode([
            "status" => "error",
            "message" => "Registration is LOCKED. Unknown QR Code."
        ]);
        exit();
    }

    if (empty($_POST['first_name']) || empty($_POST['last_name'])) {
      echo json_encode([
        "status" => "new",
        "message" => "New QR code detected. Please Register.",
      ]);
      exit();
    }
    
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $name = $last_name . ', ' . $first_name;

    // Register new user
    $pdo
      ->prepare("INSERT INTO users (qr_code, name, first_name, last_name) VALUES (?, ?, ?, ?)")
      ->execute([$qr, $name, $first_name, $last_name]);
    $user = ['name' => $name]; 
  }

  // 3. Check existing attendance
  $stmt = $pdo->prepare("SELECT id FROM attendance WHERE qr_code = ? AND date = ?");
  $stmt->execute([$qr, $today]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);

  // Determine Status
  if ($subjectId) {
       // Scanner: Subject Mode defaults to Present
       $status = 'present';
  } elseif (isset($_POST['manual_entry'])) {
      $status = $_POST['force_status'] ?? 'present';
  } else {
      $status = calculateAttendanceStatus(
        $now,
        $s["call_time"],
        (int) $s["grace_period"],
        (int) $s["absent_after"]
      );
  }

  // 3a. Subject Attendance Handling
  if ($subjectId) {
      // STRICT ENROLLMENT CHECK (Modified)
      // Regular students: ALLOW (Skip check)
      // Irregular students: MUST be in student_subjects
      
      $type = $user['student_type'] ?? 'regular'; // Default to regular if null
      
      if ($type === 'irregular') {
          $checkEnrollment = $pdo->prepare("SELECT 1 FROM student_subjects WHERE qr_code = ? AND subject_id = ?");
          $checkEnrollment->execute([$qr, $subjectId]);
          if (!$checkEnrollment->fetch()) {
              echo json_encode([
                  "status" => "error", 
                  "message" => "Student NOT enrolled in this subject.",
                  "user_name" => $user["name"] ?? "Unknown"
              ]);
              exit();
          }
      }

      // Check Existing Subject Record
      $stmt = $pdo->prepare("SELECT id FROM subject_attendance WHERE subject_id = ? AND qr_code = ? AND date = ?");
      $stmt->execute([$subjectId, $qr, $today]);
      $existingSub = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($existingSub) {
          echo json_encode([
              "status" => "duplicate",
              "message" => "Already recorded for this subject today",
              "user_name" => $user["name"] ?? "Unknown"
          ]);
          exit();
      }

      // Insert Subject Record
      $pdo->prepare("INSERT INTO subject_attendance (subject_id, qr_code, date, status) VALUES (?, ?, ?, ?)")
          ->execute([$subjectId, $qr, $today, $status]);

      echo json_encode([
          "status" => "success",
          "message" => "Subject Attendance Recorded",
          "attendance_status" => $status,
          "user_name" => $user["name"] ?? $name
      ]);
      exit();
  }

  if ($existing) {
    // Handling Manual Update
    if (isset($_POST['manual_entry'])) {
        // If updating a past record, don't overwrite the time with current time.
        if ($today === date("Y-m-d")) {
            $sql = "UPDATE attendance SET status = ?, time = ? WHERE id = ?";
            $params = [$status, $nowDisplay, $existing['id']];
        } else {
            $sql = "UPDATE attendance SET status = ? WHERE id = ?";
            $params = [$status, $existing['id']];
        }

        $pdo->prepare($sql)->execute($params);

        echo json_encode([
            "status" => "success",
            "message" => strtoupper($status),
            "attendance_status" => $status,
            "time_recorded" => $nowDisplay,
            "user_name" => $user["name"] ?? $name,
        ]);
        exit();
    }

    echo json_encode([
      "status" => "duplicate",
      "message" => "Attendance already recorded for today",
      "user_name" => $user["name"] ?? "Unknown",
      "time_recorded" => date("h:i A")
    ]);
    exit();
  }

  // 4. Record attendance (Insert)
  $schoolYear = $s['active_school_year'] ?? 'SY 2024-2025';
  $pdo->prepare(
      "INSERT INTO attendance (qr_code, date, time, status, school_year) VALUES (?, ?, ?, ?, ?)"
    )
    ->execute([$qr, $today, $nowDisplay, $status, $schoolYear]);

  // Prepare response
  $response = [
    "status" => "success",
    "message" => strtoupper($status),
    "attendance_status" => $status,
    "time_recorded" => $nowDisplay,
    "user_name" => $user["name"] ?? $name,
  ];

  echo json_encode($response);
} catch (PDOException $e) {
  error_log("Database Error: " . $e->getMessage());
  echo json_encode([
    "status" => "error",
    "message" => "Database Error: " . $e->getMessage(),
  ]);
} catch (Exception $e) {
  error_log("Application Error: " . $e->getMessage());
  echo json_encode([
    "status" => "error",
    "message" => $e->getMessage(),
  ]);
}

/**
 * Calculate attendance status based on time parameters
 */
function calculateAttendanceStatus(
  $currentTime,
  $callTime,
  $gracePeriod,
  $absentAfter
) {
  $current = strtotime($currentTime);
  $call = strtotime($callTime . ":00");
  $graceEnd = $call + $gracePeriod * 60;
  $absentTime = $graceEnd + $absentAfter * 60;

  if ($current <= $call) {
    return "present";
  } elseif ($current <= $absentTime) {
    return "late";
  } else {
    return "absent";
  }
}

/**
 * Get appropriate status message
 */
function getStatusMessage($status)
{
  return strtoupper($status);
}
?>

