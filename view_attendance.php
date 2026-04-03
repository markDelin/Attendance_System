<?php
// view_attendance.php - Records
date_default_timezone_set("Asia/Manila");
require_once "includes/db.php";

// Fetch dates
$stmt = $pdo->query("SELECT DISTINCT date FROM attendance ORDER BY date DESC");
$dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .table-wrapper {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        table { width: 100%; border-collapse: collapse; }
        th { 
            text-align: left; padding: 1.25rem; 
            background: #f8fafc; color: var(--text-muted); 
            font-size: 0.85rem; text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }
        td { padding: 1.25rem; border-bottom: 1px solid var(--border); }
        tr:last-child td { border-bottom: none; }
        
        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem; margin-top: 3rem;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="btn btn-ghost" style="border: none; padding-left: 0;">
            <i class="bi bi-arrow-left"></i> <span class="d-none-mobile">Dashboard</span>
        </a>
        <h3 class="text-gradient">Records</h3>
        <a href="settings.php" class="btn btn-ghost">
            <i class="bi bi-gear"></i> <span class="d-none-mobile">Settings</span>
        </a>
        <button onclick="exportRange()" class="btn btn-ghost">
            <i class="bi bi-calendar-range"></i> <span class="d-none-mobile">Export Range</span>
        </button>
    </nav>

    <main class="container">
        <?php if (empty($dates)): ?>
            <div class="card animate-fade-up" style="padding: 4rem; text-align: center; margin-top: 2rem;">
                <i class="bi bi-database-exclamation" style="font-size: 3rem; color: var(--text-muted); display: block; margin-bottom: 1rem;"></i>
                <h4 style="color: var(--text-muted);">No records found</h4>
            </div>
        <?php else: ?>

            <?php foreach ($dates as $index => $row): ?>
                <div class="animate-fade-up delay-<?= min($index + 1, 3) ?>">
                    
                    <div class="section-header">
                        <div class="flex-center" style="gap: 1rem;">
                            <h4 style="margin: 0; color: var(--text-main);"><?= date('F j, Y', strtotime($row['date'])) ?></h4>
                            <span class="badge" style="background: white; border: 1px solid var(--border);">
                                <?php
                                $count = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = '{$row['date']}'")->fetchColumn();
                                echo "$count Entries";
                                ?>
                            </span>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem;">
                             <form method="post" action="api/export.php" target="_blank">
                                <input type="hidden" name="export_date" value="<?= $row['date'] ?>">
                                <button type="submit" class="btn btn-ghost" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                                    <i class="bi bi-download"></i> Export
                                </button>
                            </form>
                            
                            <button onclick="confirmDelete('date', '<?= $row['date'] ?>')" 
                                    class="btn btn-ghost" style="color: var(--danger); font-size: 0.85rem; padding: 0.5rem 1rem;">
                                <i class="bi bi-trash"></i> Clear
                            </button>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th style="text-align: right;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $records = $pdo->query("SELECT a.id, a.time, a.status, u.name 
                                                      FROM attendance a 
                                                      JOIN users u ON a.qr_code = u.qr_code 
                                                      WHERE a.date = '{$row['date']}' 
                                                      ORDER BY a.time DESC")->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($records as $r): 
                                ?>
                                <tr>
                                    <td style="font-weight: 500; color: var(--text-main);"><?= htmlspecialchars($r['name']) ?></td>
                                    <td style="color: var(--text-muted);"><?= $r['status'] == 'absent' ? '--' : date('h:i A', strtotime($r['time'])) ?></td>
                                    <td>
                                        <span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <button onclick="confirmDelete('id', <?= $r['id'] ?>)" style="background: none; border: none; cursor: pointer; color: var(--text-muted);">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script>
        function exportRange() {
            Swal.fire({
                title: 'Export Attendance',
                html: `
                    <div style="text-align:left">
                        <label>Start Date</label>
                        <input type="date" id="swal-start" class="swal2-input" value="<?= date('Y-m-d') ?>">
                        <label>End Date</label>
                        <input type="date" id="swal-end" class="swal2-input" value="<?= date('Y-m-d') ?>">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Download',
                preConfirm: () => {
                    return [
                        document.getElementById('swal-start').value,
                        document.getElementById('swal-end').value
                    ]
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const [start, end] = result.value;
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

                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                }
            })
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
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `api/delete.php?${type}=${value}`;
                }
            })
        }
    </script>
</body>
</html>