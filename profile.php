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
            birthday = ?, email = ?, course = ?, sex = ?, 
            place_of_birth = ?, civil_status = ?, religion = ?, citizenship = ?, contact_number = ?,
            student_type = ?, year_level = ?
            WHERE qr_code = ?");
        $stmt->execute([
            $name, $first_name, $last_name, $middle_initial, 
            (!empty($_POST['birthday']) ? $_POST['birthday'] : null), 
            (!empty($_POST['email']) ? trim($_POST['email']) : null), 
            $_POST['course'], $_POST['sex'], 
            $_POST['place_of_birth'], $_POST['civil_status'], $_POST['religion'], $_POST['citizenship'], $_POST['contact_number'], 
            $_POST['student_type'] ?? 'regular', $_POST['year_level'] ?? '1st',
            $qr
        ]);
        header("Location: profile.php?qr=$qr&msg=saved");
        exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Recent Activity
$stmtActivity = $pdo->prepare("
    SELECT 'Daily' as subject_name, status, date, time FROM attendance WHERE qr_code = ?
    UNION ALL
    SELECT s.name as subject_name, sa.status, sa.date, sa.time FROM subject_attendance sa JOIN subjects s ON sa.subject_id = s.id WHERE sa.qr_code = ?
    ORDER BY date DESC, time DESC LIMIT 10
");
$stmtActivity->execute([$qr, $qr]);
$history = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);

// Detailed Analytics
$stmtStats = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent,
        COUNT(*) as total
    FROM (
        SELECT status FROM attendance WHERE qr_code = ?
        UNION ALL
        SELECT status FROM subject_attendance WHERE qr_code = ?
    ) as combined
");
$stmtStats->execute([$qr, $qr]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$totalMarks = $stats['total'] ?: 0;
$presentPercent = $totalMarks > 0 ? round((($stats['present'] + $stats['late']) / $totalMarks) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['name']) ?> | Profile</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .profile-container {
            display: grid; 
            grid-template-columns: 350px 1fr; 
            gap: 3rem; 
            padding-top: 1rem;
        }

        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        /* Sidebar Info */
        .profile-sidebar {
            display: flex; flex-direction: column; gap: 2rem;
        }
        .hero-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2.5rem 1.5rem; text-align: center;
        }
        .avatar-circle {
            width: 120px; height: 120px; border-radius: 50%; background: var(--bg-main); margin: 0 auto 1.5rem;
            display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 300;
            border: 1px solid var(--border); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.02); color: var(--primary);
        }
        
        /* Circular Chart */
        .analytics-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 2.5rem; text-align: center;
        }
        .attendance-chart {
            width: 160px; height: 160px; border-radius: 50%; margin: 0 auto 1.5rem; position: relative;
            background: conic-gradient(var(--primary) <?= $presentPercent ?>%, var(--bg-main) 0deg);
            display: flex; align-items: center; justify-content: center;
        }
        .attendance-chart::after {
            content: '<?= $presentPercent ?>%'; width: 130px; height: 130px; background: var(--bg-card); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; position: absolute;
            font-size: 2rem; font-weight: 800; letter-spacing: -0.05em; font-family: 'Outfit', sans-serif;
        }

        /* Stats Grid */
        .stats-mini {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 1rem;
        }
        .mini-item { padding: 16px 4px; border: 1px solid var(--border); border-radius: var(--radius-md); }
        .mini-item b { display: block; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.05em; }
        .mini-item span { font-size: 0.65rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); letter-spacing: 0.05em; }

        /* History Feed */
        .history-feed {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden;
        }
        .history-item {
            display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border); transition: all 0.2s;
        }
        .history-item:last-child { border-bottom: none; }
        .history-item:hover { background: var(--bg-main); padding-left: 2.25rem; }
        .history-info b { font-size: 1.1rem; display: block; color: var(--text-main); margin-bottom: 4px; }
        .history-info small { color: var(--text-muted); font-size: 0.85rem; font-family: monospace; font-weight: 600; }
        
        .pill-status {
            padding: 8px 20px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.08em; display: inline-flex; align-items: center; gap: 8px;
        }

        @media (max-width: 992px) {
            .profile-container { grid-template-columns: 1fr; gap: 2rem; padding-top: 1rem; }
            .profile-sidebar { flex-direction: column-reverse; }
        }

        @media (max-width: 600px) {
            .stats-mini {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .mini-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 20px;
            }
            .mini-item b { font-size: 1.25rem; }
            .history-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                padding: 1.25rem 1.5rem;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container">
        <div class="profile-container animate-fade-up">
            
            <!-- Left Sidebar -->
            <aside class="profile-sidebar">
                
                <div class="hero-card">
                    <div class="avatar-circle"><?= strtoupper(substr($user['name'] ?? '?', 0, 1)) ?></div>
                    <h2 style="font-weight: 800; letter-spacing: -0.04em; margin-bottom: 0.25rem;"><?= htmlspecialchars($user['name']) ?></h2>
                    <p style="font-family: monospace; color: var(--text-muted); font-size: 0.85rem; letter-spacing: 0.05em; margin-top: 0;"><?= $user['qr_code'] ?></p>
                    
                    <div style="display: flex; justify-content: center; gap: 8px; margin: 1.5rem 0;">
                        <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe;"><?= ($user['student_type'] ?? 'Regular') ?: 'Regular' ?></span>
                        <span class="badge" style="background: #f8fafc; color: var(--text-muted); border: 1px solid var(--border);"><?= ($user['year_level'] ?? '1st') ?: '1st' ?> Year</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 2rem;">
                        <button onclick="downloadQR()" class="btn btn-primary" style="justify-content: center; padding: 0.75rem;"><i class="bi bi-qr-code"></i> Save QR</button>
                        <button onclick="openEditModal()" class="btn btn-ghost" style="justify-content: center; padding: 0.75rem;"><i class="bi bi-pencil"></i> Edit</button>
                    </div>
                </div>

                <div class="analytics-card">
                    <h5 style="text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; margin-bottom: 1.5rem;">Overall Attendance Rank</h5>
                    <div class="attendance-chart"></div>
                    <div class="stats-mini">
                        <div class="mini-item">
                            <b><?= $stats['present'] ?: 0 ?></b>
                            <span>Present</span>
                        </div>
                        <div class="mini-item">
                            <b><?= $stats['late'] ?: 0 ?></b>
                            <span>Late</span>
                        </div>
                        <div class="mini-item">
                            <b><?= $stats['absent'] ?: 0 ?></b>
                            <span>Absent</span>
                        </div>
                    </div>
                </div>

            </aside>

            <!-- Right Content -->
            <section>
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem;">
                    <div>
                        <h4 style="font-weight: 800; letter-spacing: -0.02em; margin: 0;">Attendance Timeline</h4>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 4px 0 0;">Last 10 recorded activities</p>
                    </div>
                    <a href="student_history.php?qr_code=<?= urlencode($qr) ?>" style="font-size: 0.8rem; font-weight: 800; color: var(--primary); text-decoration: none; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid var(--primary);">View Report</a>
                </div>

                <div class="history-feed">
                    <?php if(empty($history)): ?>
                        <div style="padding: 4rem; text-align: center; color: var(--text-muted);">
                            <i class="bi bi-clipboard-x" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                            No activity detected yet.
                        </div>
                    <?php else: ?>
                        <?php foreach($history as $h): ?>
                            <div class="history-item">
                                <div class="history-info">
                                    <b><?= htmlspecialchars($h['subject_name']) ?></b>
                                    <small><?= date('M j, Y', strtotime($h['date'])) ?> • <?= date('h:i A', strtotime($h['time'])) ?></small>
                                </div>
                                <span class="pill-status pill-<?= $h['status'] ?>">
                                    <i class="bi bi-<?= $h['status']=='present'?'check-circle':($h['status']=='late'?'clock':'x-circle') ?>"></i>
                                    <?= $h['status'] ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($user['email'] || $user['contact_number']): ?>
                <div style="margin-top: 3rem;">
                    <h5 style="text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; margin-bottom: 1.5rem;">Contact Information</h5>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div style="background: var(--bg-main); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border);">
                            <small style="color: var(--text-muted); font-weight: 800; font-size: 0.6rem; text-transform: uppercase; display: block; margin-bottom: 8px;">Institutional Email</small>
                            <b style="font-size: 0.9rem;"><?= $user['email'] ?: 'Not sets' ?></b>
                        </div>
                        <div style="background: var(--bg-main); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border);">
                            <small style="color: var(--text-muted); font-weight: 800; font-size: 0.6rem; text-transform: uppercase; display: block; margin-bottom: 8px;">Phone Number</small>
                            <b style="font-size: 0.9rem;"><?= $user['contact_number'] ?: 'Not sets' ?></b>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </section>

        </div>
    </main>

    <!-- Edit Modal (Simplified for the demo) -->
    <div id="editModal" class="modal-overlay" onclick="if(event.target == this) closeEditModal()">
        <div class="modal-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h3 style="margin: 0; font-weight: 800; letter-spacing: -0.04em;">Edit Student Profile</h3>
                <button onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                    </div>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Course / Program</label>
                    <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($user['course'] ?? '') ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Student Type</label>
                        <select name="student_type" class="form-control">
                            <option value="regular" <?= ($user['student_type'] ?? '') === 'regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="irregular" <?= ($user['student_type'] ?? '') === 'irregular' ? 'selected' : '' ?>>Irregular</option>
                        </select>
                    </div>
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Year Level</label>
                        <select name="year_level" class="form-control">
                            <option value="1st" <?= ($user['year_level'] ?? '') === '1st' ? 'selected' : '' ?>>1st Year</option>
                            <option value="2nd" <?= ($user['year_level'] ?? '') === '2nd' ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3rd" <?= ($user['year_level'] ?? '') === '3rd' ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4th" <?= ($user['year_level'] ?? '') === '4th' ? 'selected' : '' ?>>4th Year</option>
                        </select>
                    </div>
                </div>
                <div style="text-align: right; margin-top: 3rem;">
                    <button type="button" onclick="closeEditModal()" class="btn btn-ghost" style="margin-right: 1rem; border: none;">Discard</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem; font-weight: 800;">Update Profile</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const Toast = Swal.mixin({
            toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });

        function openEditModal() { document.getElementById('editModal').style.display = 'flex'; }
        function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

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
            Toast.fire({ icon: 'success', title: 'Profile Updated Successfully' });
            window.history.replaceState({}, '', 'profile.php?qr=<?= $qr ?>');
        <?php endif; ?>
    </script>
</body>
</html>
