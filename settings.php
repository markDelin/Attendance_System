<?php
// settings.php - Configuration
require 'includes/db.php';

// Fetch Subjects for Dropdown
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
    'registration_lock' => 0,
    'telegram_bot_token' => '',
    'telegram_group_id' => '',
    'admin_telegram_id' => '',
    'active_school_year' => 'SY 2024-2025',
    'sy_start_date' => '',
    'sy_end_date' => '',
    'store_name' => 'OFFICIAL STORE',
    'maintenance_mode' => 0,
    'birthday_image' => ''
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
    // 1. Preserve existing Ambagan settings
    $stmt = $pdo->query("SELECT billing_quota, billing_mode FROM settings LIMIT 1");
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    $billing_quota = $existing['billing_quota'] ?? 50; 
    $billing_mode = $existing['billing_mode'] ?? 'fixed'; 

    // 2. Prepare new settings
    $newSettings = [
        'call_time' => $_POST['call_time'],
        'grace_period' => $_POST['grace_period'],
        'absent_after' => $_POST['absent_after'],
        'time_in_out_enabled' => isset($_POST['time_in_out_enabled']) ? 1 : 0,
        'registration_lock' => isset($_POST['registration_lock']) ? 1 : 0,
        'telegram_bot_token' => $_POST['telegram_bot_token'] ?? '',
        'telegram_group_id' => $_POST['telegram_group_id'] ?? '',
        'admin_telegram_id' => $_POST['admin_telegram_id'] ?? '',
        'active_school_year' => $_POST['active_school_year'] ?? 'SY 2024-2025',
        'sy_start_date' => $_POST['sy_start_date'] ?? '',
        'sy_end_date' => $_POST['sy_end_date'] ?? '',
        'store_name' => $_POST['store_name'] ?? 'OFFICIAL STORE',
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
        'birthday_image' => $_POST['birthday_image'] ?? ''
    ];

    // 3. Update
    $pdo->exec("DELETE FROM settings");
    $stmt = $pdo->prepare("INSERT INTO settings (call_time, grace_period, absent_after, time_in_out_enabled, registration_lock, billing_quota, billing_mode, telegram_bot_token, telegram_group_id, admin_telegram_id, active_school_year, sy_start_date, sy_end_date, store_name, maintenance_mode, birthday_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([ 
        $newSettings['call_time'], 
        $newSettings['grace_period'], 
        $newSettings['absent_after'], 
        $newSettings['time_in_out_enabled'],
        $newSettings['registration_lock'],
        $billing_quota,
        $billing_mode,
        $newSettings['telegram_bot_token'],
        $newSettings['telegram_group_id'],
        $newSettings['admin_telegram_id'],
        $newSettings['active_school_year'],
        $newSettings['sy_start_date'],
        $newSettings['sy_end_date'],
        $newSettings['store_name'],
        $newSettings['maintenance_mode'],
        $newSettings['birthday_image']
    ]);
    
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
    <title>Settings | QR Tools</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(20px); }
        
        .setting-group {
            padding: 2rem 0;
            border-bottom: 1px solid var(--border);
        }
        .setting-group:last-child { border: none; }
        label { font-weight: 800; display: block; margin-bottom: 0.5rem; letter-spacing: -0.02em; font-size: 1rem; }
        small { color: var(--text-muted); display: block; font-weight: 500; font-size: 0.8rem; line-height: 1.4; }

        @media (max-width: 600px) {
            .card { padding: 1.5rem !important; }
            .setting-group {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 15px;
            }
            .setting-group > div {
                max-width: 100% !important;
            }
            input[type="time"], .switch {
                align-self: flex-start;
            }
            .bot-grid {
                grid-template-columns: 1fr !important;
                gap: 1.5rem !important;
            }
            .btn-primary {
                width: 100%;
                padding: 1rem !important;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="max-width: 800px; padding-top: 4rem;">
        
        <div class="animate-fade-up">
            <!-- Theme Configuration Card -->
            <div class="card" style="padding: 3rem; border-radius: 20px; margin-bottom: 2rem;">
                <h4 style="margin-bottom: 2rem; font-weight: 800; letter-spacing: -0.04em; border-bottom: 2px solid var(--border); padding-bottom: 1rem;">System Theme Configuration</h4>
                
                <div class="setting-group" style="border: none; padding-top: 0;">
                    <div class="mobile-force-stack" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="max-width: 70%;">
                            <label>Appearance Mode</label>
                            <small>Toggle between AMOLED Dark and Refined Light mode.</small>
                        </div>
                        <div class="nav-tabs" style="margin-bottom: 0;">
                            <button type="button" onclick="toggleTheme('light')" class="nav-link light-btn">LIGHT</button>
                            <button type="button" onclick="toggleTheme('dark')" class="nav-link dark-btn">AMOLED</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Active state for theme buttons
            function updateThemeButtons() {
                const isDark = document.documentElement.classList.contains('dark');
                document.querySelector('.dark-btn').classList.toggle('active', isDark);
                document.querySelector('.light-btn').classList.toggle('active', !isDark);
            }
            
            // Override toggle to update buttons
            const originalToggle = toggleTheme;
            window.toggleTheme = function(mode) {
                originalToggle(mode);
                updateThemeButtons();
            };
            
            document.addEventListener('DOMContentLoaded', updateThemeButtons);
        </script>
        
        <form method="POST" class="animate-fade-up">
            
            <div class="card" style="padding: 3rem; border-radius: 20px;">
                <h4 style="margin-bottom: 2rem; font-weight: 800; letter-spacing: -0.04em; border-bottom: 2px solid var(--border); padding-bottom: 1rem;">System Core Configuration</h4>

                <!-- Maintenance Mode -->
                <div class="setting-group" style="padding-top: 0;">
                   <div class="mobile-force-stack" style="display: flex; justify-content: space-between; align-items: center; border: 2px solid var(--danger); background: rgba(239, 68, 68, 0.05); padding: 1.5rem; border-radius: 16px;">
                        <div style="max-width: 80%;">
                            <label style="color: var(--danger);">Maintenance Mode</label>
                            <small>When enabled, the Scanner and Manual entry pages will be locked. Use this for data migration or system updates.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="maintenance_mode" <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                            <span class="slider" style="background-color: #ef4444;"></span>
                        </label>
                   </div>
                </div>

                <!-- Call Time -->
                <div class="setting-group">
                    <div class="mobile-force-stack" style="display:flex; justify-content: space-between; align-items: center;">
                        <div style="max-width: 60%;">
                            <label>Official Start Time</label>
                            <small>Sets the baseline for 8:00 AM 'Present' marks.</small>
                        </div>
                        <input type="time" name="call_time" class="form-control" style="width: auto; font-weight: 800; border-radius: 12px;" value="<?= $settings['call_time'] ?>">
                    </div>
                </div>

                <!-- Grace Period -->
                <div class="setting-group">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <label>Grace Period</label>
                        <span class="badge" style="background: var(--primary); color: white; padding: 6px 14px; border-radius: 8px;">
                            <span id="graceVal" style="font-weight: 800;"><?= $settings['grace_period'] ?></span> MINUTES
                        </span>
                    </div>
                    <input type="range" name="grace_period" min="0" max="60" value="<?= $settings['grace_period'] ?>" oninput="document.getElementById('graceVal').innerText = this.value">
                    <small>Maximum buffer time allowed before a student is flagged as 'Late'.</small>
                </div>

                <!-- Absent Threshold -->
                <div class="setting-group">
                    <div class="mobile-force-stack" style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <label>Hard Cut-off (Absent)</label>
                        <span class="badge" style="background: var(--bg-main); border: 2px solid var(--border); color: var(--text-main); padding: 6px 14px; border-radius: 8px;">
                            <span id="absentVal" style="font-weight: 800;"><?= $settings['absent_after'] ?></span> MINUTES
                        </span>
                    </div>
                    <input type="range" name="absent_after" min="0" max="120" value="<?= $settings['absent_after'] ?>" oninput="document.getElementById('absentVal').innerText = this.value">
                    <small>Time after grace period before students are automatically marked as 'Absent'.</small>
                </div>

                <!-- Registration Lock -->
                <div class="setting-group">
                   <div class="mobile-force-stack" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="max-width: 80%;">
                            <label>Security: Registration Lock</label>
                            <small>Strictly prevents unknown QR codes from being self-registered during scans. Useful for exams or finalized rosters.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="registration_lock" <?= $settings['registration_lock'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                   </div>
                </div>

                <!-- Time In/Out -->
                <div class="setting-group">
                    <div class="mobile-force-stack" style="display: flex; justify-content: space-between; align-items: center; border: none;">
                        <div style="max-width: 80%;">
                            <label>Workflow: Time In / Out Mode</label>
                            <small>Enables a secondary scan for students to clock out. If disabled, only initial arrival is recorded.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="time_in_out_enabled" <?= $settings['time_in_out_enabled'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- School Year Management & Promotion -->
            <div class="card" style="padding: 3rem; border-radius: 20px; margin-top: 2rem;">
                <h4 style="margin-bottom: 2rem; font-weight: 800; letter-spacing: -0.04em; border-bottom: 2px solid var(--border); padding-bottom: 1rem;">Academic Period & Promotion</h4>
                
                <div class="setting-group" style="padding-top: 0;">
                    <div class="mobile-force-stack" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="max-width: 60%;">
                            <label>Active School Year</label>
                            <small>Sets the current academic period. Records will be tagged with this value.</small>
                        </div>
                        <input type="text" name="active_school_year" class="form-control" style="width: auto; font-weight: 800; border-radius: 12px;" value="<?= htmlspecialchars($settings['active_school_year']) ?>" placeholder="e.g. SY 2024-2025">
                    </div>
                </div>

                <div class="setting-group">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <label>SY Start Date</label>
                            <input type="date" name="sy_start_date" class="form-control" style="border-radius: 12px; margin-top: 5px;" value="<?= $settings['sy_start_date'] ?>">
                            <small style="margin-top: 8px;">Official start of the academic year.</small>
                        </div>
                        <div>
                            <label>SY End Date</label>
                            <input type="date" name="sy_end_date" class="form-control" style="border-radius: 12px; margin-top: 5px;" value="<?= $settings['sy_end_date'] ?>">
                            <small style="margin-top: 8px;">Expected conclusion of classes.</small>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone: Promotion -->
                <div class="setting-group" style="border-top: 1px dashed var(--border); margin-top: 1rem; padding-top: 2rem; border-bottom: none;">
                    <div class="mobile-force-stack" style="display: flex; justify-content: space-between; align-items: center; background: rgba(239, 68, 68, 0.05); padding: 1.5rem; border-radius: 16px; border: 1px dashed rgba(239, 68, 68, 0.2);">
                        <div style="max-width: 70%;">
                            <label style="color: var(--danger);"><i class="bi bi-rocket-takeoff"></i> Advanced Promotion Hub</label>
                            <small>This will increment the year level of all <b>Regular Students</b> (1st→2nd, 2nd→3rd, 3rd→4th). 4th Year students will be marked as <b>Graduated</b>.</small>
                        </div>
                        <button type="button" onclick="promoteStudents()" class="btn hover-lift" style="background: var(--danger); color: white; border-radius: 12px; padding: 0.75rem 1.5rem; font-size: 0.8rem; border: none;">
                            PROMOTE ALL
                        </button>
                    </div>
                </div>
            </div>

            <!-- Store Branding & Integration Section -->
            <div class="card" style="padding: 3rem; border-radius: 20px; margin-top: 2rem; border-color: #f1f5f9; background: #fafafa;">
                <h4 style="margin-bottom: 2rem; font-weight: 800; letter-spacing: -0.04em;">Store & Integration Settings</h4>

                <div class="setting-group" style="padding-top: 0; border-bottom: 1px solid var(--border); margin-bottom: 2rem;">
                    <div class="mobile-force-stack" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="max-width: 60%;">
                            <label>Official Store Name</label>
                            <small>This branding appears on the Telegram bot and Web Store front.</small>
                        </div>
                        <input type="text" name="store_name" class="form-control" style="width: auto; font-weight: 800; border-radius: 12px;" value="<?= htmlspecialchars($settings['store_name'] ?? 'OFFICIAL STORE') ?>" placeholder="e.g. TECH SUPPLY HUB">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <label style="font-size: 0.75rem; text-transform: uppercase;">Bot Token</label>
                        <input type="text" name="telegram_bot_token" class="form-control" style="border-radius: 12px; margin-top: 5px;" value="<?= htmlspecialchars($settings['telegram_bot_token'] ?? '') ?>" placeholder="Paste token here">
                        <small style="margin-top: 8px;">Obtained from @BotFather.</small>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; text-transform: uppercase;">Channel/Group ID</label>
                        <input type="text" name="telegram_group_id" class="form-control" style="border-radius: 12px; margin-top: 5px;" value="<?= htmlspecialchars($settings['telegram_group_id'] ?? '') ?>" placeholder="-100xxxxxxx">
                        <small style="margin-top: 8px;">Destination for alerts.</small>
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <label style="font-size: 0.75rem; text-transform: uppercase;">Admin Account ID</label>
                    <input type="text" name="admin_telegram_id" class="form-control" style="border-radius: 12px; margin-top: 5px; max-width: 300px;" value="<?= htmlspecialchars($settings['admin_telegram_id'] ?? '') ?>" placeholder="Your numeric ID">
                    <small style="margin-top: 8px;">Enables remote data synchronization via Telegram.</small>
                </div>

                <div style="margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 2rem;">
                    <label style="font-size: 0.75rem; text-transform: uppercase;">Global Birthday Thumbnail</label>
                    <div style="display: flex; gap: 1rem; align-items: center; margin-top: 10px;">
                        <?php if(!empty($settings['birthday_image'])): ?>
                            <img src="<?= htmlspecialchars($settings['birthday_image']) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 12px; border: 2px solid var(--border);" id="bdayPreview">
                        <?php else: ?>
                            <div style="width: 60px; height: 60px; background: var(--bg-main); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 1.5rem;" id="bdayPreview">
                                <i class="bi bi-image"></i>
                            </div>
                        <?php endif; ?>
                        <div style="flex: 1;">
                            <input type="text" name="birthday_image" id="birthday_image_input" class="form-control" style="border-radius: 12px; margin-bottom: 8px;" value="<?= htmlspecialchars($settings['birthday_image'] ?? '') ?>" placeholder="Image URL or upload below">
                            <input type="file" id="bdayUpload" class="form-control" style="font-size: 0.8rem; border-radius: 12px;" accept="image/*">
                        </div>
                    </div>
                    <small style="margin-top: 8px;">A global image sent by the bot for all birthday greetings. If student-specific image is set, it will override this.</small>
                </div>
            </div>

            <!-- System Backup & Integrity -->
            <?php
            $backupFiles = glob('backups/*.{db,zip}', GLOB_BRACE);
            usort($backupFiles, function($a, $b) { return filemtime($b) - filemtime($a); });
            $lastBackup = !empty($backupFiles) ? date("M j, Y - h:i A", filemtime($backupFiles[0])) : "No backups found";
            ?>
            <div class="card" style="padding: 3rem; border-radius: 20px; margin-top: 2rem; border: 1px dashed var(--border);">
                <h4 style="margin-bottom: 2rem; font-weight: 800; letter-spacing: -0.04em;">System Integrity</h4>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <label>Database Integrity</label>
                        <small>Last Backup: <b style="color: var(--primary);"><?= $lastBackup ?></b></small>
                    </div>
                    <div>
                        <?php if(!empty($backupFiles)): ?>
                            <a href="backups/<?= basename($backupFiles[0]) ?>" class="btn btn-ghost btn-sm" style="font-weight: 800;"><i class="bi bi-download"></i> Latest File</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top: 3rem; text-align: right; padding-bottom: 4rem;">
                <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; border-radius: 50px; font-weight: 800; font-size: 1.1rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
                    <i class="bi bi-save"></i> Synchronize Settings
                </button>
            </div>

        </form>

    </main>

    <script>
        <?php if ($showSuccess): ?>
            Swal.fire({
                icon: 'success', title: 'System Updated', text: 'All configuration settings have been successfully synchronized across the system.',
                confirmButtonColor: '#000', border: 'none'
            });
        <?php endif; ?>

        async function promoteStudents() {
            const { value: confirmText } = await Swal.fire({
                title: 'Confirm Global Promotion',
                text: "This will advance ALL Regular students by one year. This action is bulk and irreversible. Type 'PROMOTE' to confirm.",
                input: 'text',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Execute Promotion',
                confirmButtonColor: 'var(--danger)',
                background: 'var(--bg-card)',
                color: 'var(--text-main)',
                inputValidator: (value) => {
                    if (value !== 'PROMOTE') {
                        return 'You must type PROMOTE to proceed!'
                    }
                }
            });

            if (confirmText === 'PROMOTE') {
                Swal.fire({
                    title: 'Promoting Students...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch('api/promote_students.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'promote_all' })
                    });
                    const res = await response.json();
                    
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Promotion Complete!',
                            text: res.message,
                            confirmButtonColor: 'var(--primary)'
                        });
                    } else {
                        throw new Error(res.error);
                    }
                } catch (e) {
                    Swal.fire('Error', e.message, 'error');
                }
            }
        }

        // Handle Birthday Image Upload
        document.getElementById('bdayUpload').addEventListener('change', async function(e) {
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
                    // ... success logic ...
                    document.getElementById('birthday_image_input').value = res.path;
                    const preview = document.getElementById('bdayPreview');
                    if (preview.tagName === 'IMG') {
                        preview.src = res.path;
                    } else {
                        const newImg = document.createElement('img');
                        newImg.src = res.path;
                        newImg.id = 'bdayPreview';
                        newImg.style = preview.style.cssText;
                        preview.replaceWith(newImg);
                    }
                    Toast.fire({ icon: 'success', title: 'Image uploaded successfully' });
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
