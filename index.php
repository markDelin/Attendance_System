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
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .section-title {
            font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.12em;
            font-weight: 800; margin-bottom: 1rem; margin-top: 2.5rem; border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;
        }

        /* Stats Grid */
        .dashboard-stats {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;
        }
        .stat-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.75rem;
            display: flex; flex-direction: column; position: relative; overflow: hidden;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card::after {
            content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--primary); opacity: 0.1;
        }
        .stat-value { font-size: 2.75rem; font-weight: 800; line-height: 1; letter-spacing: -0.05em; color: var(--text-main); margin-bottom: 0.75rem; }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }
        
        /* Progress Bar */
        .progress-bar-container {
            width: 100%; height: 6px; background: var(--bg-main); border-radius: 50px; overflow: hidden; margin-top: 1rem;
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
            display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem;
        }
        .action-btn {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
            padding: 1.5rem; text-decoration: none; color: var(--text-main);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; 
            align-items: center; justify-content: center; min-height: 110px; text-align: center;
        }
        .action-btn:hover { border-color: var(--primary); background: var(--bg-hover); }
        .action-btn i { font-size: 1.75rem; margin-bottom: 0.75rem; color: var(--primary); }
        .action-btn span { font-weight: 700; font-size: 0.8rem; letter-spacing: 0.02em; }

        @media (max-width: 768px) {
            .mobile-stack { flex-direction: column; }
            .dashboard-stats { grid-template-columns: 1fr; }
            .action-grid { grid-template-columns: 1fr 1fr; }
            .activity-feed::before { left: 1.75rem; }
            .activity-dot { margin-left: -0.1rem; }
        }
        /* Birthday Grid Layout Refined */
        .birthday-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .birthday-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.5rem !important;
            }
            .birthday-card.featured {
                grid-column: span 2 !important;
            }
            .birthday-card.small {
                padding: 0.75rem !important;
            }
            .birthday-card.small .student-name {
                font-size: 0.85rem !important;
            }
            .birthday-card.small .countdown-container {
                padding: 8px !important;
                border-radius: 8px !important;
            }
            .birthday-card.small .countdown-part span:first-child {
                font-size: 0.9rem !important;
            }
            .birthday-card.small .countdown-part span:last-child {
                font-size: 0.5rem !important;
            }
            .birthday-card.small .birthday-icon {
                width: 32px !important;
                height: 32px !important;
                border-radius: 8px !important;
            }
            .birthday-card.small .birthday-icon i {
                font-size: 0.9rem !important;
            }
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
                <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; letter-spacing: -0.05em;">Dashboard</h1>
                <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500; margin:0;"><?= date('l, F j, Y') ?></p>
            </div>
            <div class="ip-access" style="background: var(--bg-card); padding: 10px 20px; border-radius: 50px; border: 1px solid var(--border); display: flex; align-items: center; gap: 10px;">
                <div style="width: 10px; height: 10px; background: #22c55e; border-radius: 50%; box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.15);"></div>
                <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">Mobile: <b style="color: var(--primary); font-family: monospace;"><?= $localIP ?>:8000</b></span>
            </div>
        </div>

        <?php
        // School Year Progress Calculation
        $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $syStart = $settings['sy_start_date'] ?? null;
        $syEnd = $settings['sy_end_date'] ?? null;
        $activeSY = $settings['active_school_year'] ?? 'Not Configured';

        $daysRemaining = null;
        $progressPercent = 0;
        
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
            <div class="section-title">Navigation Hub</div>
            <div class="action-grid">
                <a href="scan.php" class="action-btn animate-fade-up stagger-1 hover-lift">
                    <i class="bi bi-qr-code-scan"></i>
                    <span>Scanner</span>
                </a>
                <a href="manual.php" class="action-btn animate-fade-up stagger-2 hover-lift">
                    <i class="bi bi-person-check"></i>
                    <span>Manual Entry</span>
                </a>
                <a href="reattendance.php" class="action-btn animate-fade-up stagger-3 hover-lift">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>System Re-run</span>
                </a>
                <a href="manage_students.php" class="action-btn animate-fade-up stagger-4 hover-lift">
                    <i class="bi bi-people-fill"></i>
                    <span>Student Database</span>
                </a>
                <a href="view_attendance.php" class="action-btn animate-fade-up stagger-6 hover-lift">
                    <i class="bi bi-clipboard-data-fill"></i>
                    <span>Attendance Records</span>
                </a>
            </div>

            <!-- New Birthday Section -->
            <?php if (!empty($upcomingBirthdays)): ?>
            <div class="section-title">
                SYSTEM NOTICE: BIRTHDAYS
            </div>
            <div class="birthday-grid">
                <?php foreach ($upcomingBirthdays as $idx => $bday): 
                    $stagger = "stagger-" . min($idx + 1, 8);
                    $isToday = $bday['days_until'] === 0;
                    $isFeatured = $idx === 0; 
                    $accentColor = $isToday ? 'var(--accent)' : 'var(--text-muted)';
                    $bgLight = $isToday ? 'rgba(59, 130, 246, 0.1)' : 'var(--bg-main)';
                    $cardClass = $isFeatured ? 'featured' : 'small';
                ?>
                    <div class="stat-card birthday-card <?= $cardClass ?> animate-fade-up <?= $stagger ?>" style="padding: 1.25rem; position: relative; border: 1px solid var(--border);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                <span class="student-name" style="font-size: 0.9rem; font-weight: 700; color: var(--text-main); text-transform: uppercase;"><?= htmlspecialchars($bday['name']) ?></span>
                                <span style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;">
                                    <?= date('M d', strtotime($bday['birthday'])) ?>
                                    <?php if($isToday): ?>
                                        <span style="color: var(--accent); margin-left: 8px; font-weight: 800;">[ TODAY ]</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="countdown-container" 
                             data-target="<?= $bday['next_occurrence'] ?>" 
                             style="background: transparent; border-radius: 0; padding: 0.5rem 0; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border);">
                            <div class="countdown-part" style="display: flex; align-items: baseline; gap: 4px;">
                                <span class="days" style="font-size: 0.85rem; font-weight: 800; font-family: monospace;">--</span>
                                <span style="font-size: 0.5rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">d</span>
                            </div>
                            <div class="countdown-part" style="display: flex; align-items: baseline; gap: 4px;">
                                <span class="hours" style="font-size: 0.85rem; font-weight: 800; font-family: monospace;">--</span>
                                <span style="font-size: 0.5rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">h</span>
                            </div>
                            <div class="countdown-part" style="display: flex; align-items: baseline; gap: 4px;">
                                <span class="minutes" style="font-size: 0.85rem; font-weight: 800; font-family: monospace;">--</span>
                                <span style="font-size: 0.5rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">m</span>
                            </div>
                            <div class="countdown-part" style="display: flex; align-items: baseline; gap: 4px;">
                                <span class="seconds" style="font-size: 0.85rem; font-weight: 800; font-family: monospace; color: var(--accent);">--</span>
                                <span style="font-size: 0.5rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">s</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="section-title">Utility Tools</div>
            <div class="action-grid" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
                <a href="markdown_editor.php" class="action-btn animate-fade-up stagger-4 hover-lift">
                    <i class="bi bi-journal-richtext" style="font-size: 1.4rem;"></i>
                    <span style="font-size: 0.75rem;">Medical / Markdown Reports</span>
                </a>
                <a href="groups.php" class="action-btn animate-fade-up stagger-5 hover-lift">
                    <i class="bi bi-diagram-3" style="font-size: 1.4rem;"></i>
                    <span style="font-size: 0.75rem;">Group Management</span>
                </a>
                <a href="calendar.php" class="action-btn animate-fade-up stagger-6 hover-lift">
                    <i class="bi bi-calendar3" style="font-size: 1.4rem;"></i>
                    <span style="font-size: 0.75rem;">Event Calendar</span>
                </a>
                <a href="settings.php" class="action-btn animate-fade-up stagger-7 hover-lift">
                    <i class="bi bi-gear-wide-connected" style="font-size: 1.4rem;"></i>
                    <span style="font-size: 0.75rem;">System Settings</span>
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

        setInterval(updateCountdowns, 1000);
        updateCountdowns();

        function animateCounters() {
            const counters = document.querySelectorAll('.stat-counter');
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                const duration = 2000; // 2 seconds
                let startTimestamp = null;

                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    const current = Math.floor(progress * target);
                    counter.innerText = current;
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        counter.innerText = target;
                    }
                };
                window.requestAnimationFrame(step);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
             console.log('Dashboard loaded');
             animateCounters();
             
             // Animate Progress Bar
             setTimeout(() => {
                 const progressBar = document.querySelector('.progress-bar-fill');
                 if (progressBar) {
                     progressBar.style.width = progressBar.getAttribute('data-progress');
                 }
             }, 100);
        });
    </script>
</body>
</html>
