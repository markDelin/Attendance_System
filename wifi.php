<?php
// wifi.php - WiFi Network QR Code Collection
require 'includes/db.php';

// Handle Add WiFi Network
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    header('Content-Type: application/json');
    $ssid = trim($_POST['ssid'] ?? '');
    $password = $_POST['password'] ?? '';
    $encryption = $_POST['encryption'] ?? 'WPA';

    if (empty($ssid)) {
        echo json_encode(['status' => 'error', 'message' => 'SSID (WiFi Name) is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO wifi_networks (ssid, password, encryption) VALUES (?, ?, ?)");
        $stmt->execute([$ssid, $password, $encryption]);
        echo json_encode(['status' => 'success', 'message' => 'WiFi Network added successfully!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle Delete WiFi Network
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);

    try {
        $stmt = $pdo->prepare("DELETE FROM wifi_networks WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'WiFi Network deleted successfully!']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch all WiFi Networks
$networks = $pdo->query("SELECT * FROM wifi_networks ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Define Navbar Actions (Top Actions)
$navbar_actions = '
    <button onclick="openScanModal()" class="btn-icon" title="Upload & Scan WiFi QR" style="margin-right: 6px;">
        <i class="bi bi-qr-code-scan" style="font-size: 0.95rem;"></i>
    </button>
    <button onclick="openAddModal()" class="btn-icon" title="Add WiFi Network">
        <i class="bi bi-plus-lg" style="font-size: 0.95rem;"></i>
    </button>
';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Collection | QR Tools</title>
    <link href="assets/css/style.css?v=1.3" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .wifi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 320px));
            gap: 1.5rem;
            margin-top: 1rem;
            margin-bottom: 3rem;
            justify-content: start;
        }
        
        .wifi-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
        }
        .wifi-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-neu-out-lg);
            border-color: var(--primary);
        }
        
        .wifi-header {
            width: 100%;
            text-align: center;
            margin-bottom: 1rem;
            border-bottom: 2px solid color-mix(in srgb, var(--primary) 8%, transparent);
            padding-bottom: 0.75rem;
        }
        
        .wifi-ssid {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .wifi-details {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .wifi-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-main);
            padding: 6px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        
        .wifi-badge {
            font-weight: 800;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary);
        }
        
        .wifi-password-toggle {
            cursor: pointer;
            color: var(--text-main);
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.15s;
        }
        .wifi-password-toggle:hover {
            color: var(--primary);
        }

        .wifi-qr-container {
            width: 100%;
            max-width: 200px;
            aspect-ratio: 1;
            background: #ffffff;
            border-radius: 16px;
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: var(--shadow-neu-in-sm);
            border: 1px solid var(--border);
            margin: 0 auto 1.25rem;
            transition: transform 0.25s;
        }
        .wifi-card:hover .wifi-qr-container {
            transform: scale(1.02);
        }
        
        .wifi-qr-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .wifi-actions {
            display: flex;
            gap: 8px;
            width: 100%;
        }
        .wifi-actions .btn {
            flex: 1;
            justify-content: center;
            font-size: 0.78rem;
            font-weight: 800;
            padding: 0.6rem;
            border-radius: 12px;
        }
        
        .empty-state {
            grid-column: 1 / -1;
            padding: 5rem 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            border-radius: 24px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-out-sm);
        }
        .empty-state-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: color-mix(in srgb, var(--primary) 8%, transparent);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--primary);
            font-size: 2.2rem;
            box-shadow: var(--shadow-neu-out-sm);
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="padding-top: 3.5rem;">
        
        <div style="margin-top: 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="margin: 0; font-size: 2rem; font-weight: 800; letter-spacing: -0.05em; font-family: 'Outfit', sans-serif;">WiFi Collection</h1>
                <p style="color: var(--text-muted); font-size: 0.88rem; font-weight: 500; margin:0;">Generate scanable QR codes for fast WiFi logins</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button onclick="openScanModal()" class="btn btn-ghost" style="padding: 0.65rem 1.5rem; border-radius: 50px; font-weight: 800; font-size: 0.82rem; display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid var(--border);">
                    <i class="bi bi-qr-code-scan" style="display: inline-block !important; font-size: 0.95rem !important; margin-bottom: 0 !important; opacity: 1 !important; line-height: 1 !important; vertical-align: middle !important;"></i> Upload & Scan QR
                </button>
                <button onclick="openAddModal()" class="btn btn-primary" style="padding: 0.65rem 1.5rem; border-radius: 50px; font-weight: 800; font-size: 0.82rem; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                    <i class="bi bi-plus-lg" style="display: inline-block !important; font-size: 0.95rem !important; margin-bottom: 0 !important; opacity: 1 !important; line-height: 1 !important; vertical-align: middle !important;"></i> Add WiFi Network
                </button>
            </div>
        </div>

        <!-- WiFi Grid / Empty State -->
        <?php if (empty($networks)): ?>
            <div class="empty-state animate-fade-up" style="max-width: 480px; margin: 4rem auto; padding: 4rem 2rem;">
                <div class="empty-state-icon">
                    <i class="bi bi-wifi"></i>
                </div>
                <h3 style="color: var(--text-main); margin-bottom: 0.5rem; font-weight: 850; font-size: 1.25rem;">No WiFi Networks Found</h3>
                <p style="margin-bottom: 1.75rem; font-size: 0.85rem; max-width: 320px; line-height: 1.5;">Save your WiFi credentials to generate instant, click-and-scan printable connection codes.</p>
                <button onclick="openAddModal()" class="btn btn-primary" style="padding: 0.7rem 1.75rem; border-radius: 50px; font-weight: 800; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="bi bi-plus-lg" style="display: inline-block !important; font-size: 1.05rem !important; margin-bottom: 0 !important; opacity: 1 !important; line-height: 1 !important; vertical-align: middle !important;"></i> Create WiFi Network
                </button>
            </div>
        <?php else: ?>
            <div class="wifi-grid">
                <?php foreach ($networks as $idx => $net): 
                    $staggerClass = "stagger-" . min($idx + 1, 8);
                    
                    // Construct WiFi QR String
                    // FORMAT: WIFI:S:<SSID>;T:<WPA|WEP|nopass>;P:<PASSWORD>;H:<true|false|empty>;;
                    $ssidRaw = $net['ssid'];
                    $passwordRaw = $net['password'];
                    $enc = $net['encryption'] ?: 'nopass';
                    
                    // Properly escape WiFi QR parameters
                    $ssidEsc = str_replace(['\\', ';', ',', ':'], ['\\\\', '\\;', '\\,', '\\:'], $ssidRaw);
                    $pwdEsc = str_replace(['\\', ';', ',', ':'], ['\\\\', '\\;', '\\,', '\\:'], $passwordRaw);
                    
                    $qrString = "WIFI:S:{$ssidEsc};T:{$enc};P:{$pwdEsc};;";
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrString);
                ?>
                    <div class="wifi-card animate-fade-up <?= $staggerClass ?>" id="wifi-card-<?= $net['id'] ?>">
                        <!-- Top: SSID WiFi Name -->
                        <div class="wifi-header">
                            <h3 class="wifi-ssid" title="<?= htmlspecialchars($net['ssid']) ?>"><?= htmlspecialchars($net['ssid']) ?></h3>
                        </div>
                        
                        <!-- Middle Details -->
                        <div class="wifi-details">
                            <div class="wifi-detail-row">
                                <span>Password</span>
                                <?php if (empty($net['password'])): ?>
                                    <span style="font-style: italic; opacity: 0.5;">No Password</span>
                                <?php else: ?>
                                    <span class="wifi-password-toggle" onclick="togglePassword(this, '<?= htmlspecialchars($net['password']) ?>')" title="Click to copy password">
                                        <span class="pwd-mask">••••••••</span>
                                        <i class="bi bi-eye"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Bottom: Image QR Code -->
                        <div class="wifi-qr-container">
                            <img class="wifi-qr-image" src="<?= $qrUrl ?>" alt="WiFi QR Code" loading="lazy">
                        </div>
                        
                        <!-- Actions -->
                        <div class="wifi-actions">
                            <button onclick="downloadQR('<?= $net['id'] ?>', '<?= htmlspecialchars($net['ssid']) ?>', '<?= urlencode($qrString) ?>')" class="btn btn-ghost" style="border: 1px solid var(--border);" title="Save QR Image">
                                <i class="bi bi-download" style="margin-right:6px;"></i> Save QR
                            </button>
                            <button onclick="deleteNetwork(<?= $net['id'] ?>, '<?= htmlspecialchars($net['ssid']) ?>')" class="btn btn-ghost" style="border: 1px solid var(--border); color: var(--danger);" title="Remove Network">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Upload & Scan Modal -->
    <div id="scanWifiModal" class="modal-overlay" onclick="if(event.target == this) closeScanModal()">
        <div class="modal-body" style="max-width: 420px; border-radius: 24px; padding: 2rem; text-align: center;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; text-align: left;">
                <h4 style="margin: 0; font-weight: 800; letter-spacing: -0.02em; font-family: 'Outfit', sans-serif;">Upload & Scan QR</h4>
                <button onclick="closeScanModal()" style="background:none; border:none; font-size: 1.3rem; color: var(--text-muted); cursor: pointer;"><i class="bi bi-x-lg"></i></button>
            </div>

            
            <div id="dropzone" style="border: 2px dashed var(--border); border-radius: 16px; padding: 2.5rem 1.5rem; cursor: pointer; background: var(--bg-main); transition: all 0.2s;" onclick="document.getElementById('qr-file-input').click()">
                <div style="font-size: 2.2rem; color: var(--primary); margin-bottom: 1rem; opacity: 0.85;">
                    <i class="bi bi-cloud-arrow-up" style="display: inline-block !important; font-size: 2.5rem !important; margin-bottom: 0 !important; opacity: 1 !important; line-height: 1 !important; vertical-align: middle !important;"></i>
                </div>
                <h5 style="margin: 0 0 0.5rem; font-weight: 800; font-family: 'Outfit', sans-serif; color: var(--text-main);">Select QR Code Image</h5>
                <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0; line-height: 1.45;">Click to upload or drag-and-drop a WiFi QR Code image file (.png, .jpg, .jpeg)</p>
                <input type="file" id="qr-file-input" style="display: none;" accept="image/*" onchange="handleFileSelect(this)">
            </div>
            
            <div id="scanFeedback" style="display: none; margin-top: 1.5rem; padding: 1rem; border-radius: 12px; background: color-mix(in srgb, var(--primary) 6%, transparent); border: 1px solid var(--border); font-size: 0.82rem; font-weight: 600; color: var(--text-main); align-items: center; justify-content: center; gap: 8px;">
                <i class="bi bi-arrow-repeat animate-spin" style="display: inline-block !important; font-size: 1rem !important; margin-bottom: 0 !important; opacity: 1 !important; line-height: 1 !important; vertical-align: middle !important;"></i> Decoding image...
            </div>
        </div>
    </div>

    <!-- Add WiFi Modal -->
    <div id="addWifiModal" class="modal-overlay" onclick="if(event.target == this) closeAddModal()">
        <div class="modal-body" style="max-width: 420px; border-radius: 24px; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h4 style="margin: 0; font-weight: 800; letter-spacing: -0.02em; font-family: 'Outfit', sans-serif;">Add WiFi Network</h4>
                <button onclick="closeAddModal()" style="background:none; border:none; font-size: 1.3rem; color: var(--text-muted); cursor: pointer;"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <form id="addWifiForm" onsubmit="saveWifi(event)">
                <div style="margin-bottom: 1.25rem;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:700; font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted);">WiFi Name (SSID) *</label>
                    <input type="text" id="wifi_ssid" required class="form-control" placeholder="e.g. Home_Network" style="border-radius: 12px;">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:700; font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted);">Security Encryption</label>
                    <select id="wifi_encryption" class="form-control" style="border-radius: 12px;" onchange="toggleFormPassword()">
                        <option value="WPA">WPA / WPA2</option>
                        <option value="WPA3">WPA3</option>
                        <option value="WEP">WEP</option>
                        <option value="WPA-EAP">WPA / WPA2 Enterprise (EAP)</option>
                        <option value="nopass">None (Open)</option>
                    </select>
                </div>

                <div id="passwordFieldContainer" style="margin-bottom: 2rem;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:700; font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted);">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="wifi_password" class="form-control" placeholder="WiFi Password" style="border-radius: 12px; padding-right: 40px;">
                        <i class="bi bi-eye" onclick="toggleFormPasswordVisibility(this)" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-muted);"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.9rem; border-radius: 14px; font-weight: 800; font-size: 0.85rem;">
                    Generate & Save
                </button>
            </form>
        </div>
    </div>

    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: false
        });

        function openAddModal() {
            document.getElementById('addWifiModal').style.display = 'flex';
            document.getElementById('wifi_ssid').focus();
        }

        function closeAddModal() {
            document.getElementById('addWifiModal').style.display = 'none';
            document.getElementById('addWifiForm').reset();
            toggleFormPassword();
        }

        function toggleFormPassword() {
            const enc = document.getElementById('wifi_encryption').value;
            const container = document.getElementById('passwordFieldContainer');
            const pwd = document.getElementById('wifi_password');
            if (enc === 'nopass') {
                container.style.display = 'none';
                pwd.required = false;
                pwd.value = '';
            } else {
                container.style.display = 'block';
                pwd.required = true;
            }
        }

        function toggleFormPasswordVisibility(icon) {
            const pwdInput = document.getElementById('wifi_password');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                pwdInput.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        function togglePassword(element, plainPassword) {
            const textSpan = element.querySelector('.pwd-mask');
            const icon = element.querySelector('i');
            
            if (textSpan.innerText === '••••••••') {
                textSpan.innerText = plainPassword;
                icon.classList.replace('bi-eye', 'bi-eye-slash');
                
                // Copy to clipboard on show
                navigator.clipboard.writeText(plainPassword).then(() => {
                    Toast.fire({ icon: 'success', title: 'Password copied!' });
                });
            } else {
                textSpan.innerText = '••••••••';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        function saveWifi(e) {
            e.preventDefault();
            const ssid = document.getElementById('wifi_ssid').value;
            const encryption = document.getElementById('wifi_encryption').value;
            const password = document.getElementById('wifi_password').value;

            Swal.fire({
                title: 'Saving Network...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('wifi.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'add',
                    ssid: ssid,
                    encryption: encryption,
                    password: password
                })
            })
            .then(r => r.json())
            .then(res => {
                Swal.close();
                if (res.status === 'success') {
                    closeAddModal();
                    Swal.fire({
                        icon: 'success',
                        title: 'WiFi Saved!',
                        text: res.message,
                        confirmButtonColor: 'var(--primary)'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(() => {
                Swal.close();
                Swal.fire('Error', 'Failed to save WiFi network.', 'error');
            });
        }

        function downloadQR(id, ssid, qrString) {
            const url = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${qrString}`;
            fetch(url)
            .then(r => r.blob())
            .then(blob => {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `WiFi_${ssid.replace(/\s+/g, '_')}_QR.png`;
                link.click();
                Toast.fire({ icon: 'success', title: 'WiFi QR Code saved!' });
            })
            .catch(() => {
                Toast.fire({ icon: 'error', title: 'Failed to download QR.' });
            });
        }

        function deleteNetwork(id, ssid) {
            Swal.fire({
                title: 'Delete WiFi Network?',
                text: `This will remove the credentials and QR code for "${ssid}".`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete network'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Deleting...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    fetch('wifi.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action: 'delete',
                            id: id
                        })
                    })
                    .then(r => r.json())
                    .then(res => {
                        Swal.close();
                        if (res.status === 'success') {
                            const card = document.getElementById(`wifi-card-${id}`);
                            if (card) {
                                card.style.transition = 'all 0.35s ease-out';
                                card.style.opacity = '0';
                                card.style.transform = 'scale(0.8) translateY(20px)';
                                setTimeout(() => {
                                    window.location.reload();
                                }, 350);
                            } else {
                                window.location.reload();
                            }
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    })
                    .catch(() => {
                        Swal.close();
                        Swal.fire('Error', 'Failed to delete network.', 'error');
                    });
                }
            });
        }

        // --- Upload & Scan WiFi QR Logic (jsQR-based, no DOM dependency) ---

        function openScanModal() {
            document.getElementById('scanWifiModal').style.display = 'flex';
            setupDragAndDrop();
        }

        function closeScanModal() {
            document.getElementById('scanWifiModal').style.display = 'none';
            document.getElementById('qr-file-input').value = '';
            document.getElementById('scanFeedback').style.display = 'none';
        }

        function setupDragAndDrop() {
            const dropzone = document.getElementById('dropzone');
            if (dropzone && !dropzone.dataset.listenerAdded) {
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropzone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        dropzone.style.borderColor = 'var(--primary)';
                        dropzone.style.background = 'color-mix(in srgb, var(--primary) 4%, transparent)';
                    }, false);
                });
                ['dragleave', 'drop'].forEach(eventName => {
                    dropzone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        dropzone.style.borderColor = 'var(--border)';
                        dropzone.style.background = 'var(--bg-main)';
                    }, false);
                });
                dropzone.addEventListener('drop', (e) => {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    if (files.length > 0) {
                        const fileInput = document.getElementById('qr-file-input');
                        fileInput.files = files;
                        handleFileSelect(fileInput);
                    }
                }, false);
                dropzone.dataset.listenerAdded = 'true';
            }
        }

        // Decode QR from a canvas at a given crop+size using jsQR
        function decodeCanvasRegion(img, sx, sy, sw, sh, outW, outH, invert) {
            const canvas = document.createElement('canvas');
            canvas.width = outW;
            canvas.height = outH;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, sx, sy, sw, sh, 0, 0, outW, outH);

            const imgData = ctx.getImageData(0, 0, outW, outH);
            const d = imgData.data;

            // Convert to grayscale + optional invert for thresholding
            for (let i = 0; i < d.length; i += 4) {
                let v = 0.2126 * d[i] + 0.7152 * d[i+1] + 0.0722 * d[i+2];
                if (invert) v = v < 128 ? 255 : 0;
                d[i] = d[i+1] = d[i+2] = v;
            }
            ctx.putImageData(imgData, 0, 0);

            const final = ctx.getImageData(0, 0, outW, outH);
            const result = jsQR(final.data, outW, outH, { inversionAttempts: 'both' });
            return result ? result.data : null;
        }

        function loadImage(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = e => {
                    const img = new Image();
                    img.onload = () => resolve(img);
                    img.onerror = () => reject(new Error('Failed to load image.'));
                    img.src = e.target.result;
                };
                reader.onerror = () => reject(new Error('Failed to read file.'));
                reader.readAsDataURL(file);
            });
        }

        async function handleFileSelect(input) {
            const file = input.files[0];
            if (!file) return;

            const feedback = document.getElementById('scanFeedback');
            feedback.style.display = 'inline-flex';

            let decodedText = null;
            let successLabel = null;

            try {
                const img = await loadImage(file);
                const W = img.width;
                const H = img.height;

                // --- Smart crop: locate the white QR block ---
                // Downsample for analysis
                const scale = Math.min(1.0, 1024 / Math.max(W, H));
                const aW = Math.floor(W * scale);
                const aH = Math.floor(H * scale);
                const ac = document.createElement('canvas');
                ac.width = aW; ac.height = aH;
                const actx = ac.getContext('2d');
                actx.drawImage(img, 0, 0, aW, aH);
                const { data: aData } = actx.getImageData(0, 0, aW, aH);

                let minY = aH, maxY = 0, minX = aW, maxX = 0;
                let foundWhite = false;
                const step = 3; // sample every N pixels
                for (let y = 0; y < aH; y += step) {
                    let run = 0, maxRun = 0;
                    for (let x = 0; x < aW; x += step) {
                        const i = (y * aW + x) * 4;
                        if (aData[i] > 200 && aData[i+1] > 200 && aData[i+2] > 200) {
                            run++; if (run > maxRun) maxRun = run;
                        } else run = 0;
                    }
                    if (maxRun > (aW / step) * 0.35) {
                        if (y < minY) minY = y;
                        if (y > maxY) maxY = y;
                        foundWhite = true;
                    }
                }
                for (let x = 0; x < aW; x += step) {
                    let run = 0, maxRun = 0;
                    for (let y = 0; y < aH; y += step) {
                        const i = (y * aW + x) * 4;
                        if (aData[i] > 200 && aData[i+1] > 200 && aData[i+2] > 200) {
                            run++; if (run > maxRun) maxRun = run;
                        } else run = 0;
                    }
                    if (maxRun > (aH / step) * 0.35) {
                        if (x < minX) minX = x;
                        if (x > maxX) maxX = x;
                    }
                }

                // Build scan candidates (boxes in original image coords)
                const candidates = [];

                // Candidate A: smart crop of detected white block
                if (foundWhite && (maxY - minY) > aH * 0.05 && (maxX - minX) > aW * 0.05) {
                    const bx = Math.floor(minX / scale);
                    const by = Math.floor(minY / scale);
                    const bw = Math.min(Math.floor((maxX - minX) / scale * 1.2), W - bx);
                    const bh = Math.min(Math.floor((maxY - minY) / scale * 1.2), H - by);
                    const bSize = Math.max(bw, bh);
                    candidates.push({ label: 'SmartCrop', sx: bx, sy: by, sw: Math.min(bw, W-bx), sh: Math.min(bh, H-by), outW: 600, outH: 600 });
                    candidates.push({ label: 'SmartCrop-inv', sx: bx, sy: by, sw: Math.min(bw, W-bx), sh: Math.min(bh, H-by), outW: 600, outH: 600, inv: true });
                }

                // Candidate B: full image at native res
                candidates.push({ label: 'Full-native', sx: 0, sy: 0, sw: W, sh: H, outW: W, outH: H });

                // Candidate C: full image resized to 1200
                const longSide = Math.max(W, H);
                const rScale = Math.min(1, 1200 / longSide);
                candidates.push({ label: 'Full-1200', sx: 0, sy: 0, sw: W, sh: H, outW: Math.floor(W * rScale), outH: Math.floor(H * rScale) });
                candidates.push({ label: 'Full-1200-inv', sx: 0, sy: 0, sw: W, sh: H, outW: Math.floor(W * rScale), outH: Math.floor(H * rScale), inv: true });

                // Candidate D: center square crop
                const csz = Math.min(W, H);
                const csx = Math.floor((W - csz) / 2);
                const csy = Math.floor((H - csz) / 2);
                candidates.push({ label: 'CenterCrop', sx: csx, sy: csy, sw: csz, sh: csz, outW: 600, outH: 600 });
                candidates.push({ label: 'CenterCrop-inv', sx: csx, sy: csy, sw: csz, sh: csz, outW: 600, outH: 600, inv: true });

                for (const c of candidates) {
                    if (c.outW <= 0 || c.outH <= 0 || c.sw <= 0 || c.sh <= 0) continue;
                    const text = decodeCanvasRegion(img, c.sx, c.sy, c.sw, c.sh, c.outW, c.outH, !!c.inv);
                    console.log(`[jsQR] ${c.label}: ${text ? 'HIT → ' + text.substring(0, 60) : 'miss'}`);
                    if (text) {
                        const parsed = parseWifiQrString(text);
                        if (parsed) {
                            decodedText = text;
                            successLabel = c.label;
                            break;
                        }
                    }
                }

            } catch (err) {
                console.error('QR pipeline error:', err);
            }

            if (decodedText) {
                console.log(`Decoded via: ${successLabel}`);
                feedback.style.display = 'none';
                const wifiConfig = parseWifiQrString(decodedText);

                closeScanModal();
                Swal.fire({
                    title: 'Scanned WiFi Network!',
                    html: `
                        <div style="text-align: left; padding: 0.5rem 1rem; background: var(--bg-main); border-radius:14px; border: 1px solid var(--border); font-size: 0.85rem; font-weight: 600; color: var(--text-main); display:flex; flex-direction:column; gap:6px;">
                            <div style="display:flex; justify-content:space-between;"><span>SSID (WiFi Name):</span><span style="color:var(--primary); font-weight:800;">${wifiConfig.ssid}</span></div>
                            <div style="display:flex; justify-content:space-between;"><span>Password:</span><span style="font-family:monospace; color:var(--text-muted);">${wifiConfig.password ? '•••••••• (Hidden)' : '(None)'}</span></div>
                        </div>
                        <p style="margin-top: 1.25rem; font-size: 0.82rem; margin-bottom: 0;">Would you like to import this network and generate your own styled card?</p>
                    `,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--primary)',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, import network',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Importing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        fetch('wifi.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'add',
                                ssid: wifiConfig.ssid,
                                encryption: wifiConfig.encryption,
                                password: wifiConfig.password
                            })
                        })
                        .then(r => r.json())
                        .then(res => {
                            Swal.close();
                            if (res.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'WiFi Imported!',
                                    text: 'WiFi credentials imported and own styled card successfully generated!',
                                    confirmButtonColor: 'var(--primary)'
                                }).then(() => window.location.reload());
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        })
                        .catch(() => {
                            Swal.close();
                            Swal.fire('Error', 'Failed to save WiFi credentials.', 'error');
                        });
                    }
                });
            } else {
                feedback.style.display = 'none';
                Swal.fire({
                    icon: 'error',
                    title: 'Decoding Failed',
                    text: 'Could not decode QR code from the selected image. Please make sure the image contains a clear WiFi QR code.',
                    confirmButtonColor: 'var(--primary)'
                });
                input.value = '';
            }
        }

        function parseWifiQrString(qrString) {
            if (!qrString || !qrString.toUpperCase().startsWith("WIFI:")) {
                return null;
            }
            
            // Extract values defensively using matching regex patterns
            const ssidMatch = qrString.match(/S:([^;]+)/i);
            const encMatch = qrString.match(/T:([^;]+)/i);
            const pwdMatch = qrString.match(/P:([^;]+)/i);
            
            if (!ssidMatch) return null;
            
            function unescape(str) {
                if (!str) return '';
                return str.replace(/\\;/g, ';')
                          .replace(/\\,/g, ',')
                          .replace(/\\:/g, ':')
                          .replace(/\\\\/g, '\\');
            }
            
            return {
                ssid: unescape(ssidMatch[1]),
                encryption: encMatch ? encMatch[1] : 'nopass',
                password: pwdMatch ? unescape(pwdMatch[1]) : ''
            };
        }
    </script>
</body>
</html>
