<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// includes/navbar.php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$currentUrl = $_SERVER['REQUEST_URI'] ?? '';

// 0. Explicit Back Navigation (Bypasses Strategy A/B to ENSURE no loops)
if (isset($_GET['nav']) && $_GET['nav'] === 'back') {
    if (!empty($_SESSION['nav_history'])) {
        array_pop($_SESSION['nav_history']); // Pop current page
        // The actual back target will be determined by the new stack
    }
}

// 1. Navigation Stack Logic (Deep History + Loop Prevention)
if (!isset($_SESSION['nav_history'])) $_SESSION['nav_history'] = [];

// Track page only if it's a real user-facing PHP page (Ignore assets/icons)
$isAsset = preg_match('/\.(ico|css|js|png|jpg|jpeg|svg|gif|map|json)$/i', $currentUrl);
$isApi = strpos($currentUrl, 'api/') !== false || strpos($currentUrl, 'ajax') !== false;

if (!$isAsset && !$isApi) {
    $existingIndex = -1;
    foreach ($_SESSION['nav_history'] as $idx => $entry) {
        if ($entry['url'] === $currentUrl) {
            $existingIndex = $idx;
            break;
        }
    }

    if ($existingIndex !== -1) {
        // If it exists, rewind history to this point (truncates loops)
        $_SESSION['nav_history'] = array_slice($_SESSION['nav_history'], 0, $existingIndex + 1);
    } else {
        $_SESSION['nav_history'][] = [
            'page' => $currentPage,
            'url' => $currentUrl
        ];
        if (count($_SESSION['nav_history']) > 15) array_shift($_SESSION['nav_history']);
    }
}

// 2. Smart Back URL Resolution
$backUrl = 'index.php'; 
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

// STRATEGY A: Use Navigation History PRIMARY
$historyCount = count($_SESSION['nav_history']);
$foundHistoryBack = false;

if ($historyCount >= 2) {
    $prevIndex = $historyCount - 2;
    for ($i = $prevIndex; $i >= 0; $i--) {
        if ($_SESSION['nav_history'][$i]['page'] !== $currentPage) {
            $backUrl = $_SESSION['nav_history'][$i]['url'];
            $foundHistoryBack = true;
            break;
        }
    }
}

// STRATEGY B: Fallbacks
if (!$foundHistoryBack) {
    if (!empty($referer) && strpos($referer, $host) !== false && strpos($referer, $currentPage) === false) {
        $backUrl = $referer;
    } else {
        switch($currentPage) {
            case 'profile.php': 
            case 'recycle_bin.php': 
            case 'manual.php': $backUrl = 'index.php'; break;
            case 'student_history.php': 
                $qr_param = $_GET['qr_code'] ?? $_GET['qr'] ?? '';
                $backUrl = $qr_param ? "profile.php?qr=$qr_param" : 'manage_students.php'; 
                break;
            case 'view_subject_attendance.php': 
            case 'view_subjects_list.php': 
                $backUrl = 'view_attendance.php'; break;
            case 'subjects.php':
                $backUrl = 'settings.php'; break;
            case 'admin_restore.php': 
                $backUrl = 'recycle_bin.php'; break;
            case 'orders.php':
            case 'manage_products.php':
                $backUrl = 'index.php'; break;
            default:
                $backUrl = 'index.php';
        }
    }
}

// 3. Header Title Mapping
$isHome = $currentPage === 'index.php';
$title = 'Attendance System';
switch($currentPage) {
    case 'scan.php': $title = 'Scanner'; break;
    case 'manual.php': $title = 'Manual Entry'; break;
    case 'view_attendance.php': $title = 'Records Center'; break;
    case 'manage_students.php': $title = 'Classmate Database'; break;
    case 'calendar.php': $title = 'Attendance Calendar'; break;
    case 'groups.php': $title = 'Group Randomizer'; break;
    case 'markdown_editor.php': $title = 'Markdown Editor'; break;
    case 'reattendance.php': $title = 'Re-attendance'; break;
    case 'settings.php': $title = 'System Settings'; break;
    case 'subjects.php': $title = 'Subject Portal'; break;
    case 'view_subjects_list.php': $title = 'Subject Archive'; break;
    case 'profile.php': $title = 'Classmate Profile'; break;
    case 'student_history.php': $title = 'Attendance History'; break;
    case 'recycle_bin.php': $title = 'Recycle Bin'; break;
    case 'orders.php': $title = 'Order Log'; break;
    case 'manage_products.php': $title = 'Inventory Control'; break;
    case 'wifi.php': $title = 'WiFi Collection'; break;
    case 'announcements.php': $title = 'Announcements'; break;
}

// Logic for Smart Active State (Bottom Nav)
$isRecords = in_array($currentPage, ['view_attendance.php', 'view_subjects_list.php', 'view_subject_attendance.php']);
$isStudents = in_array($currentPage, ['manage_students.php', 'profile.php', 'student_history.php', 'recycle_bin.php', 'admin_restore.php']);
$isSchedule = in_array($currentPage, ['calendar.php']);
$isManual = in_array($currentPage, ['manual.php']);
$isScan = in_array($currentPage, ['scan.php']);

// Explicit Back Support for the actual button
$explicitBackUrl = $backUrl . (strpos($backUrl, '?') !== false ? '&' : '?') . 'nav=back';
?>

<style>
    .desktop-nav-link {
        color: var(--text-muted) !important;
        background: transparent;
    }
    .desktop-nav-link:hover {
        color: var(--primary) !important;
        background: var(--bg-card) !important;
    }
    .desktop-nav-link.active {
        color: var(--primary) !important;
        background: var(--bg-card) !important;
        box-shadow: var(--shadow-neu-out-sm) !important;
    }
</style>

<!-- Desktop Top Navbar -->
<nav class="navbar" id="topNavbar">
    <?php if ($isHome): ?>
        <div class="brand flex-center" style="gap: 12px;">
            <i class="bi bi-qr-code-scan" style="font-size: 1.5rem;"></i>
            <h3 style="margin:0;">Attendance<span class="text-gradient"> System</span></h3>
        </div>
        
        <!-- Desktop Nav Links (Home) -->
        <div class="desktop-nav-links hide-mobile" style="display: flex; align-items: center; gap: 6px; margin: 0 auto; background: var(--bg-hover); padding: 4px; border-radius: 12px; border: 1px solid var(--border);">
            <a href="index.php" class="desktop-nav-link <?= $isHome ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Home</a>
            <a href="scan.php" class="desktop-nav-link <?= $isScan ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">QR Scanner</a>
            <a href="manage_students.php" class="desktop-nav-link <?= $isStudents ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Classmates</a>
            <a href="manual.php" class="desktop-nav-link <?= $isManual ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Manual Entry</a>
            <a href="view_attendance.php" class="desktop-nav-link <?= $isRecords ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Reports</a>
            <a href="calendar.php" class="desktop-nav-link <?= $isSchedule ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Calendar</a>
            <a href="wifi.php" class="desktop-nav-link <?= ($currentPage === 'wifi.php') ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">WiFi</a>
            <a href="settings.php" class="desktop-nav-link <?= ($currentPage === 'settings.php') ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Settings</a>
        </div>

        <div style="display: flex; align-items: center; gap: 15px;">
            <button onclick="openGlobalSearch()" class="btn btn-ghost" style="padding: 0.4rem 1.25rem; border-radius: 50px; font-size: 0.85rem; color: var(--text-muted); background: rgba(0,0,0,0.03); border: none;">
                <i class="bi bi-search"></i> <span class="d-none-mobile" style="margin-left: 5px;">Search</span>
            </button>
        </div>
    <?php else: ?>
        <div class="flex-center" style="gap: 12px;">
            <a href="<?= $explicitBackUrl ?>" class="btn btn-ghost" style="padding: 0; border-radius: 50%; width: 40px; height: 40px; display: flex; justify-content: center; align-items: center; border: none; background: rgba(0,0,0,0.03);">
                <i class="bi bi-arrow-left" style="font-size: 1.2rem;"></i>
            </a>
            <div style="display: flex; flex-direction: column;">
                <h3 style="margin:0; font-size: 1.1rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.1;"><?= htmlspecialchars($title) ?></h3>
                <?php if (isset($header_subtitle)): ?>
                    <small style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;"><?= htmlspecialchars($header_subtitle) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Desktop Nav Links (Inner Pages) -->
        <div class="desktop-nav-links hide-mobile" style="display: flex; align-items: center; gap: 6px; margin: 0 auto; background: var(--bg-hover); padding: 4px; border-radius: 12px; border: 1px solid var(--border);">
            <a href="index.php" class="desktop-nav-link <?= $isHome ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Home</a>
            <a href="scan.php" class="desktop-nav-link <?= $isScan ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">QR Scanner</a>
            <a href="manage_students.php" class="desktop-nav-link <?= $isStudents ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Classmates</a>
            <a href="manual.php" class="desktop-nav-link <?= $isManual ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Manual Entry</a>
            <a href="view_attendance.php" class="desktop-nav-link <?= $isRecords ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Reports</a>
            <a href="calendar.php" class="desktop-nav-link <?= $isSchedule ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Calendar</a>
            <a href="wifi.php" class="desktop-nav-link <?= ($currentPage === 'wifi.php') ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">WiFi</a>
            <a href="settings.php" class="desktop-nav-link <?= ($currentPage === 'settings.php') ? 'active' : '' ?>" style="text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px; transition: all 0.2s;">Settings</a>
        </div>
        
        <div class="flex-center" style="gap: 10px;">
            <?php if (isset($navbar_actions)): ?>
                <?= $navbar_actions ?>
            <?php endif; ?>
            <a href="announcements.php" class="btn btn-ghost" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; border: 1px solid var(--border); background: var(--bg-card);" title="Announcements">
                <i class="bi bi-megaphone" style="font-size: 0.95rem; color: var(--text-muted);"></i>
            </a>
            <button onclick="openGlobalSearch()" class="btn btn-ghost" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; border: 1px solid var(--border); background: var(--bg-card);" title="Search (S)">
                <i class="bi bi-search" style="font-size: 0.95rem; color: var(--text-muted);"></i>
            </button>
        </div>
    <?php endif; ?>
</nav>

<!-- Mobile Bottom Navigation -->
<nav class="bottom-nav">
    <div class="bottom-nav-items">
        
        <a href="index.php" class="nav-item <?= $isHome ? 'active' : '' ?>">
            <i class="bi bi-house"></i>
            <span>Home</span>
        </a>

        <a href="manage_students.php" class="nav-item <?= $isStudents ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span>Classmates</span>
        </a>

        <!-- FAB for Scan -->
        <a href="scan.php" class="nav-fab <?= $isScan ? 'active' : '' ?>">
            <i class="bi bi-qr-code-scan"></i>
        </a>

        <a href="manual.php" class="nav-item <?= $isManual ? 'active' : '' ?>">
            <i class="bi bi-pencil-square"></i>
            <span>Manual</span>
        </a>

        <a href="view_attendance.php" class="nav-item <?= $isRecords ? 'active' : '' ?>">
            <i class="bi bi-clipboard-data"></i>
            <span>Reports</span>
        </a>

        <div class="nav-indicator" id="navIndicator"></div>
    </div>
</nav>

<script>
    // Navbar Scroll Effect
    window.addEventListener('scroll', () => {
        const nav = document.getElementById('topNavbar');
        if (window.scrollY > 15) {
            nav.classList.add('scrolled');
        } else {
            nav.classList.remove('scrolled');
        }
    });

    // Indicator logic
    document.addEventListener('DOMContentLoaded', () => {
        const activeItem = document.querySelector('.bottom-nav .nav-item.active, .bottom-nav .nav-fab.active');
        const indicator = document.getElementById('navIndicator');
        if (activeItem && indicator) {
            const rect = activeItem.getBoundingClientRect();
            const parentRect = activeItem.parentElement.getBoundingClientRect();
            indicator.style.left = (rect.left - parentRect.left + rect.width / 2 - 16) + 'px';
            indicator.style.opacity = '1';
        }
    });
</script>
<script src="assets/js/swal_custom.js"></script>
<script src="assets/js/toast.js"></script>

<?php include 'includes/search_overlay.php'; ?>
