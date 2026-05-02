<?php
// view_subjects_list.php - List of Subjects to View Attendance
date_default_timezone_set("Asia/Manila");
require_once "includes/db.php";

// Fetch Category from URL
$category = $_GET['category'] ?? 'subject';

// Fetch Subjects/Events
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE category = ? ORDER BY is_active DESC, semester DESC, name ASC");
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
    <link href="assets/css/style.css" rel="stylesheet">
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
            border: 1px solid var(--border);
        }
        .item-table th {
            text-align: left;
            padding: 1rem;
            background: var(--bg-card);
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border);
        }
        .item-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        .item-table tr:hover { background: var(--bg-hover); }
        .item-table tr:last-child td { border-bottom: none; }
        
        .code-badge {
            font-family: monospace;
            font-size: 0.85rem;
            background: var(--bg-main);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--text-muted);
        }
        .sem-title {
            color: var(--text-main);
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            margin-top: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sem-title::after {
            content: ""; flex: 1; height: 1px; background: var(--border);
        }
        .view-toggle {
            display: inline-flex; background: var(--bg-main); padding: 4px; border-radius: 12px;
            margin-bottom: 2rem; border: 1px solid var(--border);
        }
        .toggle-btn {
            padding: 8px 20px; border-radius: 8px; text-decoration: none; color: var(--text-muted);
            font-weight: 600; font-size: 0.9rem; transition: all 0.2s; border: none; background: none; cursor: pointer;
        }
        .toggle-btn.active {
            background: var(--bg-card); color: var(--primary); box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .toggle-btn:hover:not(.active) { color: var(--text-main); background: var(--bg-hover); }
        
        .sy-title {
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            margin-top: 3rem;
            font-weight: 700;
            display: inline-block;
            box-shadow: var(--shadow-sm);
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container">
        
        <!-- View Toggle -->
        <div class="animate-fade-up">
            <div class="nav-tabs">
                <a href="view_subjects_list.php?category=subject" class="nav-link <?= $category === 'subject' ? 'active' : '' ?>">Academic Subjects</a>
                <a href="view_subjects_list.php?category=event" class="nav-link <?= $category === 'event' ? 'active' : '' ?>">Specific Events</a>
                <a href="view_attendance.php" class="nav-link">Daily Records</a>
            </div>
            
            <div class="mobile-force-stack" style="margin-bottom: 3rem; display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem; letter-spacing: -0.03em;"><?= $category === 'event' ? 'Event Records' : 'Subject Records' ?></h1>
                    <p style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.2;">
                        Select a <?= $category === 'event' ? 'event' : 'subject' ?> to view attendance logs.
                    </p>
                </div>
                <div style="flex-shrink: 0; display: flex; gap: 10px;">
                     <button onclick="exportAll()" class="btn btn-ghost btn-sm" style="padding: 0.75rem 1.25rem; font-weight: 800; border-radius: 12px; border: 1px solid var(--border);">
                        <i class="bi bi-collection"></i> Bulk Export
                     </button>
                     <button onclick="openAddDialog()" class="btn btn-primary btn-sm" style="padding: 0.75rem 1.5rem; font-weight: 800; border-radius: 12px;">
                        <i class="bi bi-plus-lg"></i> New <?= ucfirst($category) ?>
                     </button>
                </div>
            </div>
        </div>

        <?php if (empty($grouped)): ?>
            <div class="animate-fade-up" style="padding: 4rem; text-align: center; border: 1px dashed var(--border); border-radius: var(--radius-lg);">
                <i class="bi bi-journal-x" style="font-size: 2rem; color: var(--text-muted); display: block; margin-bottom: 1rem;"></i>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">No subjects found in the database.</p>
                <a href="groups.php" class="btn btn-primary" style="font-size: 0.9rem;">Manage Subjects</a>
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
                            <div class="scroll-list no-scrollbar" style="max-height: 400px; padding: 0.5rem 0;">
                                <table class="item-table mobile-card-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th style="width: 120px;" class="hide-mobile">Code</th>
                                    <th style="width: 120px; text-align: center;">Records</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subs as $s): 
                                    $count = $pdo->query("SELECT COUNT(*) FROM subject_attendance WHERE subject_id = {$s['id']}")->fetchColumn();
                                ?>
                                <tr class="animated-item" onclick="window.location.href='view_subject_attendance.php?id=<?= $s['id'] ?>'" style="cursor: pointer;">
                                    <td data-label="Name" style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($s['name']) ?></td>
                                    <td data-label="Code" class="hide-mobile"><span class="code-badge"><?= htmlspecialchars($s['code'] ?? 'CODE') ?></span></td>
                                    <td data-label="Records" style="text-align: center; font-weight: 600;"><?= $count ?></td>
                                    <td data-label="Link" style="text-align: right; color: var(--text-muted);"><i class="bi bi-chevron-right"></i></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                                </table>
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

        function openAddDialog() {
            Swal.fire({
                title: 'New ' + (currentCategory === 'event' ? 'Event' : 'Subject'),
                html: `
                    <div style="text-align: left; padding: 0.5rem;">
                        <div style="margin-bottom: 1rem;">
                            <label style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; display: block;">Name</label>
                            <input id="swal-name" class="form-control" placeholder="${currentCategory === 'event' ? 'e.g. Field Trip' : 'e.g. Math 101'}" style="border-radius: 12px;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <label style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; display: block;">School Year</label>
                                <input id="swal-sy" class="form-control" placeholder="2025-2026" style="border-radius: 12px;">
                            </div>
                            <div>
                                <label style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; display: block;">Semester</label>
                                <input id="swal-sem" class="form-control" placeholder="1, 2, etc." style="border-radius: 12px;">
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Create ' + ucfirst(currentCategory),
                cancelButtonText: 'Cancel',
                confirmButtonColor: 'var(--primary)',
                preConfirm: () => {
                    const name = document.getElementById('swal-name').value;
                    const sy = document.getElementById('swal-sy').value;
                    const sem = document.getElementById('swal-sem').value;
                    if (!name || (!sy && currentCategory === 'subject') || !sem) {
                        Swal.showValidationMessage('Please fill all required fields');
                        return false;
                    }
                    return [name, sy, sem];
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const [name, sy, sem] = result.value;
                    fetch('api/subject_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ 
                            action: 'add_subject', 
                            name: name, 
                            school_year: sy,
                            semester: sem,
                            category: currentCategory
                        })
                    })
                    .then(r => r.json())
                    .then(d => {
                        if(d.status === 'success') {
                            Toast.fire({ icon: 'success', title: d.message }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error', d.message, 'error');
                        }
                    });
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
