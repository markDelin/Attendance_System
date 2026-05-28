<?php
// view_attendance.php - Records
date_default_timezone_set("Asia/Manila");
require_once "includes/db.php";

// Fetch Distinct Dates with Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Count total unique dates for pagination links
$totalDates = $pdo->query("SELECT COUNT(DISTINCT date) FROM attendance")->fetchColumn();
$totalPages = ceil($totalDates / $limit);

// Fetch Only dates with pre-calculated counts and notification status
$stmt = $pdo->prepare("
    SELECT 
        DISTINCT date,
        (SELECT COUNT(*) FROM attendance WHERE date = a.date) as record_count,
        (SELECT 1 FROM notified_contexts WHERE subject_id = 0 AND date = a.date LIMIT 1) as is_notified
    FROM attendance a
    ORDER BY date DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$limit, $offset]);
$dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Available School Years for Filter
$sy_list_raw = $pdo->query("SELECT DISTINCT school_year FROM attendance WHERE school_year IS NOT NULL ORDER BY school_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$active_sy = $_GET['sy'] ?? ($pdo->query("SELECT active_school_year FROM settings LIMIT 1")->fetchColumn() ?: 'SY 2024-2025');
// Always include the active SY from settings in the list
$sy_list = $sy_list_raw;
if (!in_array($active_sy, $sy_list)) {
    array_unshift($sy_list, $active_sy);
}

// Handle Filtered Counts & Dates
$where_sy = $active_sy ? "WHERE school_year = '$active_sy'" : "";
$totalDates = $pdo->query("SELECT COUNT(DISTINCT date) FROM attendance $where_sy")->fetchColumn();
$totalPages = ceil($totalDates / $limit);

$stmt = $pdo->prepare("
    SELECT 
        DISTINCT date,
        (SELECT COUNT(*) FROM attendance WHERE date = a.date AND (school_year = ? OR school_year IS NULL)) as record_count,
        (SELECT 1 FROM notified_contexts WHERE subject_id = 0 AND date = a.date LIMIT 1) as is_notified
    FROM attendance a
    WHERE school_year = ? OR school_year IS NULL
    ORDER BY date DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$active_sy, $active_sy, $limit, $offset]);
$dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Records | QR Tools by MCK</title>
    <link href="assets/css/style.css?v=1.4" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <link rel="stylesheet" href="assets/css/AnimatedList.css">
    <script src="assets/js/AnimatedList.js"></script>
    <style>
        .table-wrapper {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-neu-out-sm);
        }
        table { width: 100%; border-collapse: collapse; }
        th { 
            text-align: left; padding: 1.1rem 1.25rem;
            background: var(--bg-hover); color: var(--text-muted); 
            font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em;
            border-bottom: 1px solid var(--border);
            font-weight: 800;
        }
        td { padding: 0.95rem 1.25rem; border-bottom: 1px solid var(--border); font-size: 0.88rem; color: var(--text-main); }
        tr:last-child td { border-bottom: none; }
        tr { transition: background-color 0.2s; }
        tr:hover td { background-color: var(--bg-hover) !important; }
        
        .date-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .date-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-neu-out-lg);
            border-color: rgba(59, 130, 246, 0.2);
        }
 
        .date-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
 
        .date-header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
 
        .date-day-badge {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: color-mix(in srgb, var(--primary) 8%, transparent);
            color: var(--primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.06);
            border: 1px solid color-mix(in srgb, var(--primary) 12%, transparent);
        }
        .date-day-badge .day-num {
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1;
            font-family: 'Outfit', sans-serif;
        }
        .date-day-badge .day-abbr {
            font-size: 0.55rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.8;
        }
 
        /* Pagination Styles */
        .pagination {
            display: flex; justify-content: center; align-items: center; gap: 1rem;
            margin-top: 3rem; margin-bottom: 4rem;
        }
        .pagination-btn {
            padding: 0.7rem 1.5rem; 
            border-radius: 50px; 
            border: 1px solid var(--border);
            color: var(--text-main); 
            font-weight: 800; 
            font-size: 0.72rem; 
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: all 0.2s; 
            background: var(--bg-card);
            box-shadow: var(--shadow-neu-out-sm);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pagination-btn:hover:not(:disabled) { 
            box-shadow: var(--shadow-neu-out-lg);
            color: var(--primary); 
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        .pagination-btn:disabled { opacity: 0.35; cursor: not-allowed; box-shadow: none; }
        .page-info { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; }
 
        .nav-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding: 5px;
        }
 
        .nav-link {
            padding: 0.7rem 1.25rem;
            border-radius: 14px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--bg-card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.2s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
        }
 
        .nav-link i {
            font-size: 0.95rem;
            opacity: 0.7;
        }
 
        .nav-link.active {
            box-shadow: var(--shadow-neu-in-sm);
            color: var(--primary);
            border-color: var(--primary);
            background: var(--bg-hover);
        }
        .nav-link.active i { opacity: 1; }
 
        .filter-select {
            font-weight: 800;
            font-family: 'Outfit', 'Inter', sans-serif;
            border-radius: 50px;
            font-size: 0.78rem;
            padding: 0.55rem 1rem 0.55rem 1rem;
            cursor: pointer;
            background: var(--bg-card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-out-sm);
            color: var(--text-main);
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.85rem center;
            padding-right: 2rem;
            min-width: 130px;
            transition: all 0.2s var(--ease-out-expo);
            letter-spacing: 0.01em;
        }
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 15%, transparent);
        }
        .filter-select:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-neu-out);
        }
        .filter-select option {
            background: var(--bg-card);
            color: var(--text-main);
            font-weight: 700;
            padding: 6px;
        }
        .filter-label {
            font-size: 0.6rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>

    <?php 
    $navbar_actions = '
        <a href="settings.php" class="btn btn-ghost" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; border: 1px solid var(--border); background: var(--bg-card);" title="Settings">
            <i class="bi bi-gear" style="font-size: 0.95rem; color: var(--text-muted);"></i>
        </a>
        <button onclick="exportRange()" class="btn btn-ghost" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; border: 1px solid var(--border); background: var(--bg-card);" title="Export Range">
            <i class="bi bi-calendar-range" style="font-size: 0.95rem; color: var(--text-muted);"></i>
        </button>
        <button onclick="exportAllSubjects()" class="btn btn-ghost" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; border: 1px solid var(--border); background: var(--bg-card);" title="Bulk Export">
            <i class="bi bi-collection" style="font-size: 0.95rem; color: var(--text-muted);"></i>
        </button>
    ';
    include 'includes/navbar.php'; 
    ?>

    <main class="container">
        <!-- View Toggle -->
        <div class="animate-fade-up">
            <div class="nav-tabs">
                <a href="view_subjects_list.php" class="nav-link hover-press"><i class="bi bi-book"></i> Subject Records</a>
                <a href="view_attendance.php" class="nav-link active hover-press"><i class="bi bi-calendar-check"></i> Daily Records</a>
                <a href="groups.php" class="nav-link hover-press"><i class="bi bi-diagram-3"></i> Groups</a>
                <a href="calendar.php" class="nav-link hover-press"><i class="bi bi-calendar3"></i> Calendar</a>
            </div>
            
            <div class="mobile-force-stack" style="margin-bottom: 3rem; display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem;">
                <div>
                    <h1 style="margin-bottom: 0.5rem; letter-spacing: -0.03em;">Daily Records</h1>
                    <p style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.2;">
                        View attendance logs grouped by date.
                    </p>
                </div>
                <div style="flex-shrink: 0; display: flex; gap: 1rem; align-items: flex-end;">
                     <div style="text-align: right;">
                        <label class="filter-label">Academic Year</label>
                        <select onchange="window.location.href='?sy='+this.value" class="filter-select" title="Filter by School Year">
                            <?php foreach ($sy_list as $sy): ?>
                                <option value="<?= htmlspecialchars($sy) ?>" <?= $active_sy == $sy ? 'selected' : '' ?>><?= htmlspecialchars($sy) ?></option>
                            <?php endforeach; ?>
                        </select>
                     </div>
                     <button onclick="window.location.href='scan.php'" class="btn btn-primary" style="width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; border: none;" title="Open Scanner">
                        <i class="bi bi-qr-code-scan" style="font-size: 1.1rem;"></i>
                     </button>
                </div>
            </div>
        </div>

        <?php if (empty($dates)): ?>
            <div style="padding: 4rem; text-align: center; border: 1px dashed var(--border); border-radius: var(--radius-lg);">
                <i class="bi bi-calendar-x" style="font-size: 2rem; color: var(--text-muted); display: block; margin-bottom: 1rem;"></i>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">No attendance records found.</p>
                <a href="scan.php" class="btn btn-primary" style="font-size: 0.9rem;">Start Scanning</a>
            </div>
        <?php else: ?>

            <!-- Daily Attendance Metrics Bar -->
            <?php
            // Calculate total records for this school year
            $recordsCount = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE school_year = ? OR school_year IS NULL");
            $recordsCount->execute([$active_sy]);
            $totalCount = $recordsCount->fetchColumn();
            
            // Calculate unique logs dates
            $uniqueDates = $totalDates;
            ?>
            <div class="animate-fade-up" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2.25rem;">
                <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-neu-out-sm);">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div>
                        <span style="font-size: 1.45rem; font-weight: 900; color: var(--text-main); display: block; font-family:'Outfit', sans-serif; line-height: 1.1;"><?= $uniqueDates ?></span>
                        <span style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-top: 2px;">Logged Days</span>
                    </div>
                </div>
                <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-neu-out-sm);">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: color-mix(in srgb, #10b981 10%, transparent); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <span style="font-size: 1.45rem; font-weight: 900; color: var(--text-main); display: block; font-family:'Outfit', sans-serif; line-height: 1.1;"><?= $totalCount ?></span>
                        <span style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-top: 2px;">Total Entries</span>
                    </div>
                </div>
                <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-neu-out-sm);">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: color-mix(in srgb, #f59e0b 10%, transparent); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.88rem; font-weight: 800; color: var(--text-main); display: block; line-height: 1.25;"><?= htmlspecialchars($active_sy) ?></span>
                        <span style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-top: 2px;">Active Year</span>
                    </div>
                </div>
            </div>

            <?php foreach ($dates as $idx => $row): ?>
                <div class="date-card animate-fade-up hover-lift" style="animation-delay: <?= $idx * 0.1 ?>s">
                    
                    <div class="date-header">
                        <div class="date-header-left">
                            <div class="date-day-badge">
                                <span class="day-num"><?= date('j', strtotime($row['date'])) ?></span>
                                <span class="day-abbr"><?= date('D', strtotime($row['date'])) ?></span>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.02em;">
                                    <?= date('F j, Y', strtotime($row['date'])) ?>
                                </h3>
                                <span style="font-size: 0.68rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;">
                                    <?= $row['record_count'] ?> Entries
                                </span>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 0.4rem; align-items: center;">
                            <?php if ($row['is_notified']): ?>
                                <span style="font-size: 0.65rem; color: var(--text-muted); padding: 0.25rem 0.6rem; border: 1px solid var(--border); border-radius: 4px; display: flex; align-items: center; gap: 4px; font-weight: 800;">
                                    <i class="bi bi-check2-all"></i> SENT
                                </span>
                            <?php else: ?>
                                <button onclick="notifyAbsentees('<?= $row['date'] ?>')" class="btn btn-ghost" style="color: var(--primary); font-size: 0.65rem; padding: 0.25rem 0.6rem; border: 1px solid var(--border); border-radius: 4px; font-weight: 800; text-transform: uppercase;">
                                    Notify
                                </button>
                            <?php endif; ?>

                            <button onclick="exportDay('<?= $row['date'] ?>')" class="btn btn-ghost" style="font-size: 0.8rem; padding: 0; height: 28px; width: 28px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border: 1px solid var(--border);" title="Export">
                                <i class="bi bi-download"></i>
                            </button>
                            
                            <button onclick="confirmDelete('date', '<?= $row['date'] ?>')" 
                                    class="btn btn-ghost" style="color: var(--danger); font-size: 0.8rem; padding: 0; height: 28px; width: 28px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border: 1px solid var(--border);" title="Clear All">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="scroll-list-container">
                        <div class="top-gradient"></div>
                        <div class="bottom-gradient"></div>
                        <div class="scroll-list no-scrollbar" style="max-height: 400px; padding: 0.5rem 0;">
                            <div class="table-wrapper">
                                <table class="mobile-card-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%;">Name</th>
                                            <th style="width: 25%;">Time</th>
                                            <th style="width: 25%;">Status</th>
                                            <th style="width: 10%; text-align: right;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                         $records = $pdo->prepare("SELECT a.id, a.time, a.status, u.name 
                                                              FROM attendance a 
                                                              JOIN users u ON a.qr_code = u.qr_code 
                                                              WHERE a.date = ? AND (a.school_year = ? OR a.school_year IS NULL)
                                                              ORDER BY a.time DESC");
                                         $records->execute([$row['date'], $active_sy]);
                                         $records = $records->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($records as $r): 
                                        ?>
                                        <tr class="hover-lift animated-item">
                                            <td data-label="Name" style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($r['name']) ?></td>
                                            <td data-label="Time" style="color: var(--text-muted); font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">
                                                <?= $r['status'] == 'absent' ? '--' : date('h:i A', strtotime($r['time'] ?? '')) ?>
                                            </td>
                                            <td data-label="Status">
                                                <button onclick="toggleStatus(<?= $r['id'] ?>, 'global', this)" class="badge <?= $r['status'] ?>" style="border:1px solid var(--border); font-size: 0.75rem; padding: 0.4rem 0.8rem; border-radius: 8px; cursor: pointer; min-width: 80px; font-weight: 800; text-transform: uppercase;">
                                                    <?= $r['status'] ?>
                                                </button>
                                            </td>
                                            <td data-label="Action" style="text-align: right;">
                                                <button onclick="confirmDelete('id', <?= $r['id'] ?>)" style="background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 0.5rem; transition: color 0.2s;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination Navigation -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination animate-fade-up">
            <button onclick="window.location.href='?page=<?= $page - 1 ?>&sy=<?= $active_sy ?>'" 
                    class="pagination-btn hover-press" <?= $page <= 1 ? 'disabled' : '' ?>>
                <i class="bi bi-chevron-left"></i> Prev
            </button>
            <div class="page-info">Page <?= $page ?> of <?= $totalPages ?></div>
            <button onclick="window.location.href='?page=<?= $page + 1 ?>&sy=<?= $active_sy ?>'" 
                    class="pagination-btn hover-press" <?= $page >= $totalPages ? 'disabled' : '' ?>>
                Next <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
    <?php include 'includes/footer.php'; ?>

    <script>
        function exportRange() {
            Swal.fire({
                title: 'Export Attendance',
                html: `
                    <div style="text-align:left; padding: 0.5rem;">
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1.5rem;">Select date range for aggregated matrix export.</p>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                            <div>
                                <label style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">Start Date</label>
                                <input type="date" id="swal-start" class="form-control" value="<?= date('Y-m-d') ?>" style="border-radius:12px; margin-top:4px;">
                            </div>
                            <div>
                                <label style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">End Date</label>
                                <input type="date" id="swal-end" class="form-control" value="<?= date('Y-m-d') ?>" style="border-radius:12px; margin-top:4px;">
                            </div>
                        </div>
                        
                        <label style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; display:block; margin-bottom:8px;">Format</label>
                        <div style="display:flex; gap:15px; flex-wrap:wrap;">
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="xls" checked style="margin-right:8px; accent-color:var(--primary);"> 
                                Excel (.xls)
                            </label>
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="csv" style="margin-right:8px; accent-color:var(--primary);"> 
                                Raw CSV (.csv)
                            </label>
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="html" style="margin-right:8px; accent-color:var(--primary);"> 
                                Google Sheets
                            </label>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Download',
                confirmButtonColor: 'var(--primary)',
                preConfirm: () => {
                    return [
                        document.getElementById('swal-start').value,
                        document.getElementById('swal-end').value,
                        document.querySelector('input[name="swal-format"]:checked').value
                    ]
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const [start, end, format] = result.value;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'api/export.php';
                    form.target = '_blank';
                    
                    const startInput = document.createElement('input');
                    startInput.name = 'start_date';
                    startInput.value = start;
                    form.appendChild(startInput);

                    const endInput = document.createElement('input');
                    endInput.name = 'end_date';
                    endInput.value = end;
                    form.appendChild(endInput);

                    const formatInput = document.createElement('input');
                    formatInput.name = 'format';
                    formatInput.value = format;
                    form.appendChild(formatInput);

                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                }
            })
        }

        function exportDay(date) {
            Swal.fire({
                title: 'Export Day',
                html: `
                    <div style="text-align:left; padding: 0.5rem;">
                        <p style="margin-bottom:15px">Exporting records for <b>${date}</b></p>
                        <label style="font-size:0.7rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; display:block; margin-bottom:8px;">Format</label>
                        <div style="display:flex; gap:15px; flex-wrap:wrap;">
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="xls" checked style="margin-right:8px; accent-color:var(--primary);"> 
                                Excel (.xls)
                            </label>
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="csv" style="margin-right:8px; accent-color:var(--primary);"> 
                                Raw CSV
                            </label>
                            <label style="display:flex; align-items:center; cursor:pointer; font-weight:600; font-size:0.9rem;">
                                <input type="radio" name="swal-format" value="html" style="margin-right:8px; accent-color:var(--primary);"> 
                                Web View
                            </label>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Download',
                confirmButtonColor: 'var(--primary)',
                preConfirm: () => {
                    return document.querySelector('input[name="swal-format"]:checked').value;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const format = result.value;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'api/export.php';
                    form.target = '_blank';
                    
                    const startInput = document.createElement('input');
                    startInput.name = 'start_date';
                    startInput.value = date;
                    form.appendChild(startInput);

                    const formatInput = document.createElement('input');
                    formatInput.name = 'format';
                    formatInput.value = format;
                    form.appendChild(formatInput);

                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                }
            })
        }

        function exportAllSubjects() {
            Swal.fire({
                title: 'Bulk Subject Export',
                html: `
                    <div style="text-align:left; padding: 0.5rem;">
                        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1.5rem;">Download all subject records in one file.</p>
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
                confirmButtonText: 'Generate Bulk Export',
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

        function notifyAbsentees(date) {
            Swal.fire({
                title: 'Notify Absentees?',
                text: `This will mark all students who did NOT scan on ${date} as ABSENT and notify the Telegram GC.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--primary)',
                confirmButtonText: 'Yes, Notify Now',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const formData = new FormData();
                    formData.append('subject_id', '0'); // Daily Mode
                    formData.append('date', date);
                    return fetch('api/mark_absentees.php', { method: 'POST', body: formData })
                           .then(r => r.json())
                           .then(d => {
                               if (d.status !== 'success') throw new Error(d.message);
                               return d;
                           }).catch(error => {
                               Swal.showValidationMessage(`Request failed: ${error}`);
                           });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Success',
                        text: result.value.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
        }

        function confirmDelete(type, value) {
            let title = type === 'date' ? 'Delete All Records?' : 'Delete Record?';
            let text = type === 'date' ? `This will wipe all attendance data for ${value}` : 'This record will be permanently removed.';
            
            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const formData = new FormData();
                    formData.append(type, value);
                    return fetch('api/delete.php', { method: 'POST', body: formData })
                           .then(r => r.json())
                           .catch(() => { return { status: 'success' }; }); // Fallback for redirecting script
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: 'The record has been removed.',
                        icon: 'success',
                        confirmButtonColor: 'var(--primary)'
                    }).then(() => location.reload());
                }
            })
        }

        function toggleStatus(id, type, btnElement) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('type', type);
            
            // Show subtle saving state
            const originalText = btnElement.innerText;
            btnElement.innerText = '...';
            
            fetch('api/update_attendance_status.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        btnElement.innerText = d.new_status.charAt(0).toUpperCase() + d.new_status.slice(1);
                        btnElement.className = `badge ${d.new_status}`;
                        // Success toast
                        Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 1500}).fire({icon: 'success', title: 'Status Updated'});
                    } else {
                        btnElement.innerText = originalText;
                        Swal.fire('Error', d.message, 'error');
                    }
                }).catch(err => {
                    btnElement.innerText = originalText;
                    Swal.fire('Error', err.message, 'error');
                });
        }

        // Initialize Animated List
        document.addEventListener('DOMContentLoaded', () => {
            initAnimatedList('.scroll-list-container');
        });
    </script>
</body>
</html>
