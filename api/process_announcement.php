<?php
// api/process_announcement.php
header("Content-Type: application/json");
require_once __DIR__ . "/../includes/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$type = $_POST['type'] ?? '';
$content = $_POST['content'] ?? '';
$footer = "\n\n-System Admin";

try {
    $message = "";

    if ($type === 'custom') {
        if (empty($content)) {
            throw new Exception("Announcement content cannot be empty.");
        }
        // Sanitize or allow basic HTML? Bot uses parse_mode='HTML'
        // We'll allow the user to send HTML but we can do some basic cleanup if needed.
        $message = $content . $footer;
    } elseif ($type === 'student_count') {
        $setName = !empty($content) ? htmlspecialchars($content) : "A"; // Default to SET A if not specified
        
        // Count only active regular students for the specific SET
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND student_type = 'regular' AND section LIKE ?");
        $stmt->execute(["%$setName%"]);
        $regularCount = $stmt->fetchColumn();

        $message = "<b>🎓 SET $setName STATUS UPDATE</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━┳═─\n\n";
        $message .= "Active Regular Students: <b>$regularCount</b>\n\n";
        $message .= "Keep going, class! 🚀" . $footer;
    } elseif ($type === 'system_refresh') {
        // Fetch settings for active school year
        $settingsStmt = $pdo->query("SELECT active_school_year FROM settings LIMIT 1");
        $settings = $settingsStmt ? ($settingsStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        $sy = $settings['active_school_year'] ?? 'N/A';

        $f = function($p, $m) {
            $filled = $p / 10;
            $bar = str_repeat("█", $filled) . str_repeat("░", 10 - $filled);
            return "🔄 <b>SYSTEM REFRESHING</b>\n<code>[$bar] $p%</code>\n\n<i>$m...</i>";
        };

        $step1 = $f(20, "Initializing protocols");
        $step2 = $f(60, "Clearing subject cache");
        $step3 = $f(90, "Importing new curriculum");
        $step4 = "✅ <b>SYSTEM REFRESH COMPLETED</b>\n━━━━━━━━━━━━━━━━━━━━┳═─\n\nAcademic Year: <b>$sy</b>\n\nSystem is now ready for use! 📖" . $footer;

        $message = "[ANIMATE]" . $step1 . "[STEP]" . $step2 . "[STEP]" . $step3 . "[STEP]" . $step4;
    } elseif ($type === 'new_student') {
        $name = !empty($content) ? htmlspecialchars($content) : "Unknown";
        $message = "<b>🎉 NEW STUDENT NOTICE</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━┳═─\n\n";
        $message .= "New student notice = <b>$name</b>\n";
        $message .= "Coming soon" . $footer;
    } else {
        throw new Exception("Invalid announcement type.");
    }

    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO telegram_queue (message) VALUES (?)");
        $stmt->execute([$message]);
    }

    echo json_encode(['status' => 'success', 'message' => 'Announcement queued successfully.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
