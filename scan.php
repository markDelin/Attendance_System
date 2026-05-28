<?php
// scan.php - Simple Student QR Scanner (Modal Version)
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

// Get settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$callTime = $settings['call_time'] ?? '08:00';
$isMaintenance = ($settings['maintenance_mode'] ?? 0) == 1;

if ($isMaintenance) {
    echo "<!DOCTYPE html><html><head><title>System Maintenance</title><link href='assets/css/style.css' rel='stylesheet'><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body style='background:var(--bg-main); display:flex; align-items:center; justify-content:center; height:100vh; font-family:sans-serif;'>";
    echo "<script>Swal.fire({icon:'info', title:'System Maintenance', text:'The scanner is currently locked for maintenance. Please try again later.', showConfirmButton:false, allowOutsideClick:false, footer:'<a href=\"index.php\">Back to Dashboard</a>'});</script>";
    echo "</body></html>";
    exit;
}

// Fetch Today's Attendance for List
$todayParams = date('Y-m-d');
$sql = "SELECT 
            u.name, 
            a.time, 
            a.status 
        FROM attendance a 
        LEFT JOIN users u ON a.qr_code = u.qr_code 
        WHERE a.date = :date 
        ORDER BY a.time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':date' => $todayParams]);
$todaysRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalToday = count($todaysRecords);

// Fetch Subjects & Events
$stmt = $pdo->query("SELECT id, name, semester, category FROM subjects WHERE is_active = 1 ORDER BY category ASC, semester DESC, name ASC");
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
$groupedContexts = ['subject' => [], 'event' => []];
foreach ($subjects as $s) {
    if ($s['category'] === 'event') {
        $groupedContexts['event']['Special Events'][] = $s;
    } else {
        $groupedContexts['subject'][$s['semester']][] = $s;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Scanner | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="assets/js/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        /* Modal Styles matching Billing */
        #scannerModal {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .scanner-content {
            background: var(--bg-card);
            width: 95%; max-width: 420px;
            padding: 2rem;
            border-radius: var(--radius-lg);
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border);
        }
        .modal-close-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg-main);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-size: 1rem;
        }
        .modal-close-btn:hover {
            background: var(--bg-hover);
            color: var(--danger);
            border-color: var(--danger);
            transform: scale(1.05);
        }

        /* Scan Button Pulse */
        .scan-hero-btn {
            padding: 0.8rem 2.5rem;
            font-size: 1.05rem;
            border-radius: 50px;
            box-shadow: 0 8px 24px -4px color-mix(in srgb, var(--primary) 40%, transparent);
            position: relative;
            overflow: hidden;
        }
        .scan-hero-btn::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: inherit;
            border: 2px solid var(--primary);
            opacity: 0;
            animation: scanPulse 2s infinite;
        }
        @keyframes scanPulse {
            0% { opacity: 0.6; transform: scale(1); }
            100% { opacity: 0; transform: scale(1.15); }
        }

        .context-card {
            padding: 1rem 1.25rem;
            border-radius: 16px;
            background: var(--bg-card);
            max-width: 400px;
            margin: 0 auto;
            box-shadow: var(--shadow-neu-out-sm);
        }

        /* Mobile Adjustments */
        @media (max-width: 400px) {
            .scanner-content {
                padding: 0.75rem;
                width: 98%;
            }
            #reader {
                min-height: 250px;
            }
        }
        
    </style>
</head>
<body>

    <!-- Nav -->
    <?php 
    $show_clock = true;
    include 'includes/navbar.php'; 
    ?>

    <main class="container" style="padding-top: 1rem;">
        
        <!-- Main Actions -->
        <div style="text-align: center; margin-bottom: 2rem;" class="animate-fade-up">
            <div class="glass-panel" style="padding: 2rem 1.5rem; border-radius: 24px; margin-bottom: 1.5rem;">
                <div class="flex-center" style="flex-direction: column; gap: 12px;">
                    <div style="width: 70px; height: 70px; background: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.25rem;">
                        <i class="bi bi-qr-code-scan" style="font-size: 2rem;"></i>
                    </div>
                    <button onclick="toggleScanner()" class="btn btn-primary scan-hero-btn">
                        Scan Now
                    </button>
                    <p style="font-size: 0.78rem; color: var(--text-muted); font-weight: 500; margin: 0;">Tap to begin capturing student QR codes</p>
                </div>
            </div>

            <div class="context-card">
                <label for="scanSubject" style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-bottom: 0.6rem;">Active Context</label>
                <select id="scanSubject" class="form-control" style="width: 100%; border-radius: 12px; height: 44px; box-shadow: var(--shadow-neu-in-sm); border: none; background: var(--bg-main); font-weight: 600; font-size: 0.88rem;">
                    <option value="">-- Daily Attendance --</option>
                    
                    <?php if (!empty($groupedContexts['subject'])): ?>
                        <optgroup label="Academic Subjects">
                            <?php foreach ($groupedContexts['subject'] as $sem => $subs): ?>
                                <?php foreach ($subs as $s): ?>
                                    <option value="<?= $s['id'] ?>">[<?= htmlspecialchars($sem) ?>] <?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>

                    <?php if (!empty($groupedContexts['event'])): ?>
                        <optgroup label="Special Events">
                            <?php foreach ($groupedContexts['event'] as $sem => $subs): ?>
                                <?php foreach ($subs as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <!-- Recent Scans List -->
        <div class="animate-fade-up delay-1">
            <div class="mobile-stack" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1rem; gap: 1rem;">
                <div>
                    <h5 style="color: var(--text-main); font-weight: 800; margin: 0; letter-spacing: -0.02em;">
                        Recent Scans 
                        <span id="subjectIndicator" style="font-size: 0.75em; color: var(--text-muted); font-weight: 600; display: block; margin-top: 4px;">(Daily Mode)</span>
                    </h5>
                </div>
                <div style="text-align:right; display: flex; align-items: center; gap: 10px;">
                      <button id="btnNotify" onclick="finishAndNotify()" class="btn btn-ghost btn-sm" style="color: var(--primary); padding: 0.5rem 1rem; display: flex; align-items: center; gap: 6px; border-radius: 50px; font-weight: 700;">
                        <i class="bi bi-bell"></i> <span class="hide-mobile">Notify</span>
                      </button>
                     <span id="countBadge" style="background:var(--bg-main); color:var(--primary); padding:4px 12px; border: 1px solid var(--border); border-radius:12px; font-size:0.8rem; font-weight:800;">0</span>
                     <a href="view_attendance.php" style="color: var(--primary); text-decoration: none; font-size: 0.9rem; font-weight: 700; border-bottom: 2px solid var(--primary);">Records</a>
                </div>
            </div>

            <div id="attendanceList" class="attendance-list" style="display: flex; flex-direction: column; gap: 0.75rem;">
                <!-- Content injected via JS -->
                <div style="padding: 2rem; text-align: center;">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...
                </div>
            </div>
        </div>

    </main>

    <!-- Scanner Modal -->
    <div id="scannerModal">
        <div class="scanner-content animate-fade-up" style="padding: 1.5rem 1.75rem 1.75rem;">
            <div class="modal-header-pro" style="padding: 0 0 1.25rem 0; margin-bottom: 1.25rem; border-bottom: 1px solid color-mix(in srgb, var(--text-muted) 12%, transparent); display: flex; align-items: center; justify-content: space-between;">
                <div class="header-left" style="display: flex; align-items: center; gap: 0.85rem;">
                    <div class="header-icon" style="width: 42px; height: 42px; border-radius: 12px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 35%, transparent);">
                        <i class="bi bi-qr-code-scan"></i>
                    </div>
                    <div class="header-text">
                        <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.2; color: var(--text-main);">Live Attendance Scanner</h3>
                        <small style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; display: block;">Personal Attendance Scanner</small>
                    </div>
                </div>
                <button onclick="toggleScanner()" class="modal-close-btn" title="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <div class="scanner-view-wrapper">
                <!-- High-tech radar sweeps & reticles -->
                <div class="scanner-grid-overlay"></div>
                <div class="scanner-laser-line"></div>
                <div class="reticle-corner reticle-tl"></div>
                <div class="reticle-corner reticle-tr"></div>
                <div class="reticle-corner reticle-bl"></div>
                <div class="reticle-corner reticle-br"></div>
                
                <div id="reader" style="width: 100%; overflow: hidden;"></div>
                
                <!-- Scan Result Overlay -->
                <div id="scanFeedback" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); flex-direction:column; align-items:center; justify-content:center; z-index:10; transition: all 0.3s ease;">
                    <div id="feedbackIcon" style="font-size: 4.5rem; margin-bottom: 10px;"></div>
                    <div id="feedbackText" style="color:white; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; text-align:center; padding: 0 1.25rem; font-family: 'Outfit', sans-serif; line-height: 1.2;"></div>
                </div>
            </div>

            <p style="text-align: center; margin-top: 1rem; color: var(--text-muted); font-size: 0.82rem; font-weight: 500; margin-bottom: 0;">
                Align student QR code within targeting indicators.
            </p>
        </div>
    </div>

    <!-- Audio -->
    <audio id="successSound" src="assets/audio/game-bonus-2-294436.mp3"></audio>
    <audio id="warningSound" src="assets/audio/reject-interface-sound.mp3"></audio>
    <audio id="errorSound" src="assets/audio/bad-machine.mp3"></audio>

    <script>
        // Clock
        setInterval(() => {
            let el = document.getElementById('clock');
            if(el) el.innerText = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }, 1000);

        // Scanner Logic
        let html5QrCode = null;
        let lastScanned = "";
        let isProcessing = false;
        
        function playSound(type) {
            const el = document.getElementById(type + 'Sound');
            if (!el) return;
            
            // Pulse the element to ensure it's loaded
            el.currentTime = 0;
            let playPromise = el.play();
            
            if (playPromise !== undefined) {
                playPromise.catch(error => {
                    console.warn("Autoplay prevented or audio error:", error);
                    // Often browsers require a user interaction to start audio context
                });
            }
        }

        async function toggleScanner() {
            // "Unlock" sounds on first user interaction
            ['success', 'warning', 'error'].forEach(t => {
                const s = document.getElementById(t + 'Sound');
                if(s) { s.muted = true; s.play().then(() => { s.pause(); s.muted = false; }).catch(() => {}); }
            });

            const modal = document.getElementById('scannerModal');
            
            if (modal.style.display === 'flex') {
                // Close Scanner
                modal.style.display = 'none';
                if (html5QrCode) {
                    try {
                        if(html5QrCode.isScanning) {
                            await html5QrCode.stop();
                        }
                        await html5QrCode.clear();
                    } catch (error) {
                        console.error("Failed to stop scanner", error);
                    }
                    html5QrCode = null;
                }
            } else {
                // Open Scanner
                modal.style.display = 'flex';
                // Small delay to ensure modal is rendered
                setTimeout(startScanning, 300);
            }
        }

        function startScanning() {
             if (html5QrCode) {
                 // Already initialized
                 return;
             }

             // Initialize the scanner
             html5QrCode = new Html5Qrcode("reader");

             const config = { 
                 fps: 10, 
                 qrbox: { width: 250, height: 250 },
                 aspectRatio: 1.0 
             };

             // Try environment camera first (back camera), fallback to user (front/webcam)
             // Ideally 'facingMode: "environment"' works, but on desktop it might fail.
             // We can use the generic { facingMode: "environment" } which usually falls back, 
             // but explicit handling is safer.
             
             html5QrCode.start(
                 { facingMode: "environment" }, 
                 config, 
                 processScan, 
                 (errorMessage) => { 
                     // frame error, ignore 
                 }
             ).catch(err => {
                 console.warn("Environment camera failed, trying user camera...", err);
                 // Fallback to user camera (webcam)
                 html5QrCode.start(
                     { facingMode: "user" }, 
                     config, 
                     processScan, 
                     (errorMessage) => { /* ignore */ }
                 ).catch(err2 => {
                     // Both failed
                     handleCameraError(err2);
                 });
             });
        }

        // Removed initScannerUI as we use start() directly now
        function handleCameraError(e) {
            console.error(e);
            let msg = "Scanner Error: " + (e.message || e);
            
            // Friendly Error Messages
            if (e.name === 'NotAllowedError' || (e.message && e.message.includes('permission'))) {
                    msg = "Camera Access Denied.<br><br>The browser blocked the camera. If you are on Windows/Android, ensure you are using <b>HTTPS</b> or <b>localhost</b>.";
            } else if (e.name === 'NotFoundError') {
                    msg = "No camera found on this device.";
            } else if (e.name === 'NotReadableError') {
                    msg = "Camera is being used by another application.";
            } else if (e.message && e.message.indexOf("The request is not not allowed") > -1) {
                    msg = "Permission denied. Check browser settings.";
            }
            
            Swal.fire({
                title: 'Camera Error',
                html: msg,
                icon: 'error',
                footer: '<a href="manual.php" class="btn btn-sm btn-secondary">Go to Manual Entry</a>',
                showConfirmButton: true
            });
        }

        function processScan(qrCode) {
            if (qrCode === lastScanned || isProcessing) return;
            
            lastScanned = qrCode;
            isProcessing = true;
            
            console.log("Scanned:", qrCode);

            // Pause scanning while processing
            // if(html5QrcodeScanner) html5QrcodeScanner.pause(); // Not strictly necessary if we use isProcessing flag

            const subjectId = document.getElementById('scanSubject').value;

            fetch('api/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ 
                    qr_code: qrCode,
                    subject_id: subjectId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'new') {
                    showScanFeedback('question', 'New Classmate', '#3b82f6');
                    // New Registration
                    Swal.fire({
                        title: 'New Classmate',
                        html: `
                            <p style="margin-bottom:15px; color:#666;">Unknown QR. Register new classmate?</p>
                            <input id="reg-firstname" class="swal2-input" placeholder="First Name" style="margin-bottom: 10px;">
                            <input id="reg-lastname" class="swal2-input" placeholder="Last Name">
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Register',
                        preConfirm: () => {
                            const fname = document.getElementById('reg-firstname').value.trim();
                            const lname = document.getElementById('reg-lastname').value.trim();
                            if(!fname || !lname) {
                                Swal.showValidationMessage('First and Last Name required');
                            }
                            return { first_name: fname, last_name: lname };
                        }
                    }).then((result) => {
                        if (result.isConfirmed && result.value) {
                            fetch('api/process.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ 
                                    qr_code: qrCode, 
                                    first_name: result.value.first_name, 
                                    last_name: result.value.last_name 
                                })
                            }).then(r => r.json()).then(regHelper);
                        } else {
                            resumeScanning();
                        }
                    });
                } else if (data.status === 'duplicate') {
                    showScanFeedback('info', `${data.user_name}<br>ALREADY SCANNED`, '#f59e0b');
                    playSound('warning');
                    shortToast('info', `${data.user_name} already scanned.`);
                } else if (data.status === 'success') {
                    const statusColor = data.attendance_status === 'late' ? '#f59e0b' : '#10b981';
                    showScanFeedback('check', `${data.attendance_status.toUpperCase()}<br>${data.user_name}`, statusColor);
                    playSound('success');
                    shortToast('success', `Marked ${data.attendance_status.toUpperCase()}: ${data.user_name}`);
                    fetchRecentList(); // Update List Immediately
                } else {
                    showScanFeedback('x', data.message || 'Error', '#ef4444');
                    playSound('error');
                    shortToast('error', data.message);
                }
            })
            .catch((err) => {
                console.error(err);
                showScanFeedback('x', 'DATABASE ERROR', '#ef4444');
                shortToast('error', 'Database Error');
            })
            .finally(() => {
                // Reset scan lock after delay
                setTimeout(() => { 
                    lastScanned = ""; 
                    isProcessing = false; 
                }, 2000);
            });
        }

        function showScanFeedback(type, text, color) {
            const overlay = document.getElementById('scanFeedback');
            const icon = document.getElementById('feedbackIcon');
            const label = document.getElementById('feedbackText');
            
            icon.innerHTML = type === 'check' ? '<i class="bi bi-check-circle"></i>' : 
                             type === 'info' ? '<i class="bi bi-info-circle"></i>' :
                             type === 'x' ? '<i class="bi bi-x-circle"></i>' :
                             '<i class="bi bi-question-circle"></i>';
            
            icon.style.color = color;
            label.innerHTML = text;
            overlay.style.display = 'flex';
            overlay.style.background = `rgba(0,0,0,0.85)`;
            overlay.style.border = `4px solid ${color}`;

            setTimeout(() => {
                overlay.style.display = 'none';
            }, 1800);
        }

        function resumeScanning() {
             if(html5QrCode) {
                 isProcessing = false;
                 // Allow re-scanning same code if needed? 
                 lastScanned = ""; 
             }
        }
        
        function regHelper(data) {
             if(data.status === 'success') {
                playSound('success');
                Swal.fire('Registered!', 'Classmate added.', 'success').then(() => {
                    fetchRecentList(); // Refresh list
                    resumeScanning();
                });
            } else {
                Swal.fire('Error', data.message, 'error').then(() => {
                    resumeScanning();
                });
            }
        }

        function shortToast(icon, title) {
            Swal.fire({
                icon: icon,
                title: title,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
        }

        // --- Real-time List Logic ---
        
        function fetchRecentList() {
            const subjectId = document.getElementById('scanSubject').value;
            const indicator = document.getElementById('subjectIndicator');
            
            // Update UI Title
            if (subjectId) {
                const sel = document.getElementById('scanSubject');
                const text = sel.options[sel.selectedIndex].text;
                indicator.innerText = `(${text})`;
                indicator.style.color = 'var(--primary)';
                indicator.style.fontWeight = 'bold';
            } else {
                indicator.innerText = '(Daily)';
                indicator.style.color = 'var(--text-muted)';
                indicator.style.fontWeight = 'normal';
            }

            fetch(`api/get_recent.php?subject_id=${subjectId}`)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        renderList(res.data);
                        
                        // Update Notify Button
                        const btnNotify = document.getElementById('btnNotify');
                        if (res.is_notified) {
                            btnNotify.innerHTML = '<i class="bi bi-check-all"></i> <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">NOTIFIED</span>';
                            btnNotify.style.opacity = '0.6';
                            btnNotify.style.pointerEvents = 'none';
                        } else {
                            btnNotify.innerHTML = '<i class="bi bi-bell"></i> Finish & Notify';
                            btnNotify.style.opacity = '1';
                            btnNotify.style.pointerEvents = 'auto';
                        }
                    }
                })
                .catch(e => console.error(e));
        }

        function renderList(data) {
            const attendanceList = document.getElementById('attendanceList');
            const countBadge = document.getElementById('countBadge');
            
            countBadge.innerText = data.length;
            attendanceList.innerHTML = '';

            if (data.length === 0) {
                attendanceList.innerHTML = `
                    <div style="padding: 3rem 2rem; text-align: center; border: 1.5px dashed var(--border); border-radius: 20px; background: var(--bg-card);">
                        <i class="bi bi-qr-code" style="font-size: 2rem; color: var(--text-muted); opacity: 0.5; display: block; margin-bottom: 0.75rem;"></i>
                        <p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500; margin: 0;">No active logs scanned for this session mode.</p>
                    </div>`;
                return;
            }

            data.forEach((record, idx) => {
                const badgeClass = (record.status || 'present').toLowerCase();
                const initial = escapeHtml(record.name.charAt(0).toUpperCase());
                const colors = ['#5c6bc0','#42a5f5','#26a69a','#66bb6a','#ec407a','#ab47bc','#ef5350','#ffa726'];
                const avatarColor = colors[idx % colors.length];
                
                const row = document.createElement('div');
                row.className = 'glass-panel hover-lift animate-fade-up';
                row.style.padding = '0.95rem 1.25rem';
                row.style.borderRadius = '16px';
                row.style.display = 'flex';
                row.style.justifyContent = 'space-between';
                row.style.alignItems = 'center';
                row.style.animationDelay = (idx * 0.05) + 's';
                row.style.boxShadow = 'var(--shadow-neu-out-sm)';
                row.style.border = '1px solid var(--border)';
                row.style.background = 'var(--bg-card)';
                row.style.marginBottom = '0.5rem';

                row.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                        <div style="width: 36px; height: 36px; border-radius: 10px; background: ${avatarColor}; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; font-family: 'Outfit', sans-serif; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            ${initial}
                        </div>
                        <div style="min-width: 0;">
                            <h6 style="margin:0; font-size: 0.92rem; font-weight: 800; color: var(--text-main); font-family: 'Outfit', sans-serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.25;">${escapeHtml(record.name)}</h6>
                            <span style="font-size: 0.62rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-top: 1px;">Checkpoint Recorded</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
                        <span style="font-size: 0.82rem; font-weight: 800; color: var(--primary); font-family: 'JetBrains Mono', monospace;">${record.time}</span>
                        <span class="badge ${badgeClass}" style="padding: 0.35rem 0.75rem; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">${(record.status || 'PRESENT').toUpperCase()}</span>
                    </div>
                `;
                attendanceList.appendChild(row);
            });
        }

        function escapeHtml(text) {
            if (!text) return text;
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Check for Secure Context
            if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                Swal.fire({
                    icon: 'warning',
                    title: 'HTTPS Required for Camera',
                    html: `
                        <div style="text-align: left; font-size: 0.9rem;">
                            <p>Mobile browsers block the camera on <b>HTTP</b> connections for security.</p>
                            <p style="margin-top: 10px;"><b>How to fix:</b></p>
                            <ul style="padding-left: 20px;">
                                <li>Use a tunnel: <code>npx localtunnel --port 8000</code> to get a secure link.</li>
                                <li>Or use Chrome flags bypass on Android.</li>
                            </ul>
                            <p style="margin-top: 10px;">Check <code>CONNECT_MOBILE.md</code> in the project folder for a full guide.</p>
                        </div>
                    `,
                    confirmButtonText: 'I Understand',
                    backdrop: 'rgba(0,0,0,0.8)'
                });
            }
            
            // Auto-select subject based on current schedule, then fetch list
            fetch('api/subject_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'get_current_subject' })
            })
            .then(r => r.json())
            .then(d => {
                if(d.status === 'success') {
                     const sel = document.getElementById('scanSubject');
                     if(sel) {
                         sel.value = d.data.id;
                         shortToast('info', `Auto-selected: ${d.data.name}`);
                     }
                }
                checkUrlParam();
            })
            .catch(() => {
                checkUrlParam();
            });
            
            // Start periodic refresh
            setInterval(fetchRecentList, 5000);
        });

         function checkUrlParam() {
              const urlParams = new URLSearchParams(window.location.search);
              const urlSubId = urlParams.get('subject_id');
              if(urlSubId) {
                  const sel = document.getElementById('scanSubject');
                  if(sel) {
                      sel.value = urlSubId;
                      toggleScanner();
                  }
              }
              // Initial Fetch
              fetchRecentList();
         }

         function finishAndNotify() {
             const subjectId = document.getElementById('scanSubject').value;
             const modeName = subjectId ? 'this Subject' : 'Daily Attendance';
             
             Swal.fire({
                 title: 'Finish & Notify?',
                 text: `This will mark all remaining students as Absent for ${modeName} and notify the Group Chat.`,
                 icon: 'question',
                 showCancelButton: true,
                 confirmButtonText: 'Yes, Notify Now',
                 showLoaderOnConfirm: true,
                 confirmButtonColor: 'var(--primary)',
                 preConfirm: () => {
                     const formData = new FormData();
                     formData.append('subject_id', subjectId);
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
                     Swal.fire('Success', result.value.message, 'success').then(() => {
                         fetchRecentList();
                     });
                 }
             });
         }
    </script>
</body>
</html>
