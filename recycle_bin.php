<?php
// recycle_bin.php - Restore Deleted Users
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

// Fetch DELETED users
$stmt = $pdo->query("SELECT * FROM users WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
$deletedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define Navbar Actions
$navbar_actions = '
    <a href="admin_restore.php" class="btn btn-ghost" style="width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; padding:0;" title="Advanced Recovery">
        <i class="bi bi-hdd-network"></i>
    </a>
';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin | QR Tools</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .recycle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            padding-top: 1rem;
            padding-bottom: 4rem;
        }
        .recycle-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 1.75rem;
            position: relative; transition: all 0.2s;
        }
        .recycle-card:hover { transform: translateY(-2px); border-color: var(--danger); box-shadow: 0 10px 20px -10px rgba(239, 68, 68, 0.1); }
        .recycle-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: #ef4444; opacity: 0.1;
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="padding-top: 3rem;">
        <div class="mobile-force-stack" style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem;">
            <div>
                <h1 style="margin: 0; font-weight: 800; letter-spacing: -0.05em; color: var(--danger);">Recycle Bin</h1>
                <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500; margin-top: 4px;">Review and restore recently deleted student records.</p>
            </div>
            <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; background: var(--bg-main); padding: 6px 12px; border-radius: 50px; border: 1px solid var(--border); white-space: nowrap; width: fit-content;">
                <?= count($deletedUsers) ?> DELETED RECORDS
            </div>
        </div>

        <?php if(empty($deletedUsers)): ?>
            <div class="card flex-center animate-fade-up" style="padding: 6rem 2rem; border-style: dashed; border-radius: 20px; color: var(--text-muted);">
                <i class="bi bi-trash3" style="font-size: 4rem; opacity: 0.1; margin-bottom: 1.5rem;"></i>
                <h3 style="color: var(--text-muted); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: -0.02em;">Archive is Empty</h3>
                <p>Deleted students will appear here for 30 days before permanent removal.</p>
            </div>
        <?php else: ?>
            <div class="recycle-grid animate-fade-up">
                <?php foreach($deletedUsers as $user): ?>
                    <div class="recycle-card">
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="margin: 0 0 8px; font-weight: 800; letter-spacing: -0.02em;"><?= htmlspecialchars($user['name']) ?></h4>
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <span style="font-family: monospace; font-size: 0.8rem; color: var(--text-muted);">ID: <?= htmlspecialchars($user['qr_code']) ?></span>
                                <span style="font-size: 0.75rem; font-weight: 700; color: var(--danger); text-transform: uppercase; letter-spacing: 0.05em;">
                                    <i class="bi bi-calendar-x"></i> Deleted <?= date('M d, Y', strtotime($user['deleted_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.75rem;">
                            <button onclick="restoreUser('<?= $user['qr_code'] ?>', '<?= htmlspecialchars($user['name']) ?>')" 
                                    class="btn btn-ghost" style="flex:1; justify-content: center; border-radius: 12px; font-weight: 700; border-color: #10b981; color: #166534;">
                                <i class="bi bi-arrow-counterclockwise"></i> Restore
                            </button>
                            <button onclick="permDelete('<?= $user['qr_code'] ?>', '<?= htmlspecialchars($user['name']) ?>')" 
                                    class="btn btn-danger" style="flex:1; justify-content: center; border-radius: 12px; background: #ef4444; border: none; font-weight: 700;">
                                <i class="bi bi-x-circle"></i> Purge
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });

        function restoreUser(id, name) {
            Swal.fire({
                title: 'Restore Student?', text: `Restore ${name} to the active database?`, icon: 'question',
                showCancelButton: true, confirmButtonText: 'Yes, Restore', confirmButtonColor: '#10b981', cancelButtonColor: '#f8fafc', cancelButtonText: '<span style="color:#64748b">Cancel</span>'
            }).then((res) => { if(res.isConfirmed) processAction('restore', id); });
        }

        function permDelete(id, name) {
            Swal.fire({
                title: 'Permanent Removal?', text: `Critical: This will destroy all attendance records for ${name}. This action is irreversible.`, icon: 'warning',
                showCancelButton: true, confirmButtonText: 'Purge Student', confirmButtonColor: '#ef4444', cancelButtonColor: '#f8fafc', cancelButtonText: '<span style="color:#64748b">Keep in Bin</span>'
            }).then((res) => { if(res.isConfirmed) processAction('permanent_delete', id); });
        }

        function processAction(action, id) {
            fetch('api/manage_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: action, qr_code: id })
            })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    Toast.fire({ icon: 'success', title: data.message });
                    setTimeout(() => location.reload(), 1000);
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    </script>
</body>
</html>
