<?php
// settings.php - Configuration
require 'includes/db.php';

// Auto-Migration for new columns (Safe to run multiple times)
$missingCols = ['am_in', 'am_out', 'pm_in', 'pm_out'];
$defaults = ['08:00', '11:00', '13:00', '16:00'];
foreach($missingCols as $i => $col) {
    try {
        $pdo->exec("ALTER TABLE settings ADD COLUMN $col VARCHAR(5) DEFAULT '{$defaults[$i]}'");
    } catch (PDOException $e) { /* Ignore if exists */ }
}


// Defaults
$defaultSettings = [ 
    'call_time' => '08:00', 
    'grace_period' => 20, 
    'absent_after' => 30, 
    'time_in_out_enabled' => 1,
    'registration_lock' => 0
];

try {
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    if ($dbSettings = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings = array_merge($defaultSettings, $dbSettings);
    } else {
        $settings = $defaultSettings;
    }
} catch (PDOException $e) { $settings = $defaultSettings; }

$showSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Preserve existing Ambagan settings (or default if missing/crashed)
    $stmt = $pdo->query("SELECT billing_quota, billing_mode FROM settings LIMIT 1");
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    $billing_quota = $existing['billing_quota'] ?? 50; // Default 50 if lost
    $billing_mode = $existing['billing_mode'] ?? 'fixed'; // Default fixed if lost

    // 2. Prepare new settings
    $newSettings = [
        'call_time' => $_POST['call_time'],
        'grace_period' => $_POST['grace_period'],
        'absent_after' => $_POST['absent_after'],
        'time_in_out_enabled' => isset($_POST['time_in_out_enabled']) ? 1 : 0,
        'registration_lock' => isset($_POST['registration_lock']) ? 1 : 0,
    ];

    // 3. Update (Delete + Insert ensures clean state)
    $pdo->exec("DELETE FROM settings");
    $stmt = $pdo->prepare("INSERT INTO settings (call_time, grace_period, absent_after, time_in_out_enabled, registration_lock, billing_quota, billing_mode) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([ 
        $newSettings['call_time'], 
        $newSettings['grace_period'], 
        $newSettings['absent_after'], 
        $newSettings['time_in_out_enabled'],
        $newSettings['registration_lock'],
        $billing_quota,
        $billing_mode
    ]);
    
    // Refetch to get the latest state
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $showSuccess = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        input[type=range] {
            width: 100%; margin: 1rem 0;
            accent-color: var(--primary);
        }
        .switch {
            position: relative; display: inline-block; width: 44px; height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1; transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(20px); }
        
        .setting-group {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--border);
        }
        .setting-group:last-child { border: none; }
        label { font-weight: 600; display: block; margin-bottom: 0.25rem; }
        small { color: var(--text-muted); display: block; }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="btn btn-ghost" style="border: none; padding-left: 0;">
            <i class="bi bi-arrow-left"></i> <span class="d-none-mobile">Dashboard</span>
        </a>
        <h3 class="text-gradient">Settings</h3>
        <div style="width: 40px;"></div>
    </nav>

    <main class="container" style="max-width: 700px; padding-top: 3rem;">
        
        <form method="POST" class="card animate-fade-up" style="padding: 2.5rem;">
            
            <!-- Call Time -->
            <div class="setting-group">
                <div class="flex-center" style="justify-content: space-between;">
                    <div>
                        <label>Start Time</label>
                        <small>Official class start time.</small>
                    </div>
                    <input type="time" name="call_time" class="form-control" style="width: auto;" value="<?= $settings['call_time'] ?>">
                </div>
            </div>

            <!-- Grace Period -->
            <div class="setting-group">
                <div class="flex-center" style="justify-content: space-between; margin-bottom: 0.5rem;">
                    <label>Grace Period</label>
                    <span class="badge" style="background: var(--bg-main); border: 1px solid var(--border);">
                        <span id="graceVal"><?= $settings['grace_period'] ?></span> mins
                    </span>
                </div>
                <input type="range" name="grace_period" min="0" max="60" value="<?= $settings['grace_period'] ?>" oninput="document.getElementById('graceVal').innerText = this.value">
                <small>Time allowed before marking as 'Late'.</small>
            </div>

            <!-- Absent Threshold -->
            <div class="setting-group">
                <div class="flex-center" style="justify-content: space-between; margin-bottom: 0.5rem;">
                    <label>Absent Threshold</label>
                    <span class="badge" style="background: var(--bg-main); border: 1px solid var(--border);">
                        <span id="absentVal"><?= $settings['absent_after'] ?></span> mins
                    </span>
                </div>
                <input type="range" name="absent_after" min="0" max="120" value="<?= $settings['absent_after'] ?>" oninput="document.getElementById('absentVal').innerText = this.value">
                <small>Time after grace period before marking as 'Absent'.</small>
            </div>

            </div>



            <!-- Features -->
            <div class="setting-group" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <label>Registration Lock</label>
                    <small>Prevent new QR codes from being registered.</small>
                </div>
                <label class="switch">
                    <input type="checkbox" name="registration_lock" <?= $settings['registration_lock'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-group" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <label>Review Mode (Time In/Out)</label>
                    <small>Enable clocking out.</small>
                </div>
                <label class="switch">
                    <input type="checkbox" name="time_in_out_enabled" <?= $settings['time_in_out_enabled'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>

            </div>



            <div style="margin-top: 2rem; text-align: right;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save Changes
                </button>
            </div>

        </form>

    </main>

    <!-- Data Management Removed -->

    <?php if ($showSuccess): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Settings Saved',
            showConfirmButton: false,
            timer: 1500
        });
    </script>
    <?php endif; ?>

</body>
</html>