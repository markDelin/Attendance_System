<?php
// profile.php - Student Profile V2
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

$qr = $_GET['qr'] ?? '';
if (empty($qr)) header('Location: manual.php');

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name']);
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, birthday = ?, email = ? WHERE qr_code = ?");
        $stmt->execute([$name, $birthday, $email, $qr]);
        header("Location: profile.php?qr=$qr&msg=saved");
        exit;
    } catch (Exception $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}

// Fetch User
$stmt = $pdo->prepare("SELECT * FROM users WHERE qr_code = ?");
$stmt->execute([$qr]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die("User not found.");

// Fetch Attendance Stats
$totalClasses = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE qr_code = ?");
$totalClasses->execute([$qr]);
$totalClasses = $totalClasses->fetchColumn();

$present = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE qr_code = ? AND status = 'present'");
$present->execute([$qr]);
$present = $present->fetchColumn();

$late = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE qr_code = ? AND status = 'late'");
$late->execute([$qr]);
$late = $late->fetchColumn();

$absent = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE qr_code = ? AND status = 'absent'");
$absent->execute([$qr]);
$absent = $absent->fetchColumn();

$rate = $totalClasses > 0 ? round((($present + $late) / $totalClasses) * 100) : 0;

// Fetch Recent Activity
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE qr_code = ? ORDER BY date DESC, time DESC LIMIT 20");
$stmt->execute([$qr]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['name']) ?> | Profile</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .stat-card {
            background: white; padding: 1.5rem; border-radius: var(--radius-md);
            border: 1px solid var(--border); text-align: center;
        }
        .stat-val { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .stat-label { color: var(--text-muted); font-size: 0.9rem; }
        
        .history-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem; border-bottom: 1px solid var(--border);
            background: white;
        }
        .history-item:last-child { border: none; }
        
        .info-group { margin-bottom: 1rem; text-align: left; }
        .info-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 0.25rem; }
        .info-value { font-size: 1rem; color: var(--text-main); }
        .info-placeholder { color: var(--text-muted); font-style: italic; font-size: 0.9rem; }

        /* Modal Styles */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;
        }
        .modal-content {
            background-color: #fff; padding: 2rem; border-radius: var(--radius-lg);
            width: 90%; max-width: 500px; position: relative;
            box-shadow: var(--shadow-lg); animation: slideUp 0.3s ease;
        }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="manual.php" class="btn btn-ghost" style="border: none; padding-left: 0;">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <h3 class="text-gradient">Profile</h3>
        <div style="width: 40px;"></div>
    </nav>

    <main class="container" style="max-width: 800px; padding-top: 2rem;">
        
        <!-- Header & Info -->
        <div class="card animate-fade-up" style="padding: 2rem; text-align: center; margin-bottom: 2rem; position: relative;">
            
            <button onclick="openEditModal()" class="btn btn-ghost" style="position: absolute; top: 1rem; right: 1rem; color: var(--primary);">
                <i class="bi bi-pencil-square"></i> Edit
            </button>

            <div style="width: 80px; height: 80px; background: var(--bg-main); border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: var(--text-muted);">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            
            <h2 style="margin-bottom: 0.5rem;"><?= htmlspecialchars($user['name']) ?></h2>
            <small style="color: var(--text-muted); font-family: monospace; display: block; margin-bottom: 1.5rem;"><?= $qr ?></small>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; max-width: 500px; margin: 0 auto; text-align: left; background: var(--bg-main); padding: 1.5rem; border-radius: var(--radius-md);">
                <div class="info-group">
                    <span class="info-label">Birthday</span>
                    <?php if (!empty($user['birthday'])): ?>
                        <div class="info-value"><?= date('F j, Y', strtotime($user['birthday'])) ?></div>
                    <?php else: ?>
                        <div class="info-placeholder">Not set</div>
                    <?php endif; ?>
                </div>
                <div class="info-group">
                    <span class="info-label">Email Address</span>
                    <?php if (!empty($user['email'])): ?>
                        <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                    <?php else: ?>
                        <div class="info-placeholder">Not set</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <span class="badge" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 0.5rem 1rem; font-size: 0.9rem;">
                    Attendance Rate: <?= $rate ?>%
                </span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="animate-fade-up delay-1" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-val" style="color: var(--success);"><?= $present ?></div>
                <div class="stat-label">Present</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: var(--warning);"><?= $late ?></div>
                <div class="stat-label">Late</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: var(--danger);"><?= $absent ?></div>
                <div class="stat-label">Absent</div>
            </div>
        </div>

        <!-- History -->
        <h4 style="margin-bottom: 1rem;">Recent Activity</h4>
        <div class="card animate-fade-up delay-2" style="overflow: hidden;">
            <?php if (empty($history)): ?>
                <div style="padding: 2rem; text-align: center; color: var(--text-muted);">No records found.</div>
            <?php else: ?>
                <?php foreach ($history as $h): ?>
                    <div class="history-item">
                        <div>
                            <div style="font-weight: 500;"><?= date('F j, Y', strtotime($h['date'])) ?></div>
                            <small style="color: var(--text-muted);">
                                <?= $h['status'] === 'absent' ? '--' : date('h:i A', strtotime($h['time'])) ?>
                            </small>
                        </div>
                        <span class="badge badge-<?= $h['status'] ?>" style="
                            <?php 
                                if($h['status']=='present') echo 'background:#dcfce7; color:#166534;'; 
                                elseif($h['status']=='late') echo 'background:#ffedd5; color:#d97706;'; 
                                else echo 'background:#fee2e2; color:#dc2626;'; 
                            ?>
                        "><?= ucfirst($h['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <form method="POST" class="modal-content animate-fade-up">
            <input type="hidden" name="action" value="update_profile">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
                <h3>Edit Profile</h3>
                <button type="button" onclick="closeEditModal()" style="background:none; border:none; font-size: 1.2rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Birthday (Optional)</label>
                <input type="date" name="birthday" class="form-control" value="<?= $user['birthday'] ?? '' ?>">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Email Address (Optional)</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="student@example.com">
            </div>

            <div style="text-align: right;">
                <button type="button" onclick="closeEditModal()" class="btn btn-ghost" style="margin-right: 0.5rem;">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <script>
        function openEditModal() {
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close on outside click
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
        }

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Profile Updated',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
            // Clear URL param
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[\?&]msg=saved/, ''));
        <?php endif; ?>
    </script>
</body>
</html>
