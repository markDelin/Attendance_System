<?php
// recycle_bin.php - Restore Deleted Users
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

// Fetch DELETED users
$stmt = $pdo->query("SELECT * FROM users WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
$deletedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define Navbar Actions
$navbar_actions = '
    <a href="admin_restore.php" class="btn-icon" title="Advanced Recovery">
        <i class="bi bi-hdd-network" style="font-size: 0.95rem;"></i>
    </a>
';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin | QR Tools</title>
    <link href="assets/css/style.css?v=1.3" rel="stylesheet">
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
            background: var(--bg-card); 
            border: 1px solid var(--border); 
            border-radius: 20px; 
            padding: 1.75rem;
            position: relative; 
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: var(--shadow-neu-out-sm);
        }
        .recycle-card:hover { 
            transform: translateY(-3px); 
            border-color: #ef4444; 
            box-shadow: 0 15px 30px rgba(239, 68, 68, 0.08); 
        }
        .recycle-card::before {
            content: ''; 
            position: absolute; 
            top: 0; left: 0; 
            width: 100%; height: 5px; 
            background: linear-gradient(to right, #ef4444, #f43f5e);
            opacity: 0.85;
        }
        .btn-restore-glass {
            flex: 1; 
            justify-content: center; 
            border-radius: 12px; 
            font-weight: 800; 
            border: 1px solid #10b981 !important; 
            color: #10b981 !important;
            background: transparent !important;
            transition: all 0.2s;
        }
        .btn-restore-glass:hover {
            background: #10b981 !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transform: translateY(-1px);
        }
        .btn-purge-danger {
            flex: 1; 
            justify-content: center; 
            border-radius: 12px; 
            background: #ef4444 !important; 
            border: none !important; 
            font-weight: 800;
            color: white !important;
            transition: all 0.2s;
        }
        .btn-purge-danger:hover {
            background: #dc2626 !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="padding-top: 3rem;">
        <div class="mobile-force-stack animate-fade-up" style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem;">
            <div>
                <h1 style="margin: 0; font-weight: 900; letter-spacing: -0.05em; color: var(--danger); font-family: 'Outfit', sans-serif;">Recycle Bin</h1>
                <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin-top: 4px;">Review and restore recently deleted student dossiers.</p>
            </div>
            <div style="font-size: 0.72rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; background: var(--bg-card); padding: 8px 16px; border-radius: 50px; border: 1px solid var(--border); white-space: nowrap; width: fit-content; box-shadow: var(--shadow-neu-out-sm);">
                <?= count($deletedUsers) ?> DELETED Dossiers
            </div>
        </div>

        <?php if(empty($deletedUsers)): ?>
            <div class="glass-panel flex-center animate-fade-up" style="padding: 6rem 2rem; border-style: dashed; border-radius: 24px; color: var(--text-muted); border-width: 2px;">
                <i class="bi bi-trash" style="font-size: 4rem; opacity: 0.15; margin-bottom: 1.5rem; color: var(--danger);"></i>
                <h3 style="color: var(--text-main); font-weight: 900; margin-bottom: 0.5rem; letter-spacing: -0.02em; font-family:'Outfit', sans-serif;">Archive is Empty</h3>
                <p style="font-weight: 500; font-size: 0.85rem;">Deleted student profiles will appear here for temporary storage.</p>
            </div>
        <?php else: ?>
            <div class="recycle-grid">
                <?php 
                $idx = 0;
                foreach($deletedUsers as $user): 
                    $staggerClass = 'stagger-' . (($idx % 8) + 1);
                    $idx++;
                ?>
                    <div class="recycle-card interactive-glow <?= $staggerClass ?> animate-fade-up">
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="margin: 0 0 8px; font-weight: 800; letter-spacing: -0.02em; font-family:'Outfit', sans-serif;"><?= htmlspecialchars($user['name']) ?></h4>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <span style="font-family: monospace; font-size: 0.75rem; color: var(--text-muted);">QR Code: <?= htmlspecialchars($user['qr_code']) ?></span>
                                <span style="font-size: 0.72rem; font-weight: 800; color: #f43f5e; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="bi bi-calendar-x"></i> Deleted <?= date('M d, Y', strtotime($user['deleted_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.75rem;">
                            <button onclick="restoreUser('<?= $user['qr_code'] ?>', '<?= htmlspecialchars($user['name']) ?>')" 
                                    class="btn btn-restore-glass">
                                <i class="bi bi-arrow-counterclockwise"></i> Restore
                            </button>
                            <button onclick="permDelete('<?= $user['qr_code'] ?>', '<?= htmlspecialchars($user['name']) ?>')" 
                                    class="btn btn-purge-danger">
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
                    Swal.fire({
                        title: action === 'restore' ? 'Restored!' : 'Purged!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: 'var(--primary)'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    </script>
</body>
</html>
