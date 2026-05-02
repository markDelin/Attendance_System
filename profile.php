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
            student_type = ?, year_level = ?, birthday_image = ?
            WHERE qr_code = ?");
        $stmt->execute([
            $name, $first_name, $last_name, $middle_initial, 
            (!empty($_POST['birthday']) ? $_POST['birthday'] : null), 
            (!empty($_POST['email']) ? trim($_POST['email']) : null), 
            $_POST['course'], $_POST['section'] ?? '', $_POST['sex'], 
            $_POST['place_of_birth'], $_POST['civil_status'], $_POST['religion'], $_POST['citizenship'], $_POST['contact_number'], 
            $_POST['student_type'] ?? 'regular', $_POST['year_level'] ?? '1st',
            $_POST['birthday_image'] ?? '',
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
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .profile-container {
            display: grid; 
            grid-template-columns: 320px 1fr; 
            gap: 4rem; 
            padding: 2rem 0;
        }

        /* Unified Monolith Sidebar */
        .sidebar-monolith {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .profile-header { text-align: center; }
        .avatar-circle {
            width: 110px; height: 110px; border-radius: 50%; background: var(--bg-main); margin: 0 auto 1.5rem;
            display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 300;
            border: 1px solid var(--border); color: var(--primary); font-family: 'Outfit', sans-serif;
        }
        
        .attendance-rank-box {
            padding-top: 2.5rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .attendance-chart {
            width: 90px; height: 90px; border-radius: 50%; margin: 0 auto 1.5rem; position: relative;
            background: var(--bg-main);
            display: flex; align-items: center; justify-content: center;
            transition: background 0.1s ease;
        }
        .attendance-chart::after {
            content: attr(data-display); width: 66px; height: 66px; background: var(--bg-card); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; position: absolute;
            font-size: 1rem; font-weight: 800; letter-spacing: -0.05em; font-family: 'Outfit', sans-serif;
        }

        .stats-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
        }
        .stat-blk { text-align: center; padding: 12px 0; border: 1px solid var(--border); border-radius: 12px; }
        .stat-blk b { display: block; font-size: 1.25rem; font-weight: 800; letter-spacing: -0.04em; }
        .stat-blk span { font-size: 0.6rem; text-transform: uppercase; font-weight: 800; color: var(--text-muted); opacity: 0.6; }

        /* Timeline Rows - High Density */
        .history-item {
            display: flex; align-items: center; justify-content: space-between; padding: 1.15rem 1.5rem;
            border-bottom: 1px solid var(--border); background: var(--bg-card); transition: all 0.25s ease;
            border-radius: 16px; margin-bottom: 0.75rem; border: 1px solid var(--border);
            z-index: 5; position: relative;
        }
        .history-item:hover { border-color: var(--primary); transform: translateX(4px); background: var(--bg-hover); }
        .history-label { font-size: 0.95rem; font-weight: 700; color: var(--text-main); }
        .history-meta { font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--text-muted); }
        
        .status-badge {
            font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;
            padding: 4px 12px; border-radius: 50px; border: 1px solid var(--border);
        }
        .status-present { color: #10b981; background: rgba(16, 185, 129, 0.05); }
        .status-late { color: #f59e0b; background: rgba(245, 158, 11, 0.05); }
        .status-absent { color: #ef4444; background: rgba(239, 68, 68, 0.05); }

        @media (max-width: 992px) {
            .profile-container { grid-template-columns: 1fr; gap: 2rem; }
            .sidebar-monolith { position: static; }
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
                    <div class="avatar-circle"><?= strtoupper(substr($user['name'] ?? '?', 0, 1)) ?></div>
                    <h2 style="font-weight: 800; letter-spacing: -0.04em; margin: 0 0 0.5rem;"><?= htmlspecialchars($user['name']) ?></h2>
                    <p style="font-family: 'JetBrains Mono', monospace; color: var(--text-muted); font-size: 0.8rem; letter-spacing: 0.05em; margin: 0 0 1.5rem;"><?= $user['qr_code'] ?></p>
                    
                    <div style="display: flex; justify-content: center; gap: 8px; margin-bottom: 2rem;">
                        <span class="badge" style="background: rgba(29, 78, 216, 0.05); color: #1d4ed8; border: 1px solid rgba(29, 78, 216, 0.1);"><?= ($user['student_type'] ?? 'Regular') ?: 'Regular' ?></span>
                        <span class="badge" style="background: var(--bg-hover); color: var(--text-muted); border: 1px solid var(--border);"><?= ($user['year_level'] ?? '1st') ?: '1st' ?> Year</span>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button onclick="downloadQR()" class="btn btn-primary" style="justify-content: center; padding: 0.6rem; font-size: 0.8rem; border-radius: 50px;">Save QR</button>
                        <button onclick="openEditModal()" class="btn btn-ghost" style="justify-content: center; padding: 0.6rem; font-size: 0.8rem; border-radius: 50px;">Edit</button>
                    </div>
                </div>

                <div class="attendance-rank-box">
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

            <!-- Right Content -->
            <section>
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem;">
                    <div>
                        <h4 style="font-weight: 800; letter-spacing: -0.02em; margin: 0;">Attendance Timeline</h4>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 4px 0 0;">Last 10 recorded activities</p>
                    </div>
                    <a href="student_history.php?qr_code=<?= urlencode($qr) ?>" style="font-size: 0.8rem; font-weight: 800; color: var(--primary); text-decoration: none; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid var(--primary);">View Report</a>
                </div>

                <div class="scroll-list-container" style="background: var(--bg-main); border-radius: 20px; padding: 0.25rem;">
                    <div class="top-gradient"></div>
                    <div class="bottom-gradient"></div>
                    <div class="scroll-list no-scrollbar history-rows" style="max-height: 50vh; overflow-y: auto;">
                        <?php if(empty($history)): ?>
                            <div style="padding: 4rem; text-align: center; color: var(--text-muted); background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border);">
                                No activity detected yet.
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

                <div style="margin-top: 4rem; position: relative; z-index: 20;">
                    <h5 style="text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; margin-bottom: 1.5rem;">Contact Information</h5>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                        <div style="padding: 1.25rem; border-radius: 16px; border: 1px solid var(--border); background: var(--bg-card);">
                            <small style="color: var(--text-muted); font-weight: 800; font-size: 0.6rem; text-transform: uppercase; display: block; margin-bottom: 6px; letter-spacing: 0.05em;">Email Address</small>
                            <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-main); word-break: break-all;"><?= $user['email'] ?: '—' ?></div>
                        </div>
                        <div style="padding: 1.25rem; border-radius: 16px; border: 1px solid var(--border); background: var(--bg-card);">
                            <small style="color: var(--text-muted); font-weight: 800; font-size: 0.6rem; text-transform: uppercase; display: block; margin-bottom: 6px; letter-spacing: 0.05em;">Mobile Contact</small>
                            <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-main);"><?= $user['contact_number'] ?: '—' ?></div>
                        </div>
                    </div>
                </div>

            </section>

        </div>
    </main>

    <!-- Custom Edit Modal (Synced with Database Section) -->
    <div id="editModal" class="modal-overlay" onclick="if(event.target == this) closeEditModal()">
        <div class="modal-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h3 style="margin: 0; font-weight: 800; letter-spacing: -0.04em;">Edit Student Profile</h3>
                <button onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">

                <div class="swal-grid-2">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                    </div>
                    <div style="grid-column: span 2;">
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Middle Initial</label>
                        <input type="text" name="middle_initial" class="form-control" value="<?= htmlspecialchars($user['middle_initial'] ?? '') ?>" maxlength="2">
                    </div>
                </div>

                <div class="swal-grid-2">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Mobile Contact</label>
                        <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
                    </div>
                </div>

                <div class="swal-grid-2">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Course / Strand</label>
                        <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($user['course'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Section / Set</label>
                        <input type="text" name="section" class="form-control" value="<?= htmlspecialchars($user['section'] ?? '') ?>">
                    </div>
                </div>

                <div class="swal-grid-2">
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

                <div id="extendedFieldsProfile" style="margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 2rem;">
                     <h6 style="text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; margin-bottom: 1.5rem;">Additional Details</h6>
                     <div class="swal-grid-2">
                        <?php $p_bday = !empty($user['birthday']) ? date('Y-m-d', strtotime($user['birthday'])) : ''; ?>
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Birthday</label>
                            <input type="date" name="birthday" class="form-control" value="<?= htmlspecialchars($p_bday) ?>">
                        </div>
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Sex</label>
                            <select name="sex" class="form-control">
                                <option value="" disabled <?= empty($user['sex']) ? 'selected' : '' ?>>Select...</option>
                                <option value="Male" <?= (trim($user['sex'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= (trim($user['sex'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="swal-grid-2" style="margin-top: 1rem;">
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Civil Status</label>
                            <input type="text" name="civil_status" class="form-control" value="<?= htmlspecialchars($user['civil_status'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Religion</label>
                            <input type="text" name="religion" class="form-control" value="<?= htmlspecialchars($user['religion'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="swal-grid-2" style="margin-top: 1rem;">
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Citizenship</label>
                            <input type="text" name="citizenship" class="form-control" value="<?= htmlspecialchars($user['citizenship'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Place of Birth</label>
                            <input type="text" name="place_of_birth" class="form-control" value="<?= htmlspecialchars($user['place_of_birth'] ?? '') ?>">
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem;">
                        <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Birthday Thumbnail</label>
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <div id="p-bday-preview" style="width: 50px; height: 50px; background: var(--bg-main); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); overflow: hidden; border: 1px solid var(--border);">
                                <?php if(!empty($user['birthday_image'])): ?>
                                    <img src="<?= htmlspecialchars($user['birthday_image']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <i class="bi bi-image"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <input type="text" name="birthday_image" id="p-bday-img" class="form-control" style="margin-bottom: 5px;" value="<?= htmlspecialchars($user['birthday_image'] ?? '') ?>" placeholder="Image URL or upload...">
                                <input type="file" id="p-bday-upload" class="form-control" style="font-size: 0.75rem;" accept="image/*">
                            </div>
                        </div>
                        <small style="color: var(--text-muted); font-size: 0.7rem;">Optional image for automatic birthday greetings.</small>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 3rem; gap: 1rem;">
                    <button type="button" onclick="closeEditModal()" class="btn btn-ghost" style="border: 1px solid var(--border); padding: 0.8rem 2rem; border-radius: 12px; font-weight: 600;">Discard</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem; font-weight: 800; border-radius: 12px;">Save Changes</button>
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
                title: 'Profile Updated!',
                text: 'Student information has been successfully saved.',
                icon: 'success',
                confirmButtonColor: 'var(--primary)',
                confirmButtonText: 'Perfect'
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
            Toast.fire({ icon: 'success', title: 'Profile Updated Successfully' });
            window.history.replaceState({}, '', 'profile.php?qr=<?= $qr ?>');
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', () => {
            if (typeof initAnimatedList === 'function') {
                initAnimatedList('.history-rows');
            }
        });

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
