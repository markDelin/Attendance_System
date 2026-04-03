<?php
// scan.php - Simple Student QR Scanner (Modal Version)
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

// Get settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$callTime = $settings['call_time'] ?? '08:00';

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
            width: 95%; max-width: 400px;
            padding: 2rem;
            border-radius: var(--radius-lg);
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: none;
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
            <div class="glass-panel" style="padding: 2.5rem 1.5rem; border-radius: 24px; margin-bottom: 2rem;">
                <div class="flex-center" style="flex-direction: column; gap: 15px;">
                    <div style="width: 80px; height: 80px; background: rgba(30, 41, 59, 0.05); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem;">
                        <i class="bi bi-qr-code-scan" style="font-size: 2.5rem;"></i>
                    </div>
                    <button onclick="toggleScanner()" class="btn btn-primary" style="padding: 0.8rem 2.5rem; font-size: 1.1rem; border-radius: 50px; box-shadow: 0 10px 25px -5px rgba(30, 41, 59, 0.2);">
                        Scan Now
                    </button>
                    <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500; margin: 0;">Tap to begin capturing student QR codes</p>
                </div>
            </div>

            <div class="card" style="padding: 1.25rem; border-radius: 20px; border: 1px solid var(--border); background: var(--bg-card); max-width: 400px; margin: 0 auto; box-shadow: var(--glass-shadow);">
                <label for="scanSubject" style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.75rem;">Active Context</label>
                <select id="scanSubject" class="form-control" style="width: 100%; border-radius: 12px; height: 45px;">
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
                        <i class="bi bi-bell-fill"></i> <span class="hide-mobile">Notify</span>
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
        <div class="scanner-content animate-fade-up">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <h4>Scan QR</h4>
                <button onclick="toggleScanner()" style="background:none; border:none; font-size: 1.2rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <div style="position: relative;">
                <div id="reader" style="width: 100%; border-radius: var(--radius-md); overflow: hidden;"></div>
            </div>

            <p style="text-align: center; margin-top: 1rem; color: var(--text-muted); font-size: 0.9rem;">
                Point camera at student QR code.
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
        
        const sounds = {
            success: document.getElementById('successSound'),
            warning: document.getElementById('warningSound'),
            error: document.getElementById('errorSound')
        };

        function playSound(type) {
            try { sounds[type].currentTime = 0; sounds[type].play(); } catch(e) {}
        }

        async function toggleScanner() {
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
                    // New Registration
                    Swal.fire({
                        title: 'New Student',
                        html: `
                            <p style="margin-bottom:15px; color:#666;">Unknown QR. Register new student?</p>
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
                    playSound('warning');
                    shortToast('info', `${data.user_name} already scanned.`);
                    // resumeScanning(); // Let finally handle delay
                } else if (data.status === 'success') {
                    playSound('success');
                    shortToast('success', `Marked ${data.attendance_status.toUpperCase()}: ${data.user_name}`);
                    fetchRecentList(); // Update List Immediately
                    // resumeScanning(); // Let finally handle delay
                } else {
                    playSound('error');
                    shortToast('error', data.message);
                    // resumeScanning(); // Let finally handle delay
                }
            })
            .catch((err) => {
                console.error(err);
                shortToast('error', 'Database Error');
                // resumeScanning(); // Let finally handle delay
            })
            .finally(() => {
                // Reset scan lock after delay
                setTimeout(() => { 
                    lastScanned = ""; 
                    isProcessing = false; 
                }, 3000);
            });
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
                Swal.fire('Registered!', 'Student added.', 'success').then(() => {
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
                    <div style="padding: 2rem; text-align: center;">
                        <p style="color: var(--text-muted);">No scans yet for this mode.</p>
                    </div>`;
                return;
            }

            data.forEach(record => {
                const badgeClass = 'badge-' + (record.status || 'present').toLowerCase();
                // Minimalist Card Style for Scanner
                const row = document.createElement('div');
                row.className = 'student-row animate-fade-in';
                row.style.background = 'var(--bg-card)';
                row.style.border = '1px solid var(--border)';
                row.style.borderRadius = 'var(--radius-md)';
                row.style.padding = '0.8rem 1rem';
                row.style.display = 'flex';
                row.style.justifyContent = 'space-between';
                row.style.alignItems = 'center';

                row.innerHTML = `
                    <div class="student-info">
                        <h6 style="margin:0; font-size: 0.95rem; font-weight: 600;">${escapeHtml(record.name)}</h6>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 0.8rem; font-weight: bold; color: var(--primary);">${record.time}</span>
                        <span class="badge ${badgeClass}" style="padding: 0.3rem 0.6rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">${(record.status || 'PRESENT').toUpperCase()}</span>
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

        window.onload = function() {
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
            
            fetchRecentList();
            setInterval(fetchRecentList, 5000);
        };

        // Subject Change Listener
        document.getElementById('scanSubject').addEventListener('change', () => {
             fetchRecentList();
        });
        
        // Auto-select subject on load
        window.addEventListener('load', () => {
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
                // Fetch list initially (after auto-select check logic or default)
                // We add a small delay or just call it.
                // Parameter override check takes precedence
                checkUrlParam();
            })
            .catch(() => {
                // Determine list anyway if fetch fails
                checkUrlParam();
            });
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
