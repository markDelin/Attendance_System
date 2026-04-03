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

// Fetch Subjects
$stmt = $pdo->query("SELECT id, name, semester FROM subjects ORDER BY semester DESC, name ASC");
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
$groupedSubjects = [];
foreach ($subjects as $s) {
    $groupedSubjects[$s['semester']][] = $s;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="assets/js/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            background: white;
            width: 95%; max-width: 400px;
            padding: 1rem;
            border-radius: var(--radius-lg);
            position: relative;
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
        
        /* List Styles */
        .attendance-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            background: white;
        }
        .attendance-row:first-child { border-top-left-radius: var(--radius-lg); border-top-right-radius: var(--radius-lg); }
        .attendance-row:last-child { border-bottom: none; border-bottom-left-radius: var(--radius-lg); border-bottom-right-radius: var(--radius-lg); }
        
        /* Status Badges */
        .badge-present { color: #166534; background: #dcfce7; }
        .badge-late { color: #d97706; background: #ffedd5; }
        .badge-absent { color: #dc2626; background: #fee2e2; }
    </style>
</head>
<body>

    <!-- Nav -->
    <nav class="navbar">
        <a href="index.php" class="btn btn-ghost" style="border: none; padding-left: 0;">
            <i class="bi bi-arrow-left"></i> <span class="d-none-mobile">Back</span>
        </a>
        <h3 class="text-gradient">Class Check-In</h3>
        <div class="flex-center" style="gap: 10px;">
            <i class="bi bi-clock" style="color: var(--secondary);"></i> 
            <span id="clock" style="font-weight: 600; font-size: 0.9rem;"><?= date('h:i A') ?></span>
        </div>
    </nav>

    <main class="container" style="padding-top: 2rem;">
        
        <!-- Main Actions -->
        <div style="text-align: center; margin-bottom: 2rem;" class="animate-fade-up">
            <h1 style="margin-bottom: 1rem;">Attendance Scanner</h1>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                Scan QR codes to mark attendance.
            </p>
            <button onclick="toggleScanner()" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.2rem; border-radius: 50px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">
                <i class="bi bi-qr-code-scan"></i> Scan Now
            </button>
            <div style="margin-bottom: 1rem;">
                <label for="scanSubject" style="font-weight: bold; margin-bottom: 0.5rem; display: block; color: var(--text-muted);">Subject Mode (Optional)</label>
                <select id="scanSubject" class="form-control" style="width: 100%; max-width: 300px; margin: 0 auto;">
                    <option value="">-- Daily Attendance (Default) --</option>
                    <?php foreach ($groupedSubjects as $sem => $subs): ?>
                        <optgroup label="<?= htmlspecialchars($sem) ?>">
                            <?php foreach ($subs as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <small style="display: block; margin-top: 0.5rem; color: var(--text-muted);">Select a subject to log attendance for that class specifically.</small>
            </div>


        </div>

        <!-- Recent Scans List -->
        <div class="animate-fade-up delay-1">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h4 style="color: var(--text-main);">Today's Scans (<?= $totalToday ?>)</h4>
                <a href="view_attendance.php" style="color: var(--primary); text-decoration: none; font-size: 0.9rem;">View All</a>
            </div>

            <div style="box-shadow: var(--shadow-sm); border-radius: var(--radius-lg); border: 1px solid var(--border);">
                <?php if (empty($todaysRecords)): ?>
                    <div style="padding: 2rem; text-align: center; background: white; border-radius: var(--radius-lg);">
                        <p style="color: var(--text-muted);">No scans yet today.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($todaysRecords as $r): 
                        $time = date('h:i A', strtotime($r['time']));
                        $statusClass = 'badge-' . strtolower($r['status']);
                        $statusText = strtoupper($r['status']);
                    ?>
                    <div class="attendance-row">
                        <div>
                            <div style="font-weight: 600; font-size: 1rem;"><?= htmlspecialchars($r['name'] ?? 'Unknown') ?></div>
                            <small style="color: var(--text-muted);"><i class="bi bi-clock"></i> <?= $time ?></small>
                        </div>
                        <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
        let html5QrcodeScanner = null;
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
                if (html5QrcodeScanner) {
                    try {
                        await html5QrcodeScanner.clear();
                        console.log("Scanner cleared.");
                    } catch (error) {
                        console.error("Failed to clear scanner", error);
                    }
                    html5QrcodeScanner = null;
                }
            } else {
                // Open Scanner
                modal.style.display = 'flex';
                // Small delay to ensure modal is rendered
                setTimeout(startScanning, 300);
            }
        }

        function startScanning() {
            if (html5QrcodeScanner) {
                // Already running
                return;
            }

            try {
                // Using Html5QrcodeScanner with simplified UI config
                // Use current div size
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "reader", 
                    { 
                        fps: 10, 
                        qrbox: { width: 250, height: 250 },
                        aspectRatio: 1.0,
                        showTorchButtonIfSupported: true,
                        rememberLastUsedCamera: true
                    }, 
                    false
                );
                
                html5QrcodeScanner.render(processScan, (errorMessage) => {
                    // parse error, ignore it.
                });
            } catch(e) {
                console.error(e);
                alert("Scanner Error: " + e.message);
                // Fallback for missing lib
                if(e.message.includes('Html5QrcodeScanner')) {
                    alert("Library missing. Please check internet or assets.");
                }
            }
        }

        function processScan(qrCode) {
            if (qrCode === lastScanned || isProcessing) return;
            
            lastScanned = qrCode;
            isProcessing = true;
            
            console.log("Scanned:", qrCode);

            // Pause scanning while processing
            if(html5QrcodeScanner) html5QrcodeScanner.pause();

            fetch('api/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ 
                    qr_code: qrCode,
                    subject_id: document.getElementById('scanSubject').value 
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'new') {
                    // New Registration
                    Swal.fire({
                        title: 'New Student',
                        text: 'Register Name:',
                        input: 'text',
                        showCancelButton: true,
                        confirmButtonText: 'Register'
                    }).then((result) => {
                        if (result.isConfirmed && result.value) {
                            fetch('api/process.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ qr_code: qrCode, name: result.value })
                            }).then(r => r.json()).then(regHelper);
                        } else {
                            resumeScanning();
                        }
                    });
                } else if (data.status === 'duplicate') {
                    playSound('warning');
                    shortToast('info', `${data.user_name} already scanned.`);
                    resumeScanning();
                } else if (data.status === 'success') {
                    playSound('success');
                    shortToast('success', `Marked ${data.attendance_status.toUpperCase()}: ${data.user_name}`);
                    resumeScanning();
                } else {
                    playSound('error');
                    shortToast('error', data.message);
                    resumeScanning();
                }
            })
            .catch((err) => {
                console.error(err);
                shortToast('error', 'Database Error');
                resumeScanning();
            })
            .finally(() => {
                // Reset scan lock after delay
                setTimeout(() => { 
                    lastScanned = ""; 
                    isProcessing = false; 
                }, 2500);
            });
        }

        function resumeScanning() {
             if(html5QrcodeScanner) {
                 try {
                    html5QrcodeScanner.resume(); 
                 } catch(e) {
                     console.log("Scanner resume failed (maybe not paused):", e);
                 }
             }
        }
        
        function regHelper(data) {
             if(data.status === 'success') {
                playSound('success');
                Swal.fire('Registered!', 'Student added.', 'success').then(() => {
                    location.reload(); // Reload to show in list
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
            });

        });

        // URL Parameter Check
        const urlParams = new URLSearchParams(window.location.search);
        const urlSubId = urlParams.get('subject_id');
        if(urlSubId) {
            // override auto-detect for now, or wait for load?
            // Let's rely on the element being present.
            const checkSel = setInterval(() => {
                const sel = document.getElementById('scanSubject');
                if(sel) {
                    clearInterval(checkSel);
                    sel.value = urlSubId;
                    toggleScanner(); // Auto-open scanner if subject provided
                }
            }, 100);
        }
        
    </script>
</body>
</html>