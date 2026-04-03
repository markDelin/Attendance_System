<?php
// billing.php - Ambagan / Contribution Dashboard
date_default_timezone_set('Asia/Manila');
require 'includes/db.php';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $newMode = $_POST['billing_mode'];
    $newQuota = floatval($_POST['billing_quota']);
    
    // We update settings table
    $pdo->prepare("UPDATE settings SET billing_mode = ?, billing_quota = ?")->execute([$newMode, $newQuota]);
    
    // Redirect to self to prevent resubmission
    header("Location: billing.php");
    exit;
}

// Event Selection
$eventId = intval($_GET['event_id'] ?? 1);

// Fetch Events
$events = $pdo->query("SELECT * FROM billing_events ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Current Event
$currentEvent = $pdo->prepare("SELECT * FROM billing_events WHERE id = ?");
$currentEvent->execute([$eventId]);
$event = $currentEvent->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    // Fallback to first event or default
    $event = ['id' => 1, 'name' => 'General Contribution', 'amount' => 50];
    $eventId = 1;
}

$quota = floatval($event['amount']);
$eventName = htmlspecialchars($event['name']);

// Defaults (removed settings-based billing mode for now to simplify event transition, treating all as Quota/Fixed per event)
// Note: We can re-enable 'mode' if needed, but per event 'amount' usually implies the target per student.
$mode = 'fixed'; // standardizing on fixed per student for simplicity in multiple events for now

// Fetch Students & Payments
// We list ALL students and check if they have a payment record
$students = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$studentCount = count($students);

// Get Payments for THIS event
$stmt = $pdo->prepare("SELECT qr_code, amount, payment_date FROM billing WHERE event_id = ?");
$stmt->execute([$eventId]);
$payments = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

// Calculations
$totalCollected = 0;
$paidCount = 0;
foreach($payments as $qr => $p) {
    $totalCollected += $p['amount'];
    $paidCount++;
}

// Logic
if ($mode === 'quota') {
    $targetDetails = "Target: ₱" . number_format($quota, 2);
    $costPerStudent = $studentCount > 0 ? $quota / $studentCount : 0;
    $progressPercent = ($totalCollected / $quota) * 100;
} else {
    // Fixed Mode (Default for Events)
    $targetDetails = "Goal: ₱" . number_format($quota, 2) . " / head";
    $costPerStudent = $quota;
    $theoreticalTotal = $studentCount * $quota;
    $progressPercent = $theoreticalTotal > 0 ? ($totalCollected / $theoreticalTotal) * 100 : 0;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ambagan (Billing) | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/html5-qrcode.min.js"></script>
    <style>
        .stat-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }
        .progress-bar {
            height: 10px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
            margin-top: 1rem;
        }
        .progress-fill {
            height: 100%;
            background: var(--primary);
            transition: width 0.5s ease;
        }
        .billing-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .billing-row:last-child { border-bottom: none; }
        
        /* Modal Scanner */
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
            width: 90%; max-width: 400px;
            padding: 1rem;
            border-radius: var(--radius-lg);
            position: relative;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="btn btn-ghost" style="border: none; padding-left: 0;">
            <i class="bi bi-arrow-left"></i> <span class="d-none-mobile">Dashboard</span>
        </a>
        
        <!-- Event Selector -->
        <div style="display: flex; align-items: center; gap: 10px;">
             <h3 class="text-gradient d-none-mobile">Ambagan</h3>
             <select onchange="window.location.href='billing.php?event_id='+this.value" class="form-control" style="width: auto; min-width: 150px; font-weight: bold;">
                <?php foreach($events as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $eventId == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                <?php endforeach; ?>
             </select>
             <button onclick="openCreateEvent()" class="btn btn-ghost" title="New Event" style="padding: 0.5rem;"><i class="bi bi-plus-lg"></i></button>
        </div>

        <div class="flex-center" style="gap: 5px;">
             <!-- Reset -->
             <button onclick="confirmReset()" class="btn btn-ghost" style="color: var(--danger); border-color: var(--danger); padding: 0.6rem 0.8rem;">
                <i class="bi bi-trash"></i>
             </button>
             <!-- Export -->
             <a href="api/export_billing.php?event_id=<?= $eventId ?>" class="btn btn-ghost" style="padding: 0.6rem 0.8rem;">
                <i class="bi bi-download"></i>
             </a>
             <button onclick="toggleScanner()" class="btn btn-primary">
                <i class="bi bi-qr-code-scan"></i> <span class="d-none-mobile">Scan</span>
             </button>
        </div>
    </nav>

    <main class="container" style="padding-top: 2rem; padding-bottom: 5rem;">

        <!-- Summary -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            
            <!-- Progress Card -->
            <div class="stat-card">
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <h4 style="color: var(--text-muted); font-size: 0.9rem;">Total Collected</h4>
                        <h2 style="font-size: 2rem;">₱<?= number_format($totalCollected, 2) ?></h2>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge" style="background: var(--bg-main);"><?= $paidCount ?> / <?= $studentCount ?> Paid</span>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= min(100, $progressPercent) ?>%;"></div>
                </div>
                <small style="display: block; margin-top: 0.5rem; color: var(--text-muted);"><?= $targetDetails ?></small>
            </div>

            <!-- Cost Card -->
            <div class="stat-card flex-center" style="background: #eff6ff; border-color: #bfdbfe;">
                <div style="text-align: center;">
                    <h4 style="color: #1e40af; font-size: 0.9rem;">CONTRIBUTION PER STUDENT</h4>
                    <h1 style="color: #1d4ed8; font-size: 2.5rem;">₱<?= number_format($costPerStudent, 2) ?></h1>
                    <?php if($mode === 'quota'): ?>
                        <small style="color: #60a5fa;">(Dynamic: Quota ÷ Students)</small>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>

        <!-- Student List -->
        <div class="card">
            <div style="padding: 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <h4>Student Status</h4>
                <input type="text" id="searchInput" placeholder="Search..." class="form-control" style="width: 200px; padding: 0.5rem;">
            </div>

            <div id="studentList" style="max-height: 500px; overflow-y: auto;">
                <?php foreach($students as $s): 
                    $isPaid = isset($payments[$s['qr_code']]);
                    $pData = $isPaid ? $payments[$s['qr_code']] : null;
                ?>
                <div class="billing-row filter-item">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div style="width: 40px; height: 40px; background: <?= $isPaid ? '#dcfce7' : '#f1f5f9' ?>; border-radius: 50%; color: <?= $isPaid ? '#15803d' : '#64748b' ?>; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                            <?= substr($s['name'], 0, 1) ?>
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($s['name']) ?></div>
                            <small style="color: var(--text-muted);"><?= $isPaid ? date('M j, h:i A', strtotime($pData['payment_date'])) : 'Not Paid' ?></small>
                        </div>
                    </div>
                    <div>
                        <?php if($isPaid): ?>
                            <div class="flex-center" style="gap: 5px;">
                                <button onclick="editPayment('<?= $s['qr_code'] ?>', '<?= $pData['amount'] ?>')" class="btn btn-ghost" style="padding: 0.4rem 0.6rem;" title="Edit Amount">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button onclick="togglePayment('<?= $s['qr_code'] ?>', 'unpay')" class="btn btn-ghost" style="color: var(--success); border-color: var(--success);">
                                    <i class="bi bi-check-circle-fill"></i> Paid (₱<?= number_format($pData['amount'], 0) ?>)
                                </button>
                            </div>
                        <?php else: ?>
                            <button onclick="togglePayment('<?= $s['qr_code'] ?>', 'pay')" class="btn btn-ghost" style="opacity: 0.5;">
                                <i class="bi bi-circle"></i> Pay
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>

    <!-- Scanner Modal -->
    <div id="scannerModal">
        <div class="scanner-content">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <h4>Scan to Pay</h4>
                <button onclick="toggleScanner()" style="background:none; border:none; font-size: 1.2rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="reader" style="width: 100%; border-radius: var(--radius-md); overflow: hidden;"></div>
            <p style="text-align: center; margin-top: 1rem; color: var(--text-muted);">Scan student QR to mark as paid.</p>
        </div>
    </div>

    <!-- Create Event Modal -->
    <div id="createEventModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center;">
        <div class="scanner-content animate-fade-up">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <h4>New Event</h4>
                <button onclick="closeCreateEvent()" style="background:none; border:none; font-size: 1.2rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Event Name</label>
                <input type="text" id="newEventName" class="form-control" placeholder="e.g., Christmas Party">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Amount per Head</label>
                <input type="number" id="newEventAmount" class="form-control" value="50">
            </div>

            <button onclick="createEvent()" class="btn btn-primary" style="width: 100%;">Create Event</button>
        </div>
    </div>

    <!-- Logic -->
    <script>
        // Search
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.filter-item');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? 'flex' : 'none';
            });
        });

        // Payment Toggle
        function togglePayment(qr, action) {
            fetch('api/billing_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ 
                    qr_code: qr, 
                    action: action, 
                    amount: '<?= $costPerStudent ?>',
                    event_id: '<?= $eventId ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    // Quick reload or DOM update. Reload is safer for calculated quotas.
                    location.reload(); 
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }

        // Reset Logic
        function confirmReset() {
            Swal.fire({
                title: 'Reset All Data?',
                text: "This will delete ALL payment records. History will be archived.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, Reset All'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/billing_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'reset_event', event_id: '<?= $eventId ?>' })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.status === 'success') {
                            Swal.fire('Reset!', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }

        // Edit Logic
        function editPayment(qr, currentAmount) {
            Swal.fire({
                title: 'Edit Amount',
                input: 'number',
                inputValue: currentAmount,
                showCancelButton: true,
                confirmButtonText: 'Update',
                confirmButtonColor: 'var(--primary)',
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    fetch('api/billing_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ 
                            qr_code: qr, 
                            action: 'update', 
                            amount: result.value,
                            event_id: '<?= $eventId ?>' 
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.status === 'success') {
                            Swal.fire({
                                icon: 'success', 
                                title: 'Updated', 
                                timer: 1000, 
                                showConfirmButton: false 
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }

        // Scanner
        let html5QrcodeScanner = null;

        async function toggleScanner() {
            const modal = document.getElementById('scannerModal');
            if (modal.style.display === 'flex') {
                modal.style.display = 'none';
                if (html5QrcodeScanner) {
                    try {
                        await html5QrcodeScanner.clear();
                    } catch (e) { console.error(e); }
                    html5QrcodeScanner = null;
                }
            } else {
                modal.style.display = 'flex';
                setTimeout(startScanning, 300);
            }
        }

        function startScanning() {
            if (html5QrcodeScanner) return;

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
            html5QrcodeScanner.render(onScanSuccess, (err) => {});
        }

        function onScanSuccess(decodedText, decodedResult) {
            // Pause scanning
            if(html5QrcodeScanner) html5QrcodeScanner.pause();

            // Mark as Paid
            fetch('api/billing_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ 
                    qr_code: decodedText, 
                    action: 'pay', 
                    amount: '<?= $costPerStudent ?>',
                    event_id: '<?= $eventId ?>' 
                })
            })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    const audio = new Audio('assets/audio/game-bonus-2-294436.mp3');
                    audio.play();
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Recorded',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        html5QrcodeScanner.resume();
                        // Optional: Reload page in background or partial update? 
                        // For now we just keep scanning. User can refresh list later.
                    });
                } else {
                    const audio = new Audio('assets/audio/bad-machine.mp3');
                    audio.play();
                    Swal.fire('Note', data.message, 'info').then(() => html5QrcodeScanner.resume());
                }
            });
        }
        // Create Event Logic
        function openCreateEvent() { document.getElementById('createEventModal').style.display = 'flex'; }
        function closeCreateEvent() { document.getElementById('createEventModal').style.display = 'none'; }
        
        function createEvent() {
            const name = document.getElementById('newEventName').value;
            const amount = document.getElementById('newEventAmount').value;
            
            if(!name) return Swal.fire('Error', 'Name is required', 'error');
            
            fetch('api/billing_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ 
                    action: 'create_event', 
                    event_name: name,
                    event_amount: amount
                })
            })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    window.location.href = 'billing.php?event_id=' + data.id;
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }

    </script>

</body>
</html>
