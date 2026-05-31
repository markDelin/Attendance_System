<?php
// profile.php - Student Profile V2
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

$qr = $_GET['qr'] ?? '';
if (empty($qr)) header('Location: manage_students.php');

// Fetch User
$stmt = $pdo->prepare("SELECT * FROM users WHERE qr_code = ?");
$stmt->execute([$qr]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("User not found.");

// Profile Update Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_initial = trim($_POST['middle_initial'] ?? '');
    $name = $last_name . ', ' . $first_name . ($middle_initial ? ' ' . $middle_initial . '.' : '');
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET 
            name = ?, first_name = ?, last_name = ?, middle_initial = ?, 
            birthday = ?, email = ?, course = ?, section = ?, sex = ?, 
            place_of_birth = ?, civil_status = ?, religion = ?, citizenship = ?, contact_number = ?,
            student_type = ?, year_level = ?, birthday_image = ?,
            home_address = ?, guardian_name = ?, guardian_contact = ?, blood_type = ?,
            lrn = ?, mother_name = ?, father_name = ?, guardian_relationship = ?
            WHERE qr_code = ?");
        $stmt->execute([
            $name, $first_name, $last_name, $middle_initial, 
            (!empty($_POST['birthday']) ? $_POST['birthday'] : null), 
            (!empty($_POST['email']) ? trim($_POST['email']) : null), 
            $_POST['course'], $_POST['section'] ?? '', $_POST['sex'], 
            $_POST['place_of_birth'], $_POST['civil_status'], $_POST['religion'], $_POST['citizenship'], $_POST['contact_number'], 
            $_POST['student_type'] ?? 'regular', $_POST['year_level'] ?? '1st',
            $_POST['birthday_image'] ?? '',
            $_POST['home_address'] ?? '',
            $_POST['guardian_name'] ?? '',
            $_POST['guardian_contact'] ?? '',
            $_POST['blood_type'] ?? '',
            $_POST['lrn'] ?? '',
            $_POST['mother_name'] ?? '',
            $_POST['father_name'] ?? '',
            $_POST['guardian_relationship'] ?? '',
            $qr
        ]);
        header("Location: profile.php?qr=$qr&msg=saved");
        exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Recent Activity
$activeSY = $pdo->query("SELECT active_school_year FROM settings LIMIT 1")->fetchColumn() ?: 'SY 2024-2025';
$stmtActivity = $pdo->prepare("
    SELECT session as subject_name, status, date, time FROM attendance 
    WHERE qr_code = ? AND (session IS NOT NULL AND session != '') AND school_year = ?
    UNION ALL
    SELECT s.name as subject_name, sa.status, sa.date, sa.time FROM subject_attendance sa 
    JOIN subjects s ON sa.subject_id = s.id WHERE sa.qr_code = ? AND s.school_year = ?
    ORDER BY date DESC, time DESC LIMIT 10
");
$stmtActivity->execute([$qr, $activeSY, $qr, $activeSY]);
$history = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);

// Detailed Analytics (Subject-centric + Daily Event logs)
$stmtStats = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent,
        COUNT(*) as total
    FROM (
        SELECT status FROM attendance WHERE qr_code = ? AND (session IS NOT NULL AND session != '') AND school_year = ?
        UNION ALL
        SELECT sa.status FROM subject_attendance sa
        JOIN subjects s ON sa.subject_id = s.id WHERE sa.qr_code = ? AND s.school_year = ?
    ) as combined
");
$stmtStats->execute([$qr, $activeSY, $qr, $activeSY]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$totalMarks = $stats['total'] ?: 0;
$presentPercent = $totalMarks > 0 ? round((($stats['present'] + $stats['late']) / $totalMarks) * 100) : 0;

// Student Subject Matrix for Current School Year
// Check if student has explicit enrollment records
$hasEnroll = $pdo->prepare("SELECT 1 FROM student_subjects WHERE qr_code = ? LIMIT 1");
$hasEnroll->execute([$qr]);
if ($hasEnroll->fetch()) {
    // Student has enrolled subjects — use enrollment list
    $stmtSubjects = $pdo->prepare("
        SELECT s.id, s.name, s.code, s.lecturer, s.room, s.semester, s.school_year, s.is_active,
               COUNT(sa.id) as total,
               SUM(CASE WHEN sa.status='present' THEN 1 ELSE 0 END) as present,
               SUM(CASE WHEN sa.status='late' THEN 1 ELSE 0 END) as late,
               SUM(CASE WHEN sa.status='absent' THEN 1 ELSE 0 END) as absent
        FROM student_subjects ss
        JOIN subjects s ON ss.subject_id = s.id
        LEFT JOIN subject_attendance sa ON sa.subject_id = s.id AND sa.qr_code = ?
        WHERE ss.qr_code = ? AND s.school_year = ?
        GROUP BY s.id
        ORDER BY s.semester, s.name
    ");
    $stmtSubjects->execute([$qr, $qr, $activeSY]);
} else {
    // No explicit enrollment — show all subjects for current SY (regular students)
    $stmtSubjects = $pdo->prepare("
        SELECT s.id, s.name, s.code, s.lecturer, s.room, s.semester, s.school_year, s.is_active,
               COUNT(sa.id) as total,
               SUM(CASE WHEN sa.status='present' THEN 1 ELSE 0 END) as present,
               SUM(CASE WHEN sa.status='late' THEN 1 ELSE 0 END) as late,
               SUM(CASE WHEN sa.status='absent' THEN 1 ELSE 0 END) as absent
        FROM subjects s
        LEFT JOIN subject_attendance sa ON sa.subject_id = s.id AND sa.qr_code = ?
        WHERE s.school_year = ? AND s.is_active = 1
        GROUP BY s.id
        ORDER BY s.semester, s.name
    ");
    $stmtSubjects->execute([$qr, $activeSY]);
}
$studentSubjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

// Get schedules for enrolled subjects
$subjectIds = array_column($studentSubjects, 'id');
$schedules = [];
if (!empty($subjectIds)) {
    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $stmtSched = $pdo->prepare("SELECT * FROM schedules WHERE subject_id IN ($placeholders) ORDER BY day_of_week, start_time");
    $stmtSched->execute($subjectIds);
    $schedRows = $stmtSched->fetchAll(PDO::FETCH_ASSOC);
    foreach ($schedRows as $row) {
        $schedules[$row['subject_id']][] = $row;
    }
}

// Get active school year date range
$syStart = $pdo->query("SELECT sy_start_date FROM settings LIMIT 1")->fetchColumn() ?: '';
$syEnd = $pdo->query("SELECT sy_end_date FROM settings LIMIT 1")->fetchColumn() ?: '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['name']) ?> | Profile</title>
    <link href="assets/css/style.css?v=1.3" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        :root {
            --primary-rgb: 59, 130, 246;
            --accent-glow: rgba(var(--primary-rgb), 0.15);
        }

        .profile-container {
            display: grid; 
            grid-template-columns: 340px 1fr; 
            gap: 2.5rem; 
            padding: 2rem 0;
            align-items: start;
        }

        /* Unified Monolith Sidebar */
        .sidebar-monolith {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2.5rem 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 2.25rem;
            position: sticky;
            top: 2rem;
            height: fit-content;
            box-shadow: var(--shadow-neu-out);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .sidebar-monolith:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
        }

        .profile-header { text-align: center; }
        
        .avatar-container {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 1.5rem;
        }

        .avatar-circle {
            width: 100%;
            height: 100%;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.75rem;
            font-weight: 800;
            color: white;
            font-family: 'Outfit', sans-serif;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s;
        }
        .avatar-circle::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 60%);
        }
        
        .attendance-rank-box {
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .attendance-chart {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            position: relative;
            background: var(--bg-main);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-neu-in-sm);
            transition: all 0.3s ease;
        }
        .attendance-chart::after {
            content: attr(data-display);
            width: 76px;
            height: 76px;
            background: var(--bg-card);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            font-family: 'Outfit', sans-serif;
            box-shadow: var(--shadow-neu-out-sm);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 1rem;
        }
        .stat-blk {
            text-align: center;
            padding: 10px 4px;
            background: var(--bg-main);
            border-radius: 14px;
            box-shadow: var(--shadow-neu-in-sm);
            transition: transform 0.2s;
        }
        .stat-blk:hover {
            transform: scale(1.03);
        }
        .stat-blk b { display: block; font-size: 1.2rem; font-weight: 800; letter-spacing: -0.04em; }
        .stat-blk span { font-size: 0.58rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); letter-spacing: 0.08em; }

        /* Dossier Dashboard */
        .dashboard-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .dashboard-title h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin: 0;
            color: var(--text-main);
        }
        .dashboard-title p {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin: 4px 0 0;
        }

        .dossier-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dossier-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }
        .dossier-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-neu-out-lg);
            border-color: rgba(var(--primary-rgb), 0.3);
        }
        
        .dossier-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary);
        }

        .dossier-card-header i {
            font-size: 1.2rem;
            color: var(--primary);
            background: rgba(var(--primary-rgb), 0.08);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dossier-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 1rem;
        }
        .dossier-item:last-child { margin-bottom: 0; }
        
        .dossier-label {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .dossier-value {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-main);
            word-break: break-word;
        }

        .dossier-value.empty-field {
            font-weight: 500;
            font-style: italic;
            color: var(--text-muted);
            opacity: 0.7;
        }

        /* Timeline Section */
        .history-section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-neu-out-sm);
        }

        .history-item {
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 1rem 1.25rem;
            background: var(--bg-main); 
            transition: all 0.25s ease;
            border-radius: 14px; 
            margin-bottom: 0.5rem; 
            box-shadow: var(--shadow-neu-in-sm);
            border: 1px solid transparent;
        }
        .history-item:hover { 
            transform: translateX(4px); 
            background: var(--bg-card);
            border-color: var(--border);
            box-shadow: var(--shadow-neu-out-sm);
        }
        .history-label { font-size: 0.88rem; font-weight: 700; color: var(--text-main); }
        .history-meta { font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; }
        
        .status-badge {
            font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;
            padding: 4px 12px; border-radius: 50px; border: 1px solid var(--border);
        }
        .status-present { color: #10b981; background: rgba(16, 185, 129, 0.08); border-color: rgba(16,185,129,0.2); }
        .status-late { color: #f59e0b; background: rgba(245, 158, 11, 0.08); border-color: rgba(245,158,11,0.2); }
        .status-absent { color: #ef4444; background: rgba(239, 68, 68, 0.08); border-color: rgba(239,68,68,0.2); }

        html.dark .status-present { color: #6ee7b7; background: rgba(16,185,129,0.12); }
        html.dark .status-late { color: #fbbf24; background: rgba(245,158,11,0.12); }
        html.dark .status-absent { color: #fca5a5; background: rgba(239,68,68,0.12); }

        /* ─── Master Progress Section ─── */
        .master-progress-section {
            background: var(--bg-main);
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-neu-in-sm);
        }
        .master-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
        }
        .master-progress-header span {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
        }
        .master-progress-header strong {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--primary);
        }
        .master-bar {
            height: 6px;
            border-radius: 6px;
            background: var(--border);
            overflow: hidden;
            display: flex;
        }
        .master-bar .bar-seg {
            height: 100%;
            transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .master-bar .bar-seg.present { background: #10b981; }
        .master-bar .bar-seg.late { background: #f59e0b; }
        .master-bar .bar-seg.absent { background: #ef4444; }

        /* ─── Filter Chips ─── */
        .matrix-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 1rem;
        }
        .matrix-filter-chips .chip {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 5px 14px;
            border-radius: 50px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.25s ease;
            user-select: none;
        }
        .matrix-filter-chips .chip:hover {
            border-color: rgba(var(--primary-rgb), 0.3);
            color: var(--text-main);
        }
        .matrix-filter-chips .chip.active {
            background: rgba(var(--primary-rgb), 0.1);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* ─── Semester Group Divider ─── */
        .sem-group-divider {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0 0.25rem;
            margin-top: 0.5rem;
            border-top: 2px dashed var(--border);
        }
        .sem-group-divider:first-of-type {
            border-top: none;
            margin-top: 0;
            padding-top: 0;
        }
        .sem-group-divider .sem-badge {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 4px 16px;
            border-radius: 50px;
            background: rgba(var(--primary-rgb), 0.08);
            color: var(--primary);
            border: 1px solid rgba(var(--primary-rgb), 0.15);
            white-space: nowrap;
        }
        .sem-group-divider .sem-line {
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .sem-group-divider .sem-count {
            font-size: 0.6rem;
            font-weight: 700;
            color: var(--text-muted);
            white-space: nowrap;
        }

        /* ─── New Empty State ─── */
        .matrix-empty-new {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem 2rem;
            background: var(--bg-main);
            border-radius: 16px;
            border: 1px dashed var(--border);
            color: var(--text-muted);
        }
        .matrix-empty-new i {
            font-size: 3rem;
            display: block;
            margin-bottom: 0.75rem;
            opacity: 0.3;
        }
        .matrix-empty-new h4 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            margin: 0 0 4px;
            color: var(--text-main);
        }
        .matrix-empty-new p {
            font-size: 0.8rem;
            margin: 0;
        }

        /* ─── Schedule Modal ─── */
        .schedule-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .schedule-modal-overlay.active { display: flex; }
        .schedule-modal-body {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2rem;
            max-width: 720px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }
        .schedule-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .schedule-modal-header h3 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .schedule-week-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .schedule-day-col {
            background: var(--bg-main);
            border-radius: 14px;
            padding: 0.75rem 0.5rem;
            min-height: 120px;
            border: 1px solid var(--border);
        }
        .schedule-day-col .day-label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            text-align: center;
            color: var(--text-muted);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 0.5rem;
        }
        .schedule-day-col .sched-slot {
            font-size: 0.6rem;
            padding: 4px 6px;
            border-radius: 6px;
            background: rgba(var(--primary-rgb), 0.06);
            border: 1px solid rgba(var(--primary-rgb), 0.12);
            margin-bottom: 4px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.3;
        }
        .schedule-day-col .sched-slot .slot-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.55rem;
            color: var(--text-muted);
            font-weight: 500;
            display: block;
        }

        /* ─── Media Thumbnail elegant styling ─── */
        .bday-card-preview {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: var(--bg-main);
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-top: 5px;
        }

        /* Subject Matrix Styles */
        .matrix-dossier {
            grid-column: 1 / -1;
        }

        .matrix-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .matrix-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 4px 12px;
            border-radius: 50px;
            background: rgba(var(--primary-rgb), 0.08);
            color: var(--primary);
            border: 1px solid rgba(var(--primary-rgb), 0.15);
        }

        /* ─── Table Matrix ─── */
        .matrix-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 3px;
        }
        .matrix-table thead th {
            font-size: 0.55rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            padding: 0.3rem 0.6rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .matrix-table tbody tr {
            background: var(--bg-main);
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .matrix-table tbody tr:hover {
            background: var(--bg-card);
        }
        .matrix-table tbody td {
            padding: 0.45rem 0.6rem;
            font-size: 0.72rem;
            vertical-align: middle;
            white-space: nowrap;
        }
        .matrix-table tbody td:first-child {
            border-radius: 8px 0 0 8px;
        }
        .matrix-table tbody td:last-child {
            border-radius: 0 8px 8px 0;
        }

        .matrix-table .td-code {
            width: 60px;
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
        }
        .matrix-table .td-name {
            font-weight: 700;
            color: var(--text-main);
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .matrix-table .td-sched {
            width: 130px;
        }
        .matrix-table .td-sched .mtag {
            display: inline-block;
            font-size: 0.55rem;
            font-weight: 700;
            padding: 1px 6px;
            margin: 0 2px 2px 0;
            border-radius: 4px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-muted);
            font-family: 'JetBrains Mono', monospace;
        }
        .matrix-table .td-stats {
            width: 120px;
        }
        .matrix-table .td-stats .mstat {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: 5px;
            border: 1px solid var(--border);
            margin-right: 3px;
        }
        .matrix-table .td-stats .mstat.p { color: #10b981; border-color: rgba(16,185,129,0.2); background: rgba(16,185,129,0.06); }
        .matrix-table .td-stats .mstat.l { color: #f59e0b; border-color: rgba(245,158,11,0.2); background: rgba(245,158,11,0.06); }
        .matrix-table .td-stats .mstat.a { color: #ef4444; border-color: rgba(239,68,68,0.2); background: rgba(239,68,68,0.06); }
        .matrix-table .td-stats .empty-txt {
            font-size: 0.6rem;
            color: var(--text-muted);
            font-style: italic;
        }
        html.dark .matrix-table .td-stats .mstat.p { color: #6ee7b7; background: rgba(16,185,129,0.1); }
        html.dark .matrix-table .td-stats .mstat.l { color: #fbbf24; background: rgba(245,158,11,0.1); }
        html.dark .matrix-table .td-stats .mstat.a { color: #fca5a5; background: rgba(239,68,68,0.1); }

        .matrix-table .td-bar {
            width: 80px;
        }
        .matrix-table .td-bar .mbar {
            height: 5px;
            border-radius: 5px;
            background: var(--border);
            overflow: hidden;
            display: flex;
        }
        .matrix-table .td-bar .mbar .seg { height: 100%; transition: width 0.6s ease; }
        .matrix-table .td-bar .mbar .seg.p { background: #10b981; }
        .matrix-table .td-bar .mbar .seg.l { background: #f59e0b; }
        .matrix-table .td-bar .mbar .seg.a { background: #ef4444; }

        .matrix-table .td-lecturer {
            width: 80px;
            color: var(--text-muted);
            font-size: 0.65rem;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .matrix-table .td-lecturer i { font-size: 0.5rem; margin-right: 2px; }

        .matrix-table .td-status {
            width: 70px;
            text-align: center;
        }
        .matrix-table .td-status .mbadge {
            display: inline-block;
            font-size: 0.5rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 2px 10px;
            border-radius: 50px;
        }
        .matrix-table .td-status .mbadge.excellent { color: #10b981; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); }
        .matrix-table .td-status .mbadge.good { color: #3b82f6; background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.2); }
        .matrix-table .td-status .mbadge.at-risk { color: #ef4444; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); }
        .matrix-table .td-status .mbadge.no-data { color: var(--text-muted); background: var(--bg-main); border: 1px solid var(--border); }
        html.dark .matrix-table .td-status .mbadge.excellent { color: #6ee7b7; background: rgba(16,185,129,0.12); }
        html.dark .matrix-table .td-status .mbadge.good { color: #93c5fd; background: rgba(59,130,246,0.12); }
        html.dark .matrix-table .td-status .mbadge.at-risk { color: #fca5a5; background: rgba(239,68,68,0.12); }

        .matrix-table tbody tr.enter {
            opacity: 0;
            transform: translateY(6px);
            animation: rowIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes rowIn {
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 992px) {
            .matrix-table .td-sched { width: 100px; }
            .matrix-table .td-name { max-width: 100px; }
        }
        @media (max-width: 700px) {
            .matrix-table thead { display: none; }
            .matrix-table tbody td { display: block; white-space: normal; padding: 0.2rem 0.6rem; }
            .matrix-table tbody td:first-child { padding-top: 0.5rem; }
            .matrix-table tbody td:last-child { padding-bottom: 0.5rem; }
            .matrix-table tbody tr {
                display: block;
                background: var(--bg-main);
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 0.3rem 0.5rem;
                margin-bottom: 4px;
            }
            .matrix-table .td-code { width: auto; display: inline; }
            .matrix-table .td-name { width: auto; max-width: none; display: inline; }
            .matrix-table .td-sched { width: auto; }
            .matrix-table .td-stats { width: auto; }
            .matrix-table .td-bar { width: auto; }
            .matrix-table .td-lecturer { width: auto; }
            .matrix-table .td-status { width: auto; text-align: left; }
        }

        .sem-group-label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* ─── Sidebar Profile Classes (replace inline styles) ─── */
        .profile-name {
            font-weight: 800;
            letter-spacing: -0.04em;
            margin: 0 0 0.35rem;
            font-size: 1.45rem;
            font-family: 'Outfit', sans-serif;
            color: var(--text-main);
        }
        .profile-qr {
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-muted);
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            margin: 0 0 1.25rem;
        }
        .profile-badges {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .profile-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .profile-actions .btn {
            justify-content: center;
            padding: 0.65rem;
            font-size: 0.8rem;
            border-radius: 50px;
            font-weight: 700;
        }

        /* ─── Dashboard Header Link ─── */
        .btn-history-report {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-history-report:hover {
            background: rgba(var(--primary-rgb), 0.06);
            border-color: var(--primary);
        }

        /* ─── History Empty State ─── */
        .history-empty {
            padding: 4rem;
            text-align: center;
            color: var(--text-muted);
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            font-style: italic;
        }

        /* ─── Form Section (Edit Modal) ─── */
        .form-section {
            background: var(--bg-main);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--border);
        }
        .form-section:last-child {
            margin-bottom: 0;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1rem;
            font-weight: 800;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary);
            font-family: 'Outfit', sans-serif;
        }
        .section-header i {
            font-size: 1rem;
            color: var(--primary);
            background: rgba(var(--primary-rgb), 0.1);
            width: 28px;
            height: 28px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .field-group label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
        }
        .field-group label .req {
            color: #ef4444;
        }
        .field-group .form-control {
            width: 100%;
            font-size: 0.8rem;
        }
        .bday-upload-area {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        .bday-preview-box {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--bg-main);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .bday-preview-box i {
            font-size: 2rem;
            color: var(--text-muted);
            opacity: 0.3;
        }

        /* ─── Modal Close ─── */
        .modal-close-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .modal-close-btn:hover {
            background: var(--bg-hover);
            color: var(--text-main);
        }
        .btn-discard {
            background: var(--bg-hover);
            color: var(--text-muted);
            border: 1px solid var(--border);
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-discard:hover {
            background: var(--border);
        }
        .btn-save {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-save:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* ─── Timeline Scroll List ─── */
        .scroll-list-container {
            background: var(--bg-main);
            border-radius: 20px;
            padding: 0.25rem;
            position: relative;
        }

        /* ─── Schedule Modal Header ─── */
        .schedule-modal-header h3 i {
            color: var(--primary);
            margin-right: 8px;
        }

        /* ─── Empty day in schedule ─── */
        .schedule-day-col .empty-day {
            font-size: 0.55rem;
            color: var(--text-muted);
            text-align: center;
            padding: 0.5rem 0;
        }

        @media (max-width: 992px) {
            .profile-container { grid-template-columns: 1fr; gap: 1.5rem; padding: 1.5rem 0; }
            .sidebar-monolith { position: static; padding: 1.75rem 1.25rem; gap: 1.75rem; }
            .dashboard-title h2 { font-size: 1.4rem; }
            .dashboard-header { flex-direction: column; align-items: flex-start; }
            .schedule-week-grid { grid-template-columns: repeat(4, 1fr); }
        }

        @media (max-width: 768px) {
            .profile-container { padding: 1rem 0; gap: 1.25rem; }
            .sidebar-monolith { padding: 1.5rem 1rem; border-radius: 20px; gap: 1.5rem; }
            .sidebar-monolith:hover { transform: none; }
            .profile-name { font-size: 1.2rem; }
            .dashboard-title h2 { font-size: 1.2rem; }
            .dashboard-title p { font-size: 0.8rem; }
            .avatar-container { width: 90px; height: 90px; }
            .avatar-circle { font-size: 2.2rem; border-radius: 20px; }
            .attendance-chart { width: 80px; height: 80px; }
            .attendance-chart::after { width: 60px; height: 60px; font-size: 0.95rem; }
            .stats-grid { gap: 6px; }
            .stat-blk { padding: 8px 2px; }
            .stat-blk:hover { transform: none; }
            .stat-blk b { font-size: 1rem; }
            .dossier-grid { grid-template-columns: 1fr; gap: 1rem; }
            .dossier-card { padding: 1.1rem; }
            .dossier-card:hover { transform: none; }
            .dossier-card-header { font-size: 0.85rem; }
            .dossier-value { font-size: 0.8rem; }
            .field-grid { grid-template-columns: 1fr; }
            .field-grid .full-width { grid-column: span 1; }
            .modal-body { max-height: 95vh; }
            .modal-scroll-area { padding: 1.25rem; }
            .modal-footer-pro { padding: 1rem 1.25rem; }
            .schedule-week-grid { grid-template-columns: repeat(3, 1fr); gap: 6px; }
            .schedule-modal-body { padding: 1.25rem; }
            .history-section { padding: 1.25rem; }
            .history-item { padding: 0.75rem 1rem; flex-wrap: wrap; gap: 0.5rem; }
            .history-item:hover { transform: none; }
            .history-label { font-size: 0.78rem; }
            .matrix-filter-chips .chip { font-size: 0.6rem; padding: 4px 10px; }
            .master-progress-section { padding: 0.75rem 1rem; }
            .form-section { padding: 1rem; }
            .bday-upload-area { flex-direction: column; align-items: stretch; }
            .bday-preview-box { width: 100%; height: 100px; }
            .profile-actions { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .profile-container { padding: 0.5rem 0; gap: 1rem; }
            .sidebar-monolith { padding: 1.25rem 0.85rem; border-radius: 16px; gap: 1.25rem; }
            .profile-name { font-size: 1.15rem; }
            .profile-qr { font-size: 0.68rem; }
            .dashboard-title h2 { font-size: 1.05rem; }
            .avatar-container { width: 76px; height: 76px; }
            .avatar-circle { font-size: 1.8rem; border-radius: 16px; }
            .attendance-chart { width: 70px; height: 70px; }
            .attendance-chart::after { width: 54px; height: 54px; font-size: 0.8rem; }
            .dossier-card { padding: 0.9rem; border-radius: 16px; }
            .dossier-card-header { font-size: 0.78rem; gap: 0.5rem; }
            .dossier-card-header i { width: 26px; height: 26px; font-size: 0.9rem; }
            .dossier-label { font-size: 0.58rem; }
            .dossier-value { font-size: 0.76rem; }
            .history-section { padding: 1rem; border-radius: 18px; }
            .history-item { padding: 0.6rem 0.75rem; flex-direction: column; align-items: flex-start; gap: 0.25rem; }
            .history-label { font-size: 0.72rem; }
            .history-meta { font-size: 0.6rem; }
            .schedule-week-grid { grid-template-columns: repeat(2, 1fr); }
            .schedule-modal-body { padding: 1rem; border-radius: 18px; }
            .matrix-filter-chips { gap: 4px; }
            .matrix-filter-chips .chip { font-size: 0.55rem; padding: 3px 8px; }
            .matrix-table tbody td { font-size: 0.68rem; padding: 0.15rem 0.5rem; }
            .sem-group-divider .sem-badge { font-size: 0.6rem; padding: 3px 10px; }
            .sem-group-divider .sem-count { font-size: 0.55rem; }
            .master-progress-header strong { font-size: 0.75rem; }
            .form-section { padding: 0.85rem; }
            .section-header { font-size: 0.68rem; }
            .btn-history-report { font-size: 0.7rem; padding: 0.4rem 0.75rem; }
            .btn-discard, .btn-save { font-size: 0.7rem; padding: 0.5rem 1rem; }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container">
        <div class="profile-container animate-fade-up">
            
            <!-- Profile Column (Sidebar) -->
            <aside class="sidebar-monolith">
                <div class="profile-header">
                    <?php
                        $profileInitial = strtoupper(substr($user['last_name'] ?? $user['name'] ?? '?', 0, 1));
                        $profileColors = ['#5c6bc0','#42a5f5','#26a69a','#66bb6a','#ec407a','#ab47bc','#ef5350','#ffa726'];
                        $profileColor = $profileColors[ord($profileInitial) % count($profileColors)];
                    ?>
                    <div class="avatar-container">
                        <div class="avatar-circle" style="background: <?= $profileColor ?>;"><?= $profileInitial ?></div>
                    </div>
                    <h2 class="profile-name"><?= htmlspecialchars($user['name']) ?></h2>
                    <p class="profile-qr"><?= htmlspecialchars($user['qr_code']) ?></p>
                    
                    <div class="profile-badges">
                        <span class="badge" style="background: rgba(59, 130, 246, 0.08); color: var(--primary); border: 1px solid rgba(59, 130, 246, 0.15);"><?= htmlspecialchars(ucfirst($user['student_type'] ?? 'Regular')) ?></span>
                        <span class="badge" style="background: var(--bg-hover); color: var(--text-muted); border: 1px solid var(--border);"><?= htmlspecialchars($user['year_level'] ?? '1st') ?> Year</span>
                    </div>

                    <div class="profile-actions">
                        <button onclick="downloadQR()" class="btn btn-primary">Save QR</button>
                        <button onclick="openEditModal()" class="btn btn-ghost" style="justify-content: center; padding: 0.65rem; font-size: 0.8rem; border-radius: 50px; border: 1px solid var(--border); font-weight: 700;">Edit Profile</button>
                    </div>
                </div>

                <div class="attendance-rank-box">
                    <h5 style="text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.65rem; color: var(--text-muted); font-weight: 800; margin-bottom: 1.25rem;">Attendance Performance</h5>
                    <div class="attendance-chart" data-percent="<?= $presentPercent ?>" data-display="0%"></div>
                    <div class="stats-grid">
                        <div class="stat-blk">
                            <b class="profile-counter" data-target="<?= $stats['present'] ?: 0 ?>">0</b>
                            <span>Present</span>
                        </div>
                        <div class="stat-blk">
                            <b class="profile-counter" data-target="<?= $stats['late'] ?: 0 ?>">0</b>
                            <span>Late</span>
                        </div>
                        <div class="stat-blk">
                            <b class="profile-counter" data-target="<?= $stats['absent'] ?: 0 ?>">0</b>
                            <span>Absent</span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Right Content Dossier -->
            <section>
                <div class="dashboard-header">
                    <div class="dashboard-title">
                        <h2>Student Dossier</h2>
                        <p>Detailed verification and profiling record parameters</p>
                    </div>
                    <a href="student_history.php?qr_code=<?= urlencode($qr) ?>" class="btn-history-report"><i class="bi bi-clock-history"></i> View History Report</a>
                </div>

                <!-- Comprehensive Information Grid -->
                <div class="dossier-grid">
                    
                    <!-- Card 1: Academic Profile -->
                    <div class="dossier-card">
                        <div class="dossier-card-header">
                            <i class="bi bi-mortarboard"></i>
                            <span>Academic Info</span>
                        </div>
                        
                        <div class="dossier-item">
                            <div class="dossier-label">Learner Reference Number (LRN)</div>
                            <div class="dossier-value <?= empty($user['lrn']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['lrn'] ?? 'Not Recorded') ?></div>
                        </div>
                        
                        <div class="dossier-item">
                            <div class="dossier-label">Course & Strand</div>
                            <div class="dossier-value <?= empty($user['course']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['course'] ?? 'Not Recorded') ?></div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Section & Set</div>
                            <div class="dossier-value <?= empty($user['section']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['section'] ?? 'Not Recorded') ?></div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Year Level & Class Standing</div>
                            <div class="dossier-value"><?= htmlspecialchars($user['year_level'] ?? '1st') ?> Year (<?= htmlspecialchars(ucfirst($user['student_type'] ?? 'Regular')) ?>)</div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Student ID/QR Reference</div>
                            <div class="dossier-value" style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: var(--primary);"><?= htmlspecialchars($user['qr_code']) ?></div>
                        </div>
                    </div>

                    <!-- Card 2: Personal Demographics -->
                    <div class="dossier-card">
                        <div class="dossier-card-header">
                            <i class="bi bi-person-badge"></i>
                            <span>Personal Details</span>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Sex & Gender</div>
                            <div class="dossier-value <?= empty($user['sex']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['sex'] ?? 'Not Specified') ?></div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Birthday & Age</div>
                            <div class="dossier-value <?= empty($user['birthday']) ? 'empty-field' : '' ?>">
                                <?php 
                                    if(!empty($user['birthday'])) {
                                        $bday = new DateTime($user['birthday']);
                                        $age = $bday->diff(new DateTime('now'))->y;
                                        echo htmlspecialchars(date('F j, Y', strtotime($user['birthday']))) . " <b>(" . $age . " yrs old)</b>";
                                    } else {
                                        echo 'Not Recorded';
                                    }
                                ?>
                            </div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Blood Type</div>
                            <div class="dossier-value <?= empty($user['blood_type']) ? 'empty-field' : '' ?>" style="color: var(--danger); font-weight: 800;"><?= htmlspecialchars($user['blood_type'] ?? 'Not Specified') ?></div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Birth Place</div>
                            <div class="dossier-value <?= empty($user['place_of_birth']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['place_of_birth'] ?? 'Not Recorded') ?></div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Civil Status & Citizenship</div>
                            <div class="dossier-value">
                                <span class="<?= empty($user['civil_status']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['civil_status'] ?? 'Single') ?></span> / 
                                <span class="<?= empty($user['citizenship']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['citizenship'] ?? 'Filipino') ?></span>
                            </div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Religion</div>
                            <div class="dossier-value <?= empty($user['religion']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['religion'] ?? 'Not Specified') ?></div>
                        </div>
                    </div>

                    <!-- Card 3: Contact Channels -->
                    <div class="dossier-card">
                        <div class="dossier-card-header">
                            <i class="bi bi-telephone"></i>
                            <span>Contact & Address</span>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Personal Email Address</div>
                            <div class="dossier-value <?= empty($user['email']) ? 'empty-field' : '' ?>" style="color: var(--primary); text-decoration: underline;"><?= htmlspecialchars($user['email'] ?? 'Not Recorded') ?></div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Mobile Contact Number</div>
                            <div class="dossier-value <?= empty($user['contact_number']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['contact_number'] ?? 'Not Recorded') ?></div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Home Address</div>
                            <div class="dossier-value <?= empty($user['home_address']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['home_address'] ?? 'Not Recorded') ?></div>
                        </div>
                    </div>

                    <!-- Card 4: Family & Emergency Contact -->
                    <div class="dossier-card">
                        <div class="dossier-card-header">
                            <i class="bi bi-shield-check"></i>
                            <span>Family & Emergency</span>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Mother's Full Name</div>
                            <div class="dossier-value <?= empty($user['mother_name']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['mother_name'] ?? 'Not Recorded') ?></div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Father's Full Name</div>
                            <div class="dossier-value <?= empty($user['father_name']) ? 'empty-field' : '' ?>"><?= htmlspecialchars($user['father_name'] ?? 'Not Recorded') ?></div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Emergency Contact Person</div>
                            <div class="dossier-value <?= empty($user['guardian_name']) ? 'empty-field' : '' ?>">
                                <?= htmlspecialchars($user['guardian_name'] ?? 'Not Recorded') ?>
                                <?php if (!empty($user['guardian_relationship'])): ?>
                                    <small style="color: var(--text-muted); font-weight: 500;">(<?= htmlspecialchars($user['guardian_relationship']) ?>)</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="dossier-item">
                            <div class="dossier-label">Emergency Contact Number</div>
                            <div class="dossier-value <?= empty($user['guardian_contact']) ? 'empty-field' : '' ?>" style="color: var(--danger); font-weight: bold;"><?= htmlspecialchars($user['guardian_contact'] ?? 'Not Recorded') ?></div>
                        </div>
                    </div>

                    <?php if(!empty($user['birthday_image'])): ?>
                        <!-- Birthday Media Card -->
                        <div class="dossier-card" style="grid-column: span 1;">
                            <div class="dossier-card-header">
                                <i class="bi bi-gift"></i>
                                <span>Birthday Media</span>
                            </div>
                            <div class="bday-card-preview">
                                <img src="<?= htmlspecialchars($user['birthday_image']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);">
                                <div>
                                    <small style="display: block; font-weight: 800; font-size: 0.6rem; color: var(--text-muted); text-transform: uppercase;">Active Theme Thumbnail</small>
                                    <a href="<?= htmlspecialchars($user['birthday_image']) ?>" target="_blank" style="font-size: 0.75rem; color: var(--primary); font-weight: 700; text-decoration: none;">View Original Image</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Card 6: Subject Matrix -->
                    <div class="dossier-card matrix-dossier">
                        <div class="dossier-card-header">
                            <i class="bi bi-grid-3x3-gap"></i>
                            <span>Subject Matrix</span>
                            <span class="matrix-badge" style="margin-left: auto;"><i class="bi bi-calendar3"></i> <?= htmlspecialchars($activeSY) ?></span>
                        </div>

                        <div class="matrix-header-bar">
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <span style="font-size:0.75rem; color:var(--text-muted);">
                                    <i class="bi bi-book"></i> <?= count($studentSubjects) ?> enrolled subject(s)
                                </span>
                                <?php if (!empty($syStart)): ?>
                                    <span class="sem-group-label"><i class="bi bi-calendar-week"></i> <?= htmlspecialchars(date('M j', strtotime($syStart))) ?> - <?= htmlspecialchars(date('M j, Y', strtotime($syEnd ?: $syStart))) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($studentSubjects)): ?>
                                <button onclick="openScheduleModal()" class="btn btn-ghost" style="padding:4px 10px; font-size:0.7rem; border-radius:8px; border:1px solid var(--border);" title="View weekly schedule"><i class="bi bi-calendar-week"></i></button>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($studentSubjects)): ?>
                            <div class="matrix-empty-new">
                                <i class="bi bi-calendar-plus"></i>
                                <h4>No Subjects Enrolled Yet</h4>
                                <p>No subject assignments found for <strong><?= htmlspecialchars($activeSY) ?></strong>.<br>Classes for this school year may not have started, or the student hasn't been enrolled.</p>
                            </div>
                        <?php else: 
                            $masterPresent = array_sum(array_column($studentSubjects, 'present'));
                            $masterLate = array_sum(array_column($studentSubjects, 'late'));
                            $masterAbsent = array_sum(array_column($studentSubjects, 'absent'));
                            $masterTotal = $masterPresent + $masterLate + $masterAbsent;
                            $mpW = $masterTotal > 0 ? round($masterPresent / $masterTotal * 100) : 0;
                            $mlW = $masterTotal > 0 ? round($masterLate / $masterTotal * 100) : 0;
                            $maW = $masterTotal > 0 ? round($masterAbsent / $masterTotal * 100) : 0;
                        ?>
                            <?php if ($masterTotal > 0): ?>
                            <div class="master-progress-section">
                                <div class="master-progress-header">
                                    <span><i class="bi bi-bar-chart"></i> Overall Attendance</span>
                                    <strong><?= $masterPresent + $masterLate ?>/<?= $masterTotal ?> (<?= $masterTotal > 0 ? round(($masterPresent + $masterLate) / $masterTotal * 100) : 0 ?>%)</strong>
                                </div>
                                <div class="master-bar">
                                    <div class="bar-seg present" style="width:<?= $mpW ?>%"></div>
                                    <div class="bar-seg late" style="width:<?= $mlW ?>%"></div>
                                    <div class="bar-seg absent" style="width:<?= $maW ?>%"></div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="matrix-filter-chips">
                                <span class="chip active" data-filter="all" onclick="filterMatrix('all', this)">All</span>
                                <span class="chip" data-filter="1" onclick="filterMatrix('1', this)">1st Sem</span>
                                <span class="chip" data-filter="2" onclick="filterMatrix('2', this)">2nd Sem</span>
                                <span class="chip" data-filter="excellent" onclick="filterMatrix('excellent', this)">Excellent</span>
                                <span class="chip" data-filter="good" onclick="filterMatrix('good', this)">Good</span>
                                <span class="chip" data-filter="at-risk" onclick="filterMatrix('at-risk', this)">At Risk</span>
                            </div>

                            <table class="matrix-table" id="matrixTable">
                                <thead>
                                    <tr>
                                        <th class="td-code">Code</th>
                                        <th class="td-name">Subject</th>
                                        <th class="td-sched">Schedule</th>
                                        <th class="td-stats">Stats</th>
                                        <th class="td-bar">Trend</th>
                                        <th class="td-lecturer">Lecturer</th>
                                        <th class="td-status">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php 
                                $currentSem = '';
                                $rowIndex = 0;
                                foreach ($studentSubjects as $subj): 
                                    $subjTotal = $subj['total'] ?: 0;
                                    $subjPresent = $subj['present'] ?: 0;
                                    $subjLate = $subj['late'] ?: 0;
                                    $subjAbsent = $subj['absent'] ?: 0;
                                    $subjSched = $schedules[$subj['id']] ?? [];
                                    $subjPercent = $subjTotal > 0 ? round(($subjPresent + $subjLate) / $subjTotal * 100) : 0;
                                    $pW = $subjTotal > 0 ? round($subjPresent / $subjTotal * 100) : 0;
                                    $lW = $subjTotal > 0 ? round($subjLate / $subjTotal * 100) : 0;
                                    $aW = $subjTotal > 0 ? round($subjAbsent / $subjTotal * 100) : 0;

                                    if ($subjPercent >= 90) { $statusClass = 'excellent'; $statusLabel = 'Excellent'; }
                                    elseif ($subjPercent >= 75) { $statusClass = 'good'; $statusLabel = 'Good'; }
                                    elseif ($subjTotal > 0) { $statusClass = 'at-risk'; $statusLabel = 'At Risk'; }
                                    else { $statusClass = 'no-data'; $statusLabel = 'No Data'; }

                                    if ($currentSem !== $subj['semester']):
                                        $currentSem = $subj['semester'];
                                        $semCount = count(array_filter($studentSubjects, fn($s) => $s['semester'] === $currentSem));
                                ?>
                                    <tr class="sem-divider-row"><td colspan="7" style="padding:0">
                                        <div class="sem-group-divider" data-sem="<?= htmlspecialchars($currentSem) ?>">
                                            <span class="sem-badge"><?= htmlspecialchars($currentSem) ?></span>
                                            <span class="sem-line"></span>
                                            <span class="sem-count"><?= $semCount ?> subject(s)</span>
                                        </div>
                                    </td></tr>
                                <?php endif; ?>
                                    <tr class="enter" data-sem="<?= htmlspecialchars($subj['semester']) ?>" data-status="<?= $statusClass ?>" style="animation-delay: <?= $rowIndex * 0.04 ?>s">
                                        <td class="td-code"><?= htmlspecialchars($subj['code'] ?: 'SUBJ') ?></td>
                                        <td class="td-name" title="<?= htmlspecialchars($subj['name']) ?>"><?= htmlspecialchars($subj['name']) ?></td>

                                        <td class="td-sched">
                                            <?php if (!empty($subjSched)): ?>
                                                <?php foreach (array_slice($subjSched, 0, 2) as $sch): ?>
                                                    <span class="mtag"><?= htmlspecialchars(substr($sch['day_of_week'], 0, 3)) ?> <?= htmlspecialchars(date('h:i A', strtotime($sch['start_time']))) ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>

                                        <td class="td-stats">
                                            <?php if ($subjTotal > 0): ?>
                                                <span class="mstat p">P<?= $subjPresent ?></span>
                                                <span class="mstat l">L<?= $subjLate ?></span>
                                                <span class="mstat a">A<?= $subjAbsent ?></span>
                                            <?php else: ?>
                                                <span class="empty-txt">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="td-bar">
                                            <div class="mbar">
                                                <span class="seg p" style="width:<?= $pW ?>%"></span>
                                                <span class="seg l" style="width:<?= $lW ?>%"></span>
                                                <span class="seg a" style="width:<?= $aW ?>%"></span>
                                            </div>
                                        </td>

                                        <td class="td-lecturer" title="<?= htmlspecialchars($subj['lecturer']) ?>"><?php if (!empty($subj['lecturer'])): ?><i class="bi bi-person"></i><?= htmlspecialchars(explode(',', $subj['lecturer'])[0]) ?><?php endif; ?></td>

                                        <td class="td-status"><span class="mbadge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    </tr>
                                <?php 
                                    $rowIndex++;
                                    endforeach; 
                                ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Recent Activities Timeline -->
                <div class="history-section">
                    <h4 style="font-weight: 800; letter-spacing: -0.02em; margin: 0 0 4px; font-family: 'Outfit', sans-serif;">Attendance Timeline</h4>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 1.5rem;">Last 10 logged school activities and subjects</p>

                    <div class="scroll-list-container">
                        <div class="top-gradient"></div>
                        <div class="bottom-gradient"></div>
                        <div class="scroll-list no-scrollbar history-rows" style="max-height: 40vh; overflow-y: auto;">
                            <?php if(empty($history)): ?>
                                <div style="padding: 4rem; text-align: center; color: var(--text-muted); background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); font-style: italic;">
                                    No logged student logs or checkpoints detected yet.
                                </div>
                            <?php else: ?>
                                <?php foreach($history as $h): ?>
                                    <div class="history-item animated-item">
                                        <div>
                                            <div class="history-label"><?= htmlspecialchars($h['subject_name']) ?></div>
                                            <div class="history-meta"><?= date('M j, Y', strtotime($h['date'] ?? '')) ?> • <?= date('h:i A', strtotime($h['time'] ?? '')) ?></div>
                                        </div>
                                        <span class="status-badge status-<?= $h['status'] ?>">
                                            <?= $h['status'] ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </section>

        </div>
    </main>

    <!-- Redesigned Comprehensive Edit Modal -->
    <div id="editModal" class="modal-overlay" onclick="if(event.target == this) closeEditModal()">
        <div class="modal-body">
            <div class="modal-header-pro">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="bi bi-pencil-square" style="font-size: 1.25rem; color: var(--primary);"></i>
                    <h3>Edit Student Dossier</h3>
                </div>
                <button onclick="closeEditModal()" class="modal-close-btn" title="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">

                <div class="modal-scroll-area">

                    <!-- Section: Academic Details -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="bi bi-mortarboard"></i>
                            <span>Academic Profile</span>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <label>Learner Reference Number (LRN)</label>
                                <input type="text" name="lrn" class="form-control" value="<?= htmlspecialchars($user['lrn'] ?? '') ?>" placeholder="e.g. 102938475612">
                            </div>
                            <div class="field-group">
                                <label>Course / Strand</label>
                                <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($user['course'] ?? '') ?>" placeholder="e.g. BSCS">
                            </div>
                            <div class="field-group">
                                <label>Section / Set</label>
                                <input type="text" name="section" class="form-control" value="<?= htmlspecialchars($user['section'] ?? '') ?>" placeholder="e.g. 2-A">
                            </div>
                            <div class="field-group">
                                <label>Year Level</label>
                                <select name="year_level" class="form-control">
                                    <option value="1st" <?= ($user['year_level'] ?? '') === '1st' ? 'selected' : '' ?>>1st Year</option>
                                    <option value="2nd" <?= ($user['year_level'] ?? '') === '2nd' ? 'selected' : '' ?>>2nd Year</option>
                                    <option value="3rd" <?= ($user['year_level'] ?? '') === '3rd' ? 'selected' : '' ?>>3rd Year</option>
                                    <option value="4th" <?= ($user['year_level'] ?? '') === '4th' ? 'selected' : '' ?>>4th Year</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>Student Type</label>
                                <select name="student_type" class="form-control">
                                    <option value="regular" <?= ($user['student_type'] ?? '') === 'regular' ? 'selected' : '' ?>>Regular</option>
                                    <option value="irregular" <?= ($user['student_type'] ?? '') === 'irregular' ? 'selected' : '' ?>>Irregular</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Personal Information -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="bi bi-person"></i>
                            <span>Personal Details</span>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <label>First Name <span class="req">*</span></label>
                                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="field-group">
                                <label>Last Name <span class="req">*</span></label>
                                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                            </div>
                            <div class="field-group">
                                <label>Middle Initial</label>
                                <input type="text" name="middle_initial" class="form-control" value="<?= htmlspecialchars($user['middle_initial'] ?? '') ?>" maxlength="2">
                            </div>
                            <div class="field-group">
                                <label>Sex</label>
                                <select name="sex" class="form-control">
                                    <option value="" disabled <?= empty($user['sex']) ? 'selected' : '' ?>>Select...</option>
                                    <option value="Male" <?= (trim($user['sex'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= (trim($user['sex'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <?php $p_bday = !empty($user['birthday']) ? date('Y-m-d', strtotime($user['birthday'])) : ''; ?>
                                <label>Birthday</label>
                                <input type="date" name="birthday" class="form-control" value="<?= htmlspecialchars($p_bday) ?>">
                            </div>
                            <div class="field-group">
                                <label>Blood Type</label>
                                <select name="blood_type" class="form-control">
                                    <option value="" <?= empty($user['blood_type']) ? 'selected' : '' ?>>Select...</option>
                                    <option value="A+" <?= ($user['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                                    <option value="A-" <?= ($user['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                                    <option value="B+" <?= ($user['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                                    <option value="B-" <?= ($user['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                                    <option value="AB+" <?= ($user['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                    <option value="AB-" <?= ($user['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                    <option value="O+" <?= ($user['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                                    <option value="O-" <?= ($user['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>Civil Status</label>
                                <input type="text" name="civil_status" class="form-control" value="<?= htmlspecialchars($user['civil_status'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>Religion</label>
                                <input type="text" name="religion" class="form-control" value="<?= htmlspecialchars($user['religion'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>Citizenship</label>
                                <input type="text" name="citizenship" class="form-control" value="<?= htmlspecialchars($user['citizenship'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>Place of Birth</label>
                                <input type="text" name="place_of_birth" class="form-control" value="<?= htmlspecialchars($user['place_of_birth'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Contact Details -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="bi bi-envelope"></i>
                            <span>Contact Channels</span>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                            </div>
                            <div class="field-group">
                                <label>Mobile Contact</label>
                                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
                            </div>
                            <div class="field-group full-width">
                                <label>Home Address</label>
                                <input type="text" name="home_address" class="form-control" value="<?= htmlspecialchars($user['home_address'] ?? '') ?>" placeholder="e.g. 123 Street, City, Province">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Parents & Emergency Contacts -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="bi bi-shield-check"></i>
                            <span>Parents & Emergency Info</span>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <label>Father's Full Name</label>
                                <input type="text" name="father_name" class="form-control" value="<?= htmlspecialchars($user['father_name'] ?? '') ?>" placeholder="Father's name">
                            </div>
                            <div class="field-group">
                                <label>Mother's Full Name</label>
                                <input type="text" name="mother_name" class="form-control" value="<?= htmlspecialchars($user['mother_name'] ?? '') ?>" placeholder="Mother's name">
                            </div>
                            <div class="field-group">
                                <label>Guardian Name / Emergency Contact</label>
                                <input type="text" name="guardian_name" class="form-control" value="<?= htmlspecialchars($user['guardian_name'] ?? '') ?>" placeholder="Emergency contact person">
                            </div>
                            <div class="field-group">
                                <label>Relationship to Student</label>
                                <input type="text" name="guardian_relationship" class="form-control" value="<?= htmlspecialchars($user['guardian_relationship'] ?? '') ?>" placeholder="e.g. Mother, Uncle, Landlord">
                            </div>
                            <div class="field-group full-width">
                                <label>Emergency Contact Number</label>
                                <input type="text" name="guardian_contact" class="form-control" value="<?= htmlspecialchars($user['guardian_contact'] ?? '') ?>" placeholder="Emergency phone/mobile number">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Media & Birthdays -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="bi bi-image"></i>
                            <span>Birthday Greetings Thumbnail</span>
                        </div>
                        <div class="bday-upload-area">
                            <div id="p-bday-preview" class="bday-preview-box">
                                <?php if(!empty($user['birthday_image'])): ?>
                                    <img src="<?= htmlspecialchars($user['birthday_image']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="bi bi-image"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1; display: flex; flex-direction: column; gap: 0.5rem;">
                                <input type="text" name="birthday_image" id="p-bday-img" class="form-control" style="font-size: 0.8rem;" value="<?= htmlspecialchars($user['birthday_image'] ?? '') ?>" placeholder="Paste image URL or upload file...">
                                <input type="file" id="p-bday-upload" class="form-control" style="font-size: 0.75rem;" accept="image/*">
                            </div>
                        </div>
                        <small style="color: var(--text-muted); font-size: 0.7rem; margin-top: 0.5rem; display: block;">Optional image that highlights the student profile on their birthday logs.</small>
                    </div>
                </div>

                <div class="modal-footer-pro">
                    <button type="button" onclick="closeEditModal()" class="btn-discard">Discard Changes</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="schedule-modal-overlay" onclick="if(event.target==this) closeScheduleModal()">
        <div class="schedule-modal-body">
            <div class="schedule-modal-header">
                <h3><i class="bi bi-calendar-week"></i>Weekly Schedule</h3>
                <button onclick="closeScheduleModal()" class="modal-close-btn"><i class="bi bi-x-lg"></i></button>
            </div>
            <p style="font-size:0.78rem;color:var(--text-muted);margin:0 0 1.25rem;"><?= htmlspecialchars($user['name']) ?> &middot; <?= htmlspecialchars($activeSY) ?></p>
            <?php
            $daysOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            $allScheds = [];
            foreach ($studentSubjects as $subj) {
                $subjSched = $schedules[$subj['id']] ?? [];
                foreach ($subjSched as $sch) {
                    $sch['subject_name'] = $subj['name'];
                    $sch['subject_code'] = $subj['code'];
                    $allScheds[] = $sch;
                }
            }
            usort($allScheds, fn($a, $b) => array_search($a['day_of_week'], $daysOrder) <=> array_search($b['day_of_week'], $daysOrder)
                ?: strcmp($a['start_time'], $b['start_time']));
            $groupedByDay = [];
            foreach ($allScheds as $s) {
                $groupedByDay[$s['day_of_week']][] = $s;
            }
            ?>
            <div class="schedule-week-grid">
                <?php foreach ($daysOrder as $day):
                    $dayScheds = $groupedByDay[$day] ?? [];
                ?>
                <div class="schedule-day-col">
                    <div class="day-label"><?= htmlspecialchars(substr($day, 0, 3)) ?></div>
                    <?php if (empty($dayScheds)): ?>
                        <div style="font-size:0.55rem;color:var(--text-muted);text-align:center;padding:0.5rem 0;">—</div>
                    <?php else: ?>
                        <?php foreach ($dayScheds as $s): ?>
                        <div class="sched-slot">
                            <strong><?= htmlspecialchars($s['subject_code'] ?: $s['subject_name']) ?></strong>
                            <span class="slot-time"><?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        const Toast = Swal.mixin({
            toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });

        // Check for success message
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'saved') {
            Swal.fire({
                title: 'Dossier Updated!',
                text: 'Student records have been updated successfully.',
                icon: 'success',
                confirmButtonColor: 'var(--primary)',
                confirmButtonText: 'Affirmative'
            }).then(() => {
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname + "?qr=<?= urlencode($qr) ?>");
            });
        }

        function openEditModal() { document.getElementById('editModal').style.display = 'flex'; }
        function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

        document.addEventListener('DOMContentLoaded', () => {
            const duration = 2000;
            const counters = document.querySelectorAll('.profile-counter');
            const charts = document.querySelectorAll('.attendance-chart');

            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    counter.innerText = Math.floor(progress * target);
                    if (progress < 1) window.requestAnimationFrame(step);
                };
                window.requestAnimationFrame(step);
            });

            charts.forEach(chart => {
                const targetPercent = +chart.getAttribute('data-percent');
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    const currentPercent = progress * targetPercent;
                    chart.style.background = `conic-gradient(var(--primary) ${currentPercent}%, var(--bg-main) 0deg)`;
                    chart.setAttribute('data-display', Math.floor(currentPercent) + '%');
                    if (progress < 1) window.requestAnimationFrame(step);
                    else chart.setAttribute('data-display', targetPercent + '%');
                };
                window.requestAnimationFrame(step);
            });

            if (typeof initAnimatedList === 'function') {
                initAnimatedList('.history-rows');
            }
        });

        // ─── Matrix Filter ───
        function filterMatrix(filter, el) {
            document.querySelectorAll('.matrix-filter-chips .chip').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            const isSem = filter === '1' || filter === '2';
            const rows = document.querySelectorAll('#matrixTable tbody tr.enter');
            rows.forEach(row => {
                if (filter === 'all') { row.style.display = ''; return; }
                if (isSem) {
                    const sem = (row.getAttribute('data-sem') || '').trim();
                    row.style.display = sem.startsWith(filter) || sem.includes('Sem ' + filter) ? '' : 'none';
                    return;
                }
                row.style.display = (row.getAttribute('data-status') || '') === filter ? '' : 'none';
            });
            const dividers = document.querySelectorAll('#matrixTable .sem-group-divider');
            dividers.forEach(div => {
                const parentRow = div.closest('tr');
                if (filter === 'all') { if (parentRow) parentRow.style.display = ''; return; }
                if (isSem) {
                    const sem = (div.getAttribute('data-sem') || '').trim();
                    if (parentRow) parentRow.style.display = sem.startsWith(filter) || sem.includes('Sem ' + filter) ? '' : 'none';
                } else {
                    if (parentRow) parentRow.style.display = 'none';
                }
            });
        }

        // ─── Schedule Modal ───
        function openScheduleModal() {
            document.getElementById('scheduleModal').classList.add('active');
        }
        function closeScheduleModal() {
            document.getElementById('scheduleModal').classList.remove('active');
        }

        function downloadQR() {
            const qrStr = '<?= $user['qr_code'] ?>';
            const url = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodeURIComponent(qrStr)}`;
            fetch(url).then(r => r.blob()).then(blob => {
                const link = document.createElement('a'); link.href = URL.createObjectURL(blob);
                link.download = `QR_<?= urlencode($user['name']) ?>.png`; link.click();
                Toast.fire({ icon: 'success', title: 'Security QR Downloaded' });
            });
        }

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
            Toast.fire({ icon: 'success', title: 'Dossier Saved' });
            window.history.replaceState({}, '', 'profile.php?qr=<?= $qr ?>');
        <?php endif; ?>

        // Birthday Image Upload Handler for Profile
        document.getElementById('p-bday-upload').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('image', file);

            try {
                Swal.fire({ title: 'Uploading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const response = await fetch('api/upload_image.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                Swal.close();

                if (res.success) {
                    document.getElementById('p-bday-img').value = res.path;
                    document.getElementById('p-bday-preview').innerHTML = `<img src="${res.path}" style="width:100%; height:100%; object-fit:cover;">`;
                    Toast.fire({ icon: 'success', title: 'Thumbnail uploaded' });
                } else {
                    let errorMsg = res.error;
                    if (res.details) {
                        errorMsg += "\nDetails: " + JSON.stringify(res.details, null, 2);
                    }
                    throw new Error(errorMsg);
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    html: '<pre style="text-align: left; font-size: 0.75rem;">' + e.message + '</pre>',
                    confirmButtonText: 'OK'
                });
            }
        });
    </script>
</body>
</html>
