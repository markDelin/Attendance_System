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
$stmtActivity = $pdo->prepare("
    SELECT session as subject_name, status, date, time FROM attendance WHERE qr_code = ? AND (session IS NOT NULL AND session != '')
    UNION ALL
    SELECT s.name as subject_name, sa.status, sa.date, sa.time FROM subject_attendance sa JOIN subjects s ON sa.subject_id = s.id WHERE sa.qr_code = ?
    ORDER BY date DESC, time DESC LIMIT 10
");
$stmtActivity->execute([$qr, $qr]);
$history = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);

// Detailed Analytics (Subject-centric + Daily Event logs)
$stmtStats = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent,
        COUNT(*) as total
    FROM (
        SELECT status FROM attendance WHERE qr_code = ? AND (session IS NOT NULL AND session != '')
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

        /* Media Thumbnail elegant styling */
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



        @media (max-width: 992px) {
            .profile-container { grid-template-columns: 1fr; gap: 2rem; }
            .sidebar-monolith { position: static; }
        }

        @media (max-width: 768px) {
            .field-grid { grid-template-columns: 1fr; }
            .field-grid .full-width { grid-column: span 1; }
            .modal-body { max-height: 95vh; }
            .modal-scroll-area { padding: 1.25rem; }
            .modal-footer-pro { padding: 1rem 1.25rem; }
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
                    <h2 style="font-weight: 800; letter-spacing: -0.04em; margin: 0 0 0.35rem; font-size: 1.45rem; font-family: 'Outfit', sans-serif;"><?= htmlspecialchars($user['name']) ?></h2>
                    <p style="font-family: 'JetBrains Mono', monospace; color: var(--text-muted); font-size: 0.75rem; letter-spacing: 0.05em; margin: 0 0 1.25rem;"><?= htmlspecialchars($user['qr_code']) ?></p>
                    
                    <div style="display: flex; justify-content: center; gap: 8px; margin-bottom: 2rem; flex-wrap: wrap;">
                        <span class="badge" style="background: rgba(59, 130, 246, 0.08); color: var(--primary); border: 1px solid rgba(59, 130, 246, 0.15);"><?= htmlspecialchars(ucfirst($user['student_type'] ?? 'Regular')) ?></span>
                        <span class="badge" style="background: var(--bg-hover); color: var(--text-muted); border: 1px solid var(--border);"><?= htmlspecialchars($user['year_level'] ?? '1st') ?> Year</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button onclick="downloadQR()" class="btn btn-primary" style="justify-content: center; padding: 0.65rem; font-size: 0.8rem; border-radius: 50px; font-weight: 700;">Save QR</button>
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
                    <a href="student_history.php?qr_code=<?= urlencode($qr) ?>" class="btn btn-ghost" style="font-size: 0.8rem; font-weight: 800; color: var(--primary); text-decoration: none; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid var(--border); border-radius: 12px; padding: 0.5rem 1rem;">View History Report</a>
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

                </div>

                <!-- Recent Activities Timeline -->
                <div class="history-section">
                    <h4 style="font-weight: 800; letter-spacing: -0.02em; margin: 0 0 4px; font-family: 'Outfit', sans-serif;">Attendance Timeline</h4>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 1.5rem;">Last 10 logged school activities and subjects</p>

                    <div class="scroll-list-container" style="background: var(--bg-main); border-radius: 20px; padding: 0.25rem;">
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
