<?php
// view_subjects_list.php - List of Subjects to View Attendance
date_default_timezone_set("Asia/Manila");
require_once "includes/db.php";

// Fetch Category from URL
$category = $_GET['category'] ?? 'subject';

// Fetch Subjects/Events with dynamic schedule subquery
$stmt = $pdo->prepare("
    SELECT s.*, 
           (SELECT GROUP_CONCAT(day_of_week || ' ' || start_time || '-' || end_time, '||') 
            FROM schedules 
            WHERE subject_id = s.id) as schedule_list
    FROM subjects s
    WHERE s.category = ? 
    ORDER BY s.is_active DESC, s.semester DESC, s.name ASC
");
$stmt->execute([$category]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach ($items as $s) {
    if ($category === 'event') {
        $grouped['Special Events']['Events'][] = $s;
    } else {
        $sy = $s['school_year'] ?: 'No School Year';
        $sem = $s['semester'] ?: 'No Semester';
        $grouped[$sy][$sem][] = $s;
    }
}
ksort($grouped); // Sort by SY
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Records | Attendance System</title>
    <link href="assets/css/style.css?v=1.3" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <?php include 'includes/theme_loader.php'; ?>
    <link rel="stylesheet" href="assets/css/AnimatedList.css">
    <script src="assets/js/AnimatedList.js"></script>
    <style>
        .item-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
            border-radius: var(--radius-md);
            overflow: hidden;
            border: none;
            box-shadow: var(--shadow-neu-out-sm);
        }
        .item-table th {
            text-align: left;
            padding: 0.85rem 1.25rem;
            background: var(--bg-card);
            color: var(--text-muted);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 2px solid var(--bg-main);
            font-weight: 800;
        }
        .item-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--bg-main);
            font-size: 0.88rem;
        }
        .item-table tr { transition: all 0.15s; }
        .item-table tr:hover { background: var(--bg-main); }
        .item-table tr:last-child td { border-bottom: none; }
        
        .code-badge {
            font-family: monospace;
            font-size: 0.75rem;
            background: var(--bg-main);
            padding: 3px 8px;
            border-radius: 6px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .sem-title {
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1rem;
            margin-top: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sem-title::after {
            content: ""; flex: 1; height: 1px; background: color-mix(in srgb, var(--text-muted) 10%, transparent);
        }

        .nav-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding: 5px;
        }
        .nav-link {
            padding: 0.65rem 1.1rem;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: var(--bg-card);
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.2s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .nav-link i { font-size: 0.85rem; opacity: 0.6; }
        .nav-link.active { box-shadow: var(--shadow-neu-in-sm); color: var(--primary); }
        .nav-link.active i { opacity: 1; }

        .sy-title {
            background: var(--primary);
            color: white;
            padding: 0.6rem 1.25rem;
            border-radius: 12px;
            margin-top: 2.5rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 25%, transparent);
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container">
        
        <!-- View Toggle -->
        <div class="animate-fade-up">
            <div class="nav-tabs">
                <a href="view_subjects_list.php?category=subject" class="nav-link <?= $category === 'subject' ? 'active' : '' ?> hover-press"><i class="bi bi-book"></i> Academic Subjects</a>
                <a href="view_subjects_list.php?category=event" class="nav-link <?= $category === 'event' ? 'active' : '' ?> hover-press"><i class="bi bi-calendar-event"></i> Specific Events</a>
                <a href="view_attendance.php" class="nav-link hover-press"><i class="bi bi-calendar-check"></i> Daily Records</a>
            </div>
            
            <div class="mobile-force-stack" style="margin-bottom: 3rem; display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem; letter-spacing: -0.03em;"><?= $category === 'event' ? 'Event Records' : 'Subject Records' ?></h1>
                    <p style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.2;">
                        Select a <?= $category === 'event' ? 'event' : 'subject' ?> to view attendance logs.
                    </p>
                </div>
                 <div style="flex-shrink: 0; display: flex; gap: 10px;">
                      <button onclick="exportAll()" class="btn btn-ghost" style="width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; border: 1px solid var(--border); background: var(--bg-card);" title="Bulk Export">
                         <i class="bi bi-collection" style="font-size: 1.1rem; color: var(--text-muted);"></i>
                      </button>
                </div>
            </div>
        </div>

        <?php if (empty($grouped)): ?>
            <div class="animate-fade-up" style="padding: 4rem; text-align: center; border: 1px dashed var(--border); border-radius: var(--radius-lg);">
                <i class="bi bi-journal-x" style="font-size: 2rem; color: var(--text-muted); display: block; margin-bottom: 1rem;"></i>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">No subjects found in the database.</p>
                <a href="subjects.php" class="btn btn-primary" style="font-size: 0.9rem;">Manage Subjects in Subject Portal</a>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $sy => $semesters): ?>
                <div class="sy-title animate-fade-up"><?= htmlspecialchars($sy) ?></div>
                <?php foreach ($semesters as $sem => $subs): ?>
                    <div>
                        <div class="sem-title"><?= htmlspecialchars($sem) ?></div>
                        <div class="scroll-list-container">
                            <div class="top-gradient"></div>
                            <div class="bottom-gradient"></div>
                            <div class="scroll-list no-scrollbar" style="max-height: calc(75vh - 120px); padding: 0.5rem 0.25rem;">
                                <?php foreach ($subs as $s): 
                                    $count = $pdo->query("SELECT COUNT(*) FROM subject_attendance WHERE subject_id = {$s['id']}")->fetchColumn();
                                ?>
                                <div class="animated-item hover-lift" onclick="window.location.href='view_subject_attendance.php?id=<?= $s['id'] ?>'" 
                                     style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 1.1rem 1.25rem; border-bottom: 1px solid color-mix(in srgb, var(--border) 40%, transparent); transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); background: var(--bg-card); border-radius: 18px; border: 1px solid var(--border); margin-bottom: 0.75rem; box-shadow: var(--shadow-neu-out-sm);">
                                    <div style="flex: 1; min-width: 0; padding-right: 12px;">
                                        <p style="margin: 0; font-weight: 800; font-size: 0.95rem; color: var(--text-main); display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-family:'Outfit', sans-serif; line-height: 1.2;">
                                            <?= htmlspecialchars($s['name']) ?>
                                            <?php if (!empty($s['code'])): ?>
                                                <span style="font-family:'JetBrains Mono', monospace; font-size: 0.65rem; background: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary); padding: 2px 7px; border-radius: 6px; font-weight: 700;"><?= htmlspecialchars($s['code']) ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <div style="display: flex; gap: 12px; align-items: center; margin-top: 5px; flex-wrap: wrap;">
                                            <?php if (!empty($s['room'])): ?>
                                                <span style="font-size: 0.68rem; color: var(--text-muted); font-weight: 600; display: inline-flex; align-items: center; gap: 2px;"><i class="bi bi-geo-alt" style="font-size:0.72rem;"></i><?= htmlspecialchars($s['room']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($s['lecturer'])): ?>
                                                <span style="font-size: 0.68rem; color: var(--text-muted); font-weight: 600; display: inline-flex; align-items: center; gap: 2px;"><i class="bi bi-person" style="font-size:0.72rem;"></i><?= htmlspecialchars($s['lecturer']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($s['schedule_list'])): ?>
                                            <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px;">
                                                <?php foreach (explode('||', $s['schedule_list']) as $sched): ?>
                                                    <span style="font-family:'JetBrains Mono', monospace; font-size: 0.62rem; background: color-mix(in srgb, var(--primary) 5%, transparent); color: var(--text-main); border: 1px solid color-mix(in srgb, var(--primary) 10%, transparent); padding: 2px 7px; border-radius: 30px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                        <i class="bi bi-clock" style="font-size:0.68rem; color:var(--primary);"></i>
                                                        <?= htmlspecialchars($sched) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; gap: 1rem; flex-shrink: 0;">
                                        <div style="text-align: right; min-width: 42px;">
                                            <span style="font-size: 1.15rem; font-weight: 850; color: var(--primary); display: block; font-family:'Outfit', sans-serif; line-height: 1;"><?= $count ?></span>
                                            <span style="font-size: 0.55rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-top: 2px;">Logs</span>
                                        </div>
                                        <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--primary) 6%, transparent); color: var(--primary); font-size: 0.85rem; border: 1px solid color-mix(in srgb, var(--primary) 10%, transparent);">
                                            <i class="bi bi-chevron-right"></i>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
<?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const currentCategory = '<?= $category ?>';
        const Toast = Swal.mixin({
            toast: true, position: 'bottom-end', showConfirmButton: false,
            timer: 2000, timerProgressBar: true
        });

        function exportAll() {
            Swal.fire({
                title: 'Bulk Export All',
                html: `
                    <div style="text-align:left; padding: 0.5rem;">
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1.5rem;">Generate a matrix report for all active subjects.</p>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                            <div>
                                <label style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">Start Date</label>
                                <input type="date" id="bulk-start" class="form-control" value="<?= date('Y-m-d') ?>" style="border-radius:12px; margin-top:4px;">
                            </div>
                            <div>
                                <label style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">End Date</label>
                                <input type="date" id="bulk-end" class="form-control" value="<?= date('Y-m-d') ?>" style="border-radius:12px; margin-top:4px;">
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Export Now',
                confirmButtonColor: 'var(--primary)',
                preConfirm: () => {
                    return [
                        document.getElementById('bulk-start').value,
                        document.getElementById('bulk-end').value
                    ]
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const [start, end] = result.value;
                    window.open(`api/export_all_subjects.php?start=${start}&end=${end}&format=xls`, '_blank');
                }
            });
        }

        function ucfirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        // Initialize Animated List
        document.addEventListener('DOMContentLoaded', () => {
            initAnimatedList('.scroll-list-container');
        });
    </script>
</body>
</html>
