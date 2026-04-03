<?php
// admin_restore.php - Restore Missing Data from Backups
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

$backupDir = __DIR__ . '/backups';
$backups = glob($backupDir . '/*.zip');
rsort($backups); // Newest first

if (!class_exists('ZipArchive')) {
    die("Error: PHP ZipArchive extension is not enabled. Please enable 'extension=zip' in php.ini.");
}

// Clean up old temp files
$tempDir = __DIR__ . '/temp_restore';
if (file_exists($tempDir)) {
    // cleanup logic here if needed, or just let process handle it
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore Data | QR Tools</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .backup-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem; border-bottom: 1px solid var(--border);
            background: var(--bg-card);
        }
        .backup-item:first-child { border-top-left-radius: var(--radius-md); border-top-right-radius: var(--radius-md); }
        .backup-item:last-child { border-bottom: none; border-bottom-left-radius: var(--radius-md); border-bottom-right-radius: var(--radius-md); }
        
        .analysis-box {
            background: var(--bg-main); border: 1px solid var(--border); padding: 1.5rem;
            border-radius: var(--radius-md); margin-bottom: 2rem; display: none;
        }
        
        .analysis-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
        }

        @media (max-width: 600px) {
            .analysis-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="padding-top: 2rem; max-width: 800px;">
        
        <div style="margin-bottom: 2rem;">
            <h2><i class="bi bi-hdd-network"></i> Data Recovery</h2>
            <p style="color: var(--text-muted);">
                Recover data that was permanently deleted by finding it in old backups.
                <br><strong style="color: var(--warning);">Note:</strong> This will NOT overwrite current data. It only restores missing records.
            </p>
            <?php if (!class_exists('ZipArchive')): ?>
                <div style="background: #fff1f2; border: 1px solid #ffe4e6; padding: 1.5rem; border-radius: 12px; margin-top: 1rem; color: #991b1b;">
                    <h6 style="margin: 0 0 5px; font-weight: 800;">System Requirement Missing</h6>
                    <p style="margin: 0; font-size: 0.85rem; line-height: 1.4;">The <strong>PHP ZipArchive</strong> extension is not enabled on this server. Recovery from ZIP backups is currently disabled. Please enable <code>extension=zip</code> in your <code>php.ini</code> file.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Analysis Result -->
        <div id="analysisResult" class="analysis-box animate-fade-up">
            <h5 style="border-bottom: 1px solid #ddd; padding-bottom: 0.5rem; margin-bottom: 1rem;">Backup Analysis</h5>
            <div id="analysisContent"></div>
            <div style="margin-top: 1.5rem; text-align: right;">
                <button onclick="cancelAnalysis()" class="btn btn-secondary">Cancel</button>
                <button onclick="executeRestore()" class="btn btn-success" id="restoreBtn" disabled>
                    <i class="bi bi-arrow-counterclockwise"></i> Restore Found Items
                </button>
            </div>
        </div>

        <!-- Backup List -->
        <div class="card">
            <div class="card-header" style="background: #f1f5f9; font-weight: bold;">Available Backups</div>
            <?php if(empty($backups)): ?>
                <div style="padding: 2rem; text-align: center; color: var(--text-muted);">No backups found.</div>
            <?php else: ?>
                <div>
                    <?php foreach($backups as $file): 
                        $name = basename($file);
                        $size = round(filesize($file) / 1024, 2) . ' KB';
                        $date = date('M d, Y h:i A', filemtime($file));
                    ?>
                        <div class="backup-item">
                            <div>
                                <strong style="display: block; font-size: 1.1rem;"><?= $name ?></strong>
                                <small style="color: var(--text-muted);"><?= $date ?> &bullet; <?= $size ?></small>
                            </div>
                            <button onclick="analyzeBackup('<?= $name ?>')" class="btn btn-primary btn-sm">
                                <i class="bi bi-search"></i> Inspect
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        let currentBackup = '';

        function analyzeBackup(filename) {
            currentBackup = filename;
            Swal.fire({
                title: 'Analyzing Backup...',
                text: 'Comparing backup data with current database.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('api/restore_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'analyze', filename: filename })
            })
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if(data.status === 'success') {
                    showAnalysis(data);
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Network Error', 'error'));
        }

        function showAnalysis(data) {
            const box = document.getElementById('analysisResult');
            const content = document.getElementById('analysisContent');
            const btn = document.getElementById('restoreBtn');

            let html = `<div class="analysis-grid">`;
            
            // Users
            html += `
                <div style="background: var(--bg-card); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                    <h6 style="color: var(--primary);">Missing Users</h6>
                    <h2 style="margin: 0.5rem 0;">\${data.missing_users_count}</h2>
                    <small style="color: var(--text-muted);">Users found in backup but not in current DB.</small>
                </div>
            `;
            
            // Attendance
            html += `
                <div style="background: var(--bg-card); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                    <h6 style="color: var(--primary);">Missing Attendance</h6>
                    <h2 style="margin: 0.5rem 0;">\${data.missing_attendance_count}</h2>
                    <small style="color: var(--text-muted);">Records found in backup but not in current DB.</small>
                </div>
            `;
            html += \`</div>\`;

            if (data.missing_users_count === 0 && data.missing_attendance_count === 0) {
                html += `<div style="margin-top: 1rem; color: #15803d; font-weight: bold; text-align: center;">
                            <i class="bi bi-check-circle"></i> No missing data found. Current DB is up to date relative to this backup.
                         </div>`;
                btn.disabled = true;
            } else {
                 btn.disabled = false;
            }

            content.innerHTML = html;
            box.style.display = 'block';
            
            // Scroll to analysis
            box.scrollIntoView({ behavior: 'smooth' });
        }

        function cancelAnalysis() {
            document.getElementById('analysisResult').style.display = 'none';
        }

        function executeRestore() {
            Swal.fire({
                title: 'Confirm Restore',
                text: "This will insert the found missing records into your current database.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Restore',
                confirmButtonColor: '#10b981'
            }).then((result) => {
                if (result.isConfirmed) {
                     Swal.fire({
                        title: 'Restoring...',
                        didOpen: () => { Swal.showLoading(); }
                    });

                    fetch('api/restore_process.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'restore', filename: currentBackup })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.status === 'success') {
                            Swal.fire('Restored!', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }
    </script>

</body>
</html>
