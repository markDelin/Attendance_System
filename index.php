<?php
// index.php - Student Attendance System Dashboard
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard | QR Tools</title>
    <link href="assets/css/style.css?v=1.3" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .section-title {
            font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.12em;
            font-weight: 800; margin-bottom: 1.25rem; margin-top: 3rem; border-bottom: 1px solid var(--border);
            padding-bottom: 0.6rem; display: flex; align-items: center; gap: 8px;
        }

        /* Stats Grid */
        .dashboard-stats {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;
        }
        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; padding: 1.75rem;
            display: flex; flex-direction: column; position: relative; overflow: hidden;
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.4s var(--ease-out-expo);
        }
        .stat-card::after {
            content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--primary); border-radius: 0 4px 4px 0;
        }
        .stat-card:hover { 
            transform: translateY(-3px); 
            box-shadow: var(--shadow-neu-out-lg);
            border-color: rgba(59, 130, 246, 0.25);
        }
        .stat-value { font-size: 2.5rem; font-weight: 800; line-height: 1; letter-spacing: -0.05em; color: var(--text-main); margin-bottom: 0.5rem; }
        .stat-label { font-size: 0.68rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; }
        
        /* Progress Bar */
        .progress-bar-container {
            width: 100%; height: 8px; background: var(--bg-main); border-radius: 50px; overflow: hidden; margin-top: 1rem;
            border: 1px solid var(--border);
        }
        .progress-bar-fill {
            height: 100%; background: var(--primary); width: 0%; transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Activity Timeline */
        .activity-feed {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem;
            position: relative;
        }
        .activity-feed::before {
            content: ''; position: absolute; left: 2.25rem; top: 1.5rem; bottom: 1.5rem; width: 2px; background: var(--border);
        }
        .activity-item {
            display: flex; align-items: flex-start; gap: 1.5rem; padding: 1.25rem 0;
            position: relative; z-index: 1;
        }
        .activity-dot {
            width: 12px; height: 12px; border-radius: 50%; background: var(--bg-card); border: 3px solid var(--border);
            margin-top: 0.4rem; flex-shrink: 0; margin-left: 0.4rem;
        }
        .activity-item.active .activity-dot { border-color: var(--primary); }
        
        .activity-content { flex: 1; display: flex; justify-content: space-between; align-items: center; }
        .activity-details b { font-size: 0.95rem; color: var(--text-main); display: block; margin-bottom: 2px; }
        .activity-details span { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; }
        .activity-meta { text-align: right; }
        .activity-time { font-size: 0.8rem; font-weight: 700; color: var(--text-main); font-family: monospace; display: block; }
        .activity-status { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); }

        .dot-present { border-color: #10b981 !important; background: #10b981 !important; }
        .dot-late { border-color: #f59e0b !important; background: #f59e0b !important; }
        .dot-absent { border-color: #ef4444 !important; background: #ef4444 !important; }

        /* Actions Custom */
        .action-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1.25rem;
        }
        .action-btn {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px;
            padding: 1.75rem 1rem; text-decoration: none; color: var(--text-main);
            transition: all 0.3s var(--ease-out-expo); display: flex; flex-direction: column; 
            align-items: center; justify-content: center; min-height: 120px; text-align: center;
            box-shadow: var(--shadow-neu-out-sm); gap: 0.75rem;
        }
        .action-btn:hover { 
            transform: translateY(-4px); 
            box-shadow: var(--shadow-neu-out-lg);
            border-color: var(--primary);
        }
        .action-btn i { 
            font-size: 1.6rem; width: 46px; height: 46px; display: flex; align-items: center; justify-content: center; 
            border-radius: 14px; background: color-mix(in srgb, var(--primary) 8%, transparent); color: var(--primary); 
            transition: transform 0.3s var(--ease-out-expo);
        }
        .action-btn:hover i {
            transform: scale(1.08) rotate(-3deg);
        }
        .action-btn span { font-weight: 750; font-size: 0.8rem; letter-spacing: -0.01em; line-height: 1.35; font-family: 'Outfit', sans-serif; }

        @media (max-width: 768px) {
            .mobile-stack { flex-direction: column; }
            .dashboard-stats { grid-template-columns: 1fr; }
            .action-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.75rem !important; }
            .activity-feed::before { left: 1.75rem; }
            .activity-dot { margin-left: -0.1rem; }
        }
        
        /* Birthday Grid Layout Refined */
        .birthday-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .birthday-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.4s var(--ease-out-expo);
            border: 1px solid var(--border);
        }

        .birthday-card.today {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.15), var(--shadow-neu-out-sm);
            border-color: rgba(59, 130, 246, 0.4);
            animation: bdayGlow 2.5s infinite ease-in-out;
        }

        @keyframes bdayGlow {
            0%, 100% { border-color: rgba(59, 130, 246, 0.3); }
            50% { border-color: rgba(59, 130, 246, 0.6); }
        }

        .birthday-card .student-name {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-main);
            text-transform: capitalize;
            font-family: 'Outfit', sans-serif;
        }

        .birthday-card .date-badge {
            font-size: 0.68rem;
            color: var(--text-muted);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .countdown-container {
            background: var(--bg-main);
            box-shadow: var(--shadow-neu-in-sm);
            border-radius: 14px;
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.25rem;
            border: 1px solid var(--border);
        }

        .countdown-part {
            display: flex;
            align-items: baseline;
            gap: 2px;
        }

        .countdown-part .val {
            font-size: 1.05rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-main);
        }

        .countdown-part .unit {
            font-size: 0.58rem;
            color: var(--text-muted);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .countdown-part .val.seconds {
            color: var(--accent);
        }

        @media (max-width: 768px) {
            .birthday-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
            }
            .birthday-card.featured {
                grid-column: span 2 !important;
            }
            .birthday-card {
                padding: 1.25rem !important;
            }
            .birthday-card .student-name {
                font-size: 0.88rem !important;
            }
            .countdown-container {
                padding: 0.65rem 0.85rem !important;
            }
            .countdown-part .val {
                font-size: 0.9rem !important;
            }
        }
            padding: 0.75rem 2.25rem;
            border-radius: 50px;
            border: none;
            font-weight: 800;
            font-size: 0.88rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.25s;
        }
        .notif-success-btn {
            background: var(--success);
            color: white;
        }
        .notif-success-btn:hover {
            background: color-mix(in srgb, var(--success) 85%, black);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px color-mix(in srgb, var(--success) 35%, transparent);
        }
        .notif-error-btn {
            background: var(--danger);
            color: white;
        }
        .notif-error-btn:hover {
            background: color-mix(in srgb, var(--danger) 85%, black);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px color-mix(in srgb, var(--danger) 35%, transparent);
        }

        /* Delete Specific Modal */
        .delete-modal-body {
            text-align: center;
            padding: 2.25rem 2rem 1.75rem;
        }
        .delete-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: color-mix(in srgb, var(--danger) 10%, transparent);
            color: var(--danger);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            margin: 0 auto 1.25rem;
            box-shadow: 0 8px 20px -4px color-mix(in srgb, var(--danger) 30%, transparent);
        }

        @media (max-width: 600px) {
            .subjects-card { padding: 1.5rem !important; border-radius: 18px; }
            .btn-primary { width: 100%; padding: 1rem !important; }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>
    
    <?php
    // Fetch nearest birthdays (ignore year)
    $stmt = $pdo->prepare("SELECT qr_code, name, birthday FROM users WHERE birthday IS NOT NULL AND birthday != '' AND deleted_at IS NULL");
    $stmt->execute();
    $studentsWithBirthdays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $upcomingBirthdays = [];
    $now = new DateTime();
    $today = new DateTime($now->format('Y-m-d')); // Zero out time for date comparison

    foreach ($studentsWithBirthdays as $s) {
        $bdayDate = new DateTime($s['birthday']);
        $thisYearBday = new DateTime(date('Y') . '-' . $bdayDate->format('m-d'));
        
        if ($thisYearBday < $today) {
            $nextBday = new DateTime((date('Y') + 1) . '-' . $bdayDate->format('m-d'));
        } else {
            $nextBday = $thisYearBday;
        }

        $interval = $today->diff($nextBday);
        $daysUntil = $interval->days;

        $upcomingBirthdays[] = [
            'qr_code' => $s['qr_code'],
            'name' => $s['name'],
            'birthday' => $s['birthday'],
            'next_occurrence' => $nextBday->format('Y-m-d 00:00:00'),
            'days_until' => $daysUntil
        ];
    }

    // Sort by nearest
    usort($upcomingBirthdays, function($a, $b) {
        return $a['days_until'] <=> $b['days_until'];
    });

    // Take top 5
    $upcomingBirthdays = array_slice($upcomingBirthdays, 0, 5);

    // Fetch Recent System Notices
    $stmt = $pdo->query("SELECT * FROM system_notices ORDER BY created_at DESC LIMIT 5");
    $systemNotices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <main class="container">
        
        <?php
        // Local IP Detection
        $localIP = "127.0.0.1";
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('ipconfig', $output);
            foreach ($output as $line) {
                if (preg_match('/IPv4 Address.*: (192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2[0-9]|3[0-1])\.\d+\.\d+)/', $line, $m)) {
                    $localIP = $m[1];
                    break;
                }
            }
        } else {
            $localIP = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
        }
        ?>

        <div style="margin-top: 2.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="margin: 0; font-size: 2rem; font-weight: 800; letter-spacing: -0.05em; font-family: 'Outfit', sans-serif;">Dashboard</h1>
                <p style="color: var(--text-muted); font-size: 0.88rem; font-weight: 500; margin:0;"><?= date('l, F j, Y') ?></p>
            </div>
            <div class="ip-access" style="background: var(--bg-card); border: 1px solid var(--border); padding: 8px 18px; border-radius: 50px; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-neu-out-sm);">
                <div style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%; box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.12);"></div>
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Access Server IP: <b style="color: var(--primary); font-family: 'JetBrains Mono', monospace; font-size: 0.7rem;"><?= $localIP ?>:8000</b></span>
            </div>
        </div>

        <?php
        // School Year Progress Calculation
        $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
        $syStart = $settings['sy_start_date'] ?? null;
        $syEnd = $settings['sy_end_date'] ?? null;
        $activeSY = $settings['active_school_year'] ?? 'Not Configured';

        $daysRemaining = null;
        $progressPercent = 0;
        $rem = 0;
        
        if ($syStart && $syEnd) {
            $startDate = new DateTime($syStart);
            $endDate = new DateTime($syEnd);
            $today = new DateTime();
            
            if ($today < $startDate) {
                $daysRemaining = "Starts in " . $today->diff($startDate)->days . " days";
                $progressPercent = 0;
            } elseif ($today > $endDate) {
                $daysRemaining = "School Year Ended";
                $progressPercent = 100;
            } else {
                $totalDays = $startDate->diff($endDate)->days;
                $elapsedDays = $startDate->diff($today)->days;
                $rem = $today->diff($endDate)->days;
                $daysRemaining = $rem . " Days Remaining";
                $progressPercent = ($totalDays > 0) ? round(($elapsedDays / $totalDays) * 100) : 0;
            }
        }
        ?>

        <?php if ($syStart && $syEnd): ?>
        <div class="animate-fade-up" style="margin-top: 2rem;">
            <div class="card" style="padding: 1.5rem 2rem; border-radius: 20px; border: 1px solid var(--border); background: var(--bg-card); display: flex; align-items: center; justify-content: space-between; gap: 2rem;">
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em;">
                            Academic Year: <b style="color: var(--text-main);"><?= htmlspecialchars($activeSY) ?></b>
                        </span>
                        <span style="font-size: 0.85rem; font-weight: 800; color: var(--primary); letter-spacing: -0.02em;">
                            <span class="stat-counter" data-target="<?= (int)$rem ?>"><?= (int)$rem ?></span> Days Remaining
                        </span>
                    </div>
                    <div class="progress-bar-container" style="height: 8px; background: var(--bg-main); border-radius: 50px; overflow: hidden;">
                        <div class="progress-bar-fill" data-progress="<?= $progressPercent ?>%" style="width: 0%; height: 100%; background: var(--primary); border-radius: 50px; transition: width 2s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                    </div>
                </div>
                <div style="text-align: right; min-width: 60px;">
                    <span style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.05em;">
                        <span class="stat-counter" data-target="<?= $progressPercent ?>"><?= $progressPercent ?></span>%
                    </span>
                    <small style="display: block; font-size: 0.6rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Progress</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <section class="animate-fade-up">
            <div class="section-title"><i class="bi bi-grid" style="font-size: 0.85rem;"></i> Navigation Hub</div>
            <div class="action-grid">
                <a href="scan.php" class="action-btn card-violet interactive-glow animate-fade-up stagger-1 hover-lift">
                    <i class="bi bi-qr-code-scan"></i>
                    <span>QR Scanner</span>
                </a>
                <a href="manual.php" class="action-btn card-emerald interactive-glow animate-fade-up stagger-2 hover-lift">
                    <i class="bi bi-person-check"></i>
                    <span>Manual Entry</span>
                </a>
                <a href="reattendance.php" class="action-btn card-amber interactive-glow animate-fade-up stagger-3 hover-lift">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>System Re-run</span>
                </a>
                <a href="manage_students.php" class="action-btn card-rose interactive-glow animate-fade-up stagger-4 hover-lift">
                    <i class="bi bi-people"></i>
                    <span>Classmates Database</span>
                </a>
                <a href="orders.php" class="action-btn card-sky interactive-glow animate-fade-up stagger-5 hover-lift">
                    <i class="bi bi-cart"></i>
                    <span>Ordered Records</span>
                </a>
                <a href="view_attendance.php" class="action-btn card-turquoise interactive-glow animate-fade-up stagger-6 hover-lift">
                    <i class="bi bi-clipboard-data"></i>
                    <span>Attendance Records</span>
                </a>
                <a href="wifi.php" class="action-btn card-violet interactive-glow animate-fade-up stagger-7 hover-lift">
                    <i class="bi bi-wifi"></i>
                    <span>WiFi Collection</span>
                </a>
                <a href="subjects.php" class="action-btn card-rose interactive-glow animate-fade-up stagger-8 hover-lift">
                    <i class="bi bi-book"></i>
                    <span>Subject Management</span>
                </a>
            </div>

            

            <!-- Birthday Section -->
            <?php if (!empty($upcomingBirthdays)): ?>
            <div class="section-title"><i class="bi bi-balloon-heart" style="font-size: 0.85rem;"></i> UPCOMING BIRTHDAYS</div>
            <div class="birthday-grid">
                <?php foreach ($upcomingBirthdays as $idx => $bday): 
                    $stagger = "stagger-" . min($idx + 1, 8);
                    $isToday = $bday['days_until'] === 0;
                    $isFeatured = $idx === 0; 
                    $cardClass = ($isFeatured ? 'featured ' : '') . ($isToday ? 'today' : '');
                ?>
                    <div class="birthday-card <?= $cardClass ?> animate-fade-up <?= $stagger ?> hover-lift">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <?php 
                                $bdayInitial = strtoupper(substr($bday['name'], 0, 1));
                                $bdayColors = ['#5c6bc0','#42a5f5','#26a69a','#66bb6a','#ec407a','#ab47bc','#ef5350','#ffa726'];
                                $bdayColor = $bdayColors[$idx % count($bdayColors)];
                            ?>
                            <div style="width: 38px; height: 38px; border-radius: 12px; background: <?= $bdayColor ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.88rem; font-family: 'Outfit', sans-serif; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.12);">
                                <?= $bdayInitial ?>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 1px;">
                                <span class="student-name"><?= htmlspecialchars($bday['name']) ?></span>
                                <span class="date-badge">
                                    <?= date('F d', strtotime($bday['birthday'])) ?>
                                    <?php if($isToday): ?>
                                        <span style="color: var(--accent); margin-left: 6px; font-weight: 800;">🎂 TODAY</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="countdown-container" data-target="<?= $bday['next_occurrence'] ?>">
                            <div class="countdown-part">
                                <span class="days val">--</span>
                                <span class="unit">d</span>
                            </div>
                            <div class="countdown-part">
                                <span class="hours val">--</span>
                                <span class="unit">h</span>
                            </div>
                            <div class="countdown-part">
                                <span class="minutes val">--</span>
                                <span class="unit">m</span>
                            </div>
                            <div class="countdown-part">
                                <span class="seconds val">--</span>
                                <span class="unit">s</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($systemNotices)): ?>
            <div class="section-title" style="color: var(--danger); opacity: 0.9; margin-top: 3rem;"><i class="bi bi-shield-exclamation" style="font-size: 0.85rem;"></i> RECENT SYSTEM NOTICES</div>
            <div style="margin-bottom: 2rem;">
                <?php foreach ($systemNotices as $idx => $notice): 
                    $stagger = "stagger-" . min($idx + 1, 8);
                ?>
                    <div class="card animate-fade-up <?= $stagger ?> hover-lift" style="padding: 1.25rem; margin-bottom: 1rem; border: 1px solid var(--border); border-left: 4px solid var(--danger); background: var(--bg-card); display: flex; align-items: center; gap: 1.25rem; border-radius: 16px;">
                        <div class="flex-center" style="width: 44px; height: 44px; border-radius: 12px; background: rgba(239, 68, 68, 0.08); color: var(--danger); flex-shrink: 0;">
                            <i class="bi bi-shield-exclamation" style="font-size: 1.25rem;"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-main); line-height: 1.45;">
                                <?= strip_tags($notice['content'], '<b><i><em><strong>') ?>
                            </div>
                            <div style="font-size: 0.68rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.05em;">
                                <?= date('M d, Y · h:i A', strtotime($notice['created_at'])) ?> · SECURITY AUDIT LOG
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="section-title"><i class="bi bi-tools" style="font-size: 0.85rem;"></i> Utility Tools</div>
            <div class="action-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));">
                <a href="markdown_editor.php" class="action-btn interactive-glow animate-fade-up stagger-4 hover-lift">
                    <i class="bi bi-journal-richtext"></i>
                    <span>Medical Reports</span>
                </a>
                <a href="groups.php" class="action-btn interactive-glow animate-fade-up stagger-5 hover-lift">
                    <i class="bi bi-diagram-3"></i>
                    <span>Group Randomizer</span>
                </a>
                <a href="calendar.php" class="action-btn interactive-glow animate-fade-up stagger-6 hover-lift">
                    <i class="bi bi-calendar3"></i>
                    <span>Event Calendar</span>
                </a>
                <a href="announcements.php" class="action-btn interactive-glow animate-fade-up stagger-7 hover-lift">
                    <i class="bi bi-megaphone"></i>
                    <span>Announcements</span>
                </a>
                <a href="settings.php" class="action-btn interactive-glow animate-fade-up stagger-8 hover-lift">
                    <i class="bi bi-gear-wide-connected"></i>
                    <span>System Settings</span>
                </a>
            </div>
        </section>

        </div>
    </main>

    <script>
        // Real-time Countdown Logic
        function updateCountdowns() {
            document.querySelectorAll('.countdown-container').forEach(timer => {
                const targetDate = new Date(timer.dataset.target).getTime();
                const now = new Date().getTime();
                const distance = targetDate - now;

                if (distance < 0) {
                    timer.innerHTML = '<div style="width: 100%; text-align: center; color: var(--accent); font-weight: 800; font-size: 0.8rem; letter-spacing: 0.05em;">🎉 CELEBRATING TODAY! 🎂</div>';
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                timer.querySelector('.days').innerText = String(days).padStart(2, '0');
                timer.querySelector('.hours').innerText = String(hours).padStart(2, '0');
                timer.querySelector('.minutes').innerText = String(minutes).padStart(2, '0');
                timer.querySelector('.seconds').innerText = String(seconds).padStart(2, '0');
                timer.querySelector('.seconds').style.color = 'var(--accent)';
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
             console.log('Dashboard ready');
             
             // Start Countdowns
             updateCountdowns();
             setInterval(updateCountdowns, 1000);
             
             // Initial animations
             setTimeout(() => {
                 document.querySelectorAll('.progress-bar-fill').forEach(bar => {
                     const target = bar.getAttribute('data-progress');
                     if(target) bar.style.width = target;
                 });
             }, 100);
        });
    </script>
</body>
</html>

