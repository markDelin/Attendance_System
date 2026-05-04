<?php
// manage_students.php - Manage Users
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

// Fetch users
$stmt = $pdo->query("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY name");
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$regularUsers = array_filter($allUsers, function($u) {
    return ($u['student_type'] ?? 'regular') === 'regular';
});
$irregularUsers = array_filter($allUsers, function($u) {
    return ($u['student_type'] ?? 'regular') !== 'regular';
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage Students | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <link rel="stylesheet" href="assets/css/AnimatedList.css">
    <script src="assets/js/AnimatedList.js"></script>
    <style>
        .search-area {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--bg-main);
            padding: 1.25rem 0 0.75rem;
        }

        .search-container {
            position: relative;
            max-width: 650px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 0.95rem 1.25rem 0.95rem 3.2rem;
            border-radius: var(--radius-md);
            border: none;
            background: var(--bg-main);
            color: var(--text-main);
            font-size: 0.92rem;
            box-shadow: var(--shadow-neu-in-sm);
            transition: all 0.3s cubic-bezier(0.19, 1, 0.22, 1);
            font-weight: 500;
        }

        .search-input:focus {
            outline: none;
            box-shadow: var(--shadow-neu-in);
            transform: scale(0.995);
        }

        .search-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
            pointer-events: none;
        }

        /* Student Counter */
        .list-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0.25rem 0.75rem;
            max-width: 650px;
            margin: 0 auto;
        }

        .student-count {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
        }

        .student-count strong {
            color: var(--primary);
            font-weight: 800;
        }

        .section-title {
            margin: 3rem 0 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            font-weight: 800;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--bg-main);
            padding-bottom: 0.75rem;
        }

        /* Neumorphic Table Container */
        .data-table-container {
            background: var(--bg-card);
            border: none;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-neu-out);
            margin-bottom: 3rem;
        }

        .student-table {
            width: 100%;
            border-collapse: collapse;
        }

        .student-table th {
            text-align: left;
            padding: 1rem 1.25rem;
            background: var(--bg-card);
            color: var(--text-muted);
            font-weight: 800;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-bottom: 1px solid var(--bg-main);
        }

        .student-table td {
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--bg-main);
            vertical-align: middle;
            color: var(--text-main);
        }

        .student-table tr:hover {
            background: var(--bg-hover);
        }

        /* Avatar Initial */
        .student-avatar {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.82rem;
            font-family: 'Outfit', sans-serif;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            text-transform: uppercase;
        }

        .student-name {
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            font-size: 0.92rem;
            color: var(--text-main);
            text-decoration: none;
            transition: color 0.2s;
            line-height: 1.25;
        }
        .student-name:hover { color: var(--primary); }

        .student-id {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 2px;
            display: block;
        }

        .student-info-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .student-info-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.75rem;
            border-radius: 30px;
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-neu-out-sm);
        }

        .type-regular { background: #f0fdf4; color: #166534; }
        .type-irregular { background: #fff1f2; color: #991b1b; }

        html.dark .type-regular { background: rgba(16,185,129,0.12); color: #6ee7b7; }
        html.dark .type-irregular { background: rgba(239,68,68,0.12); color: #fca5a5; }

        .course-cell {
            font-weight: 600;
            font-size: 0.82rem;
            line-height: 1.3;
        }
        .course-cell small {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
            display: block;
            margin-top: 1px;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: none;
            background: var(--bg-card);
            color: var(--text-muted);
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.2s var(--ease-out-expo);
            cursor: pointer;
            font-size: 0.88rem;
        }

        .btn-action:hover {
            box-shadow: var(--shadow-neu-in-sm);
            color: var(--primary);
            transform: scale(0.93);
        }

        .btn-delete:hover {
            color: var(--danger);
        }

        @media (min-width: 769px) {
            .student-table { display: block; }
            .student-table thead, .student-table tbody { display: block; width: 100%; }
            .student-table tr {
                display: grid !important;
                grid-template-columns: 2.5fr 1fr 0.7fr 0.7fr !important;
                align-items: center;
                border: none !important;
                border-bottom: 1px solid var(--bg-main) !important;
                padding: 0.1rem 0;
            }
            .student-table th, .student-table td {
                padding: 0.85rem 1.25rem !important;
                border: none !important;
            }
            .column-actions { text-align: right; }
        }

        @media (max-width: 768px) {
            .search-area { padding: 0.75rem 0 0.25rem; }
            .list-meta { padding: 0.25rem 0.25rem 0.5rem; }
            .data-table-container { background: transparent; box-shadow: none; overflow: visible; }
            .student-table thead { display: none; }
            .student-table tr {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin-bottom: 0.6rem;
                background: var(--bg-card);
                border-radius: var(--radius-md);
                padding: 0.9rem 1rem;
                box-shadow: var(--shadow-neu-out-sm);
            }
            .student-table td { border: none; padding: 0 !important; }
            .student-table td:first-child { flex: 1; min-width: 0; }
            .column-course { display: none; }
            .column-type { display: none; }
            .column-actions { width: auto; }
            .btn-action { width: 38px; height: 38px; }
            .student-name {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                display: block;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Nav (Standardized) -->
    <?php 
    $navbar_actions = '
        <button onclick="addStudent()" class="btn btn-primary" style="padding: 0.5rem 1rem; border-radius: 20px;">
            <i class="bi bi-person-plus"></i> Add
        </button>
    ';
    include 'includes/navbar.php'; 
    ?>

    <!-- Search Bar -->
    <div class="search-area">
        <div class="container">
            <div class="search-container">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search by name or student ID..." autocomplete="off">
            </div>
            <div class="list-meta">
                <span class="student-count"><strong><?= count($allUsers) ?></strong> students enrolled</span>
            </div>
        </div>
    </div>

    <main class="container">
        
        <div class="scroll-list-container">
            <div class="top-gradient"></div>
            <div class="bottom-gradient"></div>
            
            <div class="scroll-list no-scrollbar" style="max-height: 70vh;">
                <div class="data-table-container">
                    <div class="table-wrapper">
                        <table class="student-table">
                        <thead>
                            <tr>
                                <th class="column-student">Student</th>
                                <th class="column-course hide-mobile">Course & Section</th>
                                <th class="column-type">Type</th>
                                <th class="column-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentBody">
                            <?php 
                            // Avatar color palette
                            $avatarColors = ['#5c6bc0','#42a5f5','#26a69a','#66bb6a','#ec407a','#ab47bc','#ef5350','#ffa726','#8d6e63','#78909c'];
                            foreach ($allUsers as $idx => $user): 
                                $typeClass = ($user['student_type'] ?? 'regular') === 'regular' ? 'type-regular' : 'type-irregular';
                                $initial = strtoupper(substr($user['last_name'] ?? $user['name'] ?? '?', 0, 1));
                                $avatarColor = $avatarColors[$idx % count($avatarColors)];
                            ?>
                                <tr class="student-row animated-item hover-lift" 
                                    data-name="<?= htmlspecialchars($user['name']) ?>"
                                    data-qr="<?= htmlspecialchars($user['qr_code']) ?>"
                                    id="row-<?= htmlspecialchars($user['qr_code']) ?>"
                                    style="margin-bottom: 0;"
                            <?php 
                                $bday = !empty($user['birthday']) ? date('Y-m-d', strtotime($user['birthday'])) : '';
                                echo ' data-firstname="'.htmlspecialchars($user['first_name']??'').'"';
                                echo ' data-lastname="'.htmlspecialchars($user['last_name']??'').'"';
                                echo ' data-middle="'.htmlspecialchars($user['middle_initial']??'').'"';
                                echo ' data-course="'.htmlspecialchars($user['course']??'').'"';
                                echo ' data-section="'.htmlspecialchars($user['section']??'').'"';
                                echo ' data-type="'.htmlspecialchars($user['student_type']??'regular').'"';
                                echo ' data-birthday="'.htmlspecialchars($bday).'"';
                                echo ' data-sex="'.htmlspecialchars($user['sex']??'').'"';
                                echo ' data-civil="'.htmlspecialchars($user['civil_status']??'').'"';
                                echo ' data-religion="'.htmlspecialchars($user['religion']??'').'"';
                                echo ' data-citizenship="'.htmlspecialchars($user['citizenship']??'').'"';
                                echo ' data-contact="'.htmlspecialchars($user['contact_number']??'').'"';
                                echo ' data-email="'.htmlspecialchars($user['email']??'').'"';
                                echo ' data-pob="'.htmlspecialchars($user['place_of_birth']??'').'"';
                                echo ' data-year="'.htmlspecialchars($user['year_level']??'1st').'"';
                                echo ' data-qr="'.htmlspecialchars($user['qr_code']??'').'"';
                                echo ' data-name="'.htmlspecialchars($user['name']??'').'"';
                                echo ' data-bday-img="'.htmlspecialchars($user['birthday_image']??'').'"';
                            ?>
                        >
                            <td data-label="Student" class="column-student">
                                <div class="student-info-cell">
                                    <div class="student-avatar" style="background: <?= $avatarColor ?>;">
                                        <?= $initial ?>
                                    </div>
                                    <div class="student-info-text">
                                        <a href="profile.php?qr=<?= urlencode($user['qr_code']) ?>" class="student-name">
                                            <?= htmlspecialchars($user['name']) ?>
                                        </a>
                                        <span class="student-id"><?= htmlspecialchars($user['qr_code']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Course" class="column-course hide-mobile">
                                <div class="course-cell">
                                    <?= htmlspecialchars($user['course'] ?? '—') ?>
                                    <small><?= htmlspecialchars($user['section'] ?? '') ?></small>
                                </div>
                            </td>
                            <td data-label="Type" class="column-type">
                                <span class="type-badge <?= $typeClass ?>"><?= ($user['student_type'] ?? 'regular') ?></span>
                            </td>
                            <td data-label="Actions" class="column-actions">
                                <div style="display:flex; gap:6px; justify-content:flex-end;">
                                    <button onclick="editUser(this)" class="btn-action hover-press" title="Edit Profile">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <?php if(($user['student_type'] ?? 'regular') === 'irregular'): ?>
                                        <button onclick="manageSubjects(this)" class="btn-action hover-press" style="color:var(--warning)" title="Manage Subjects">
                                            <i class="bi bi-journal-text"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="deleteUser(this)" class="btn-action btn-delete hover-press" title="Move to Trash">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                        </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </main>
    <?php include 'includes/footer.php'; ?>
    


    <!-- Professional Management Modal -->
    <style>
        /* ── Modal Professional Overhaul ── */
        #managementModal .modal-body {
            max-width: 680px;
            padding: 0;
            border-radius: 28px;
            overflow: hidden;
            background: var(--bg-card);
        }

        .modal-header-pro {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.75rem 2rem 1.25rem;
            border-bottom: 1px solid color-mix(in srgb, var(--text-muted) 12%, transparent);
        }

        .modal-header-pro .header-left {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .modal-header-pro .header-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--primary) 35%, transparent);
        }

        .modal-header-pro .header-text h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.2;
        }

        .modal-header-pro .header-text small {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .modal-close-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: var(--bg-main);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: var(--shadow-neu-out-sm);
            font-size: 1rem;
        }

        .modal-close-btn:hover {
            box-shadow: var(--shadow-neu-in-sm);
            color: var(--danger);
            transform: scale(0.95);
        }

        .modal-scroll-area {
            padding: 1.5rem 2rem 2rem;
            max-height: calc(85vh - 160px);
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .modal-scroll-area::-webkit-scrollbar { width: 4px; }
        .modal-scroll-area::-webkit-scrollbar-track { background: transparent; }
        .modal-scroll-area::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--text-muted) 25%, transparent);
            border-radius: 10px;
        }

        /* ── Section Groups ── */
        .form-section {
            margin-bottom: 1.75rem;
            animation: sectionFadeIn 0.4s var(--ease-out-expo) both;
        }

        .form-section:nth-child(2) { animation-delay: 0.05s; }
        .form-section:nth-child(3) { animation-delay: 0.1s; }
        .form-section:nth-child(4) { animation-delay: 0.15s; }
        .form-section:nth-child(5) { animation-delay: 0.2s; }

        @keyframes sectionFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid color-mix(in srgb, var(--text-muted) 10%, transparent);
        }

        .section-header i {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: color-mix(in srgb, var(--primary) 12%, transparent);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .section-header span {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-muted);
        }

        /* ── Field Grid ── */
        .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem;
        }

        .field-grid .full-width {
            grid-column: 1 / -1;
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .field-group label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
            margin: 0;
            border: none;
            padding: 0;
        }

        .field-group label .req {
            color: var(--danger);
            font-size: 0.75rem;
        }

        .field-group .form-control {
            padding: 0.7rem 1rem;
            font-size: 0.88rem;
            border-radius: 12px;
        }

        /* ── Divider ── */
        .form-divider {
            height: 1px;
            background: color-mix(in srgb, var(--text-muted) 10%, transparent);
            margin: 0.5rem 0 1.5rem;
        }

        /* ── Birthday Thumbnail Pro ── */
        .bday-upload-area {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            padding: 1rem;
            background: var(--bg-main);
            border-radius: 14px;
            box-shadow: var(--shadow-neu-in-sm);
        }

        .bday-preview-box {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            overflow: hidden;
            box-shadow: var(--shadow-neu-out-sm);
            flex-shrink: 0;
            font-size: 1.2rem;
        }

        .bday-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .bday-upload-fields {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .bday-upload-fields .form-control {
            padding: 0.55rem 0.85rem;
            font-size: 0.8rem;
            border-radius: 10px;
        }

        .bday-hint {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ── Footer Actions ── */
        .modal-footer-pro {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 2rem;
            border-top: 1px solid color-mix(in srgb, var(--text-muted) 10%, transparent);
            background: color-mix(in srgb, var(--bg-main) 50%, var(--bg-card));
        }

        .modal-footer-pro .btn-discard {
            padding: 0.7rem 1.5rem;
            border-radius: 12px;
            border: none;
            background: var(--bg-card);
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.2s var(--ease-out-expo);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .modal-footer-pro .btn-discard:hover {
            box-shadow: var(--shadow-neu-in-sm);
            color: var(--danger);
            transform: scale(0.97);
        }

        .modal-footer-pro .btn-save {
            padding: 0.7rem 2rem;
            border-radius: 12px;
            border: none;
            background: var(--primary);
            color: white;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.25s var(--ease-out-expo);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 14px color-mix(in srgb, var(--primary) 30%, transparent);
        }

        .modal-footer-pro .btn-save:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px color-mix(in srgb, var(--primary) 40%, transparent);
        }

        .modal-footer-pro .btn-save:active {
            transform: scale(0.97);
        }

        /* ── Mobile Responsive ── */
        @media (max-width: 768px) {
            #managementModal .modal-body {
                width: 100%;
                max-width: 100%;
                border-radius: 24px 24px 0 0;
                max-height: 92vh;
                align-self: flex-end;
            }

            .modal-header-pro { padding: 1.25rem 1.25rem 1rem; }
            .modal-scroll-area { padding: 1.25rem; max-height: calc(92vh - 150px); }
            .modal-footer-pro { padding: 1rem 1.25rem; }

            .field-grid {
                grid-template-columns: 1fr;
            }

            .field-grid .half-mobile {
                grid-column: auto;
            }
        }
    </style>

    <div id="managementModal" class="modal-overlay" onclick="if(event.target == this) closeManagementModal()">
        <div class="modal-body">
            <!-- Header -->
            <div class="modal-header-pro">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="bi bi-person-badge" id="modalIcon"></i>
                    </div>
                    <div class="header-text">
                        <h3 id="modalTitle">Student Profile</h3>
                        <small id="modalSubtitle">Complete all required fields</small>
                    </div>
                </div>
                <button onclick="closeManagementModal()" class="modal-close-btn" title="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <form id="managementForm" onsubmit="submitManagementForm(event)">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="qr_original" id="modalQrOriginal">

                <div class="modal-scroll-area">

                    <!-- Section: Identification -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="bi bi-qr-code"></i>
                            <span>Identification</span>
                        </div>
                        <div class="field-grid">
                            <div class="field-group full-width">
                                <label>Student ID / QR Code <span class="req">*</span></label>
                                <input type="text" name="qr_code" id="m-qr" class="form-control" required placeholder="e.g. 2024-0001">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Personal Information -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="bi bi-person"></i>
                            <span>Personal Information</span>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <label>First Name <span class="req">*</span></label>
                                <input type="text" name="first_name" id="m-fname" class="form-control" required placeholder="John">
                            </div>
                            <div class="field-group">
                                <label>Last Name <span class="req">*</span></label>
                                <input type="text" name="last_name" id="m-lname" class="form-control" required placeholder="Doe">
                            </div>
                            <div class="field-group full-width">
                                <label>Middle Initial</label>
                                <input type="text" name="middle_initial" id="m-middle" class="form-control" maxlength="2" placeholder="e.g. M" style="max-width: 140px;">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Contact Details -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="bi bi-envelope"></i>
                            <span>Contact Details</span>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <label>Email Address</label>
                                <input type="email" name="email" id="m-email" class="form-control" placeholder="john@example.com">
                            </div>
                            <div class="field-group">
                                <label>Mobile Number</label>
                                <input type="text" name="contact_number" id="m-contact" class="form-control" placeholder="0917XXXXXXX">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Academic Details -->
                    <div class="form-section">
                        <div class="section-header">
                            <i class="bi bi-mortarboard"></i>
                            <span>Academic Details</span>
                        </div>
                        <div class="field-grid">
                            <div class="field-group">
                                <label>Course / Strand</label>
                                <input type="text" name="course" id="m-course" class="form-control" placeholder="e.g. BSCS">
                            </div>
                            <div class="field-group">
                                <label>Section / Set</label>
                                <input type="text" name="section" id="m-section" class="form-control" placeholder="e.g. 2-A">
                            </div>
                            <div class="field-group">
                                <label>Student Type</label>
                                <select name="student_type" id="m-type" class="form-control">
                                    <option value="regular">Regular</option>
                                    <option value="irregular">Irregular / Other</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>Year Level</label>
                                <select name="year_level" id="m-year" class="form-control">
                                    <option value="1st">1st Year</option>
                                    <option value="2nd">2nd Year</option>
                                    <option value="3rd">3rd Year</option>
                                    <option value="4th">4th Year</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Extended Fields (Edit Mode Only) -->
                    <div id="extendedFields">
                        <div class="form-divider"></div>

                        <!-- Section: Demographics -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="bi bi-clipboard2-data"></i>
                                <span>Demographics</span>
                            </div>
                            <div class="field-grid">
                                <div class="field-group">
                                    <label>Birthday</label>
                                    <input type="date" name="birthday" id="m-birthday" class="form-control">
                                </div>
                                <div class="field-group">
                                    <label>Sex</label>
                                    <select name="sex" id="m-sex" class="form-control">
                                        <option value="">Select...</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label>Civil Status</label>
                                    <input type="text" name="civil_status" id="m-civil" class="form-control" placeholder="e.g. Single">
                                </div>
                                <div class="field-group">
                                    <label>Religion</label>
                                    <input type="text" name="religion" id="m-religion" class="form-control" placeholder="e.g. Catholic">
                                </div>
                                <div class="field-group">
                                    <label>Citizenship</label>
                                    <input type="text" name="citizenship" id="m-citizenship" class="form-control" placeholder="e.g. Filipino">
                                </div>
                                <div class="field-group">
                                    <label>Place of Birth</label>
                                    <input type="text" name="place_of_birth" id="m-pob" class="form-control" placeholder="e.g. Manila">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Birthday Media -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="bi bi-image"></i>
                                <span>Birthday Thumbnail</span>
                            </div>
                            <div class="bday-upload-area">
                                <div class="bday-preview-box" id="m-bday-preview">
                                    <i class="bi bi-image"></i>
                                </div>
                                <div class="bday-upload-fields">
                                    <input type="text" name="birthday_image" id="m-bday-img" class="form-control" placeholder="Paste image URL...">
                                    <input type="file" id="m-bday-upload" class="form-control" accept="image/*">
                                </div>
                            </div>
                            <div class="bday-hint">
                                <i class="bi bi-info-circle"></i>
                                Optional image for automatic birthday greetings
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer Actions -->
                <div class="modal-footer-pro">
                    <button type="button" onclick="closeManagementModal()" class="btn-discard">
                        <i class="bi bi-x"></i> Discard
                    </button>
                    <button type="submit" class="btn-save">
                        <i class="bi bi-check2" id="saveIcon"></i>
                        <span id="saveText">Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Search
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.student-row').forEach(row => {
                const name = (row.dataset.name || '').toLowerCase();
                const qr = (row.dataset.qr || '').toLowerCase();
                row.style.display = (name.includes(term) || qr.includes(term)) ? '' : 'none';
            });
        });

        const Toast = Swal.mixin({
            toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });

        function showModal() { document.getElementById('managementModal').style.display = 'flex'; }
        function closeManagementModal() { document.getElementById('managementModal').style.display = 'none'; }

        function addStudent() {
            const f = document.getElementById('managementForm');
            f.reset();
            document.getElementById('modalTitle').innerText = 'Add New Student';
            document.getElementById('modalSubtitle').innerText = 'Complete all required fields';
            document.getElementById('modalIcon').className = 'bi bi-person-plus';
            document.getElementById('modalAction').value = 'add';
            document.getElementById('m-qr').readOnly = false;
            document.getElementById('extendedFields').style.display = 'none';
            document.getElementById('saveIcon').className = 'bi bi-plus-lg';
            document.getElementById('saveText').innerText = 'Add Student';
            showModal();
        }

        function editUser(btn) {
            const d = btn.closest('.student-row').dataset;
            const f = document.getElementById('managementForm');
            f.reset();
            
            document.getElementById('modalTitle').innerText = 'Edit Student Profile';
            document.getElementById('modalSubtitle').innerText = 'Modify student record details';
            document.getElementById('modalIcon').className = 'bi bi-pencil-square';
            document.getElementById('modalAction').value = 'update';
            document.getElementById('modalQrOriginal').value = d.qr;
            document.getElementById('extendedFields').style.display = 'block';
            document.getElementById('saveIcon').className = 'bi bi-check2';
            document.getElementById('saveText').innerText = 'Save Changes';
            
            document.getElementById('m-qr').value = d.qr;
            document.getElementById('m-qr').readOnly = true;
            document.getElementById('m-fname').value = d.firstname;
            document.getElementById('m-lname').value = d.lastname;
            document.getElementById('m-middle').value = d.middle;
            document.getElementById('m-email').value = d.email;
            document.getElementById('m-contact').value = d.contact;
            document.getElementById('m-course').value = d.course;
            document.getElementById('m-section').value = d.section;
            document.getElementById('m-birthday').value = d.birthday;
            document.getElementById('m-sex').value = d.sex;
            document.getElementById('m-civil').value = d.civil;
            document.getElementById('m-religion').value = d.religion;
            document.getElementById('m-pob').value = d.pob;
            document.getElementById('m-year').value = d.year;
            document.getElementById('m-type').value = d.type;
            document.getElementById('m-citizenship').value = d.citizenship;
            
            const bdayImg = d.bdayImg || '';
            document.getElementById('m-bday-img').value = bdayImg;
            const preview = document.getElementById('m-bday-preview');
            if (bdayImg) {
                preview.innerHTML = `<img src="${bdayImg}" style="width:100%; height:100%; object-fit:cover;">`;
            } else {
                preview.innerHTML = '<i class="bi bi-image"></i>';
            }

            showModal();
        }

        function submitManagementForm(e) {
            e.preventDefault();
            const f = e.target;
            const formData = new FormData(f);
            
            fetch('api/manage_users.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    closeManagementModal();
                    Swal.fire({
                        title: 'Success!',
                        text: res.message || 'Student database updated successfully.',
                        icon: 'success',
                        confirmButtonColor: 'var(--primary)',
                        confirmButtonText: 'Great'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Network Error', 'error'));
        }

        async function deleteUser(btn) {
            const row = btn.closest('.student-row');
            const id = row.dataset.qrOriginal || row.dataset.qr;
            const name = row.dataset.nameDisplay || row.dataset.name;

            const { value: reason } = await Swal.fire({
                title: 'Record Removal',
                text: `Removing ${name} from active records. Please select a reason:`,
                icon: 'warning',
                input: 'select',
                inputOptions: {
                    'Unknown': 'Unknown / General Removal',
                    'Dropped Out': 'Dropped Out',
                    'Transferred': 'Transferred to Other School',
                    'Graduated': 'Graduated',
                    'Duplicate Record': 'Duplicate Record',
                    'Disciplinary': 'Disciplinary Action'
                },
                inputPlaceholder: 'Select a reason',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Confirm Removal',
                inputValidator: (value) => {
                    return new Promise((resolve) => {
                        resolve(); // Reason is optional, defaults to Unknown if somehow skipped
                    });
                }
            });

            if (reason) {
                fetch('api/manage_users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'delete', qr_code: id, reason: reason })
                })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                         Swal.fire({
                            title: 'Removed!',
                            text: 'The record has been updated and moved to archives.',
                            icon: 'success',
                            confirmButtonColor: 'var(--primary)'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
            }
        }

        function manageSubjects(btn) {
            const row = btn.closest('.student-row');
            const qr = row.dataset.qr;
            const name = row.dataset.name;

            Swal.fire({
                title: 'Loading Subjects...',
                didOpen: () => { Swal.showLoading(); }
            });
            
            fetch('api/manage_enrollment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_enrollments', qr_code: qr })
            })
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if(data.status !== 'success') {
                    Swal.fire('Error', data.message, 'error');
                    return;
                }
                
                // Build Grouped Checkbox List
                let html = '<div style="text-align:left; max-height:400px; overflow-y:auto; border:1px solid #ddd; padding:15px; background:#f9fafb; border-radius:8px;">';
                if(data.all_subjects.length === 0) {
                    html += '<p style="color:#666; font-style:italic;">No subjects available. Please create subjects first.</p>';
                } else {
                    const enrolledSet = new Set(data.enrolled_ids.map(String));
                    
                    const grouped = {};
                    data.all_subjects.forEach(s => {
                        const sy = s.school_year || 'No School Year';
                        const sem = s.semester || 'No Semester';
                        if(!grouped[sy]) grouped[sy] = {};
                        if(!grouped[sy][sem]) grouped[sy][sem] = [];
                        grouped[sy][sem].push(s);
                    });

                    for (const sy in grouped) {
                        html += `<div style="background:var(--primary); color:white; padding:5px 10px; border-radius:4px; font-size:0.75rem; font-weight:700; margin-top:15px; margin-bottom:10px;">${sy}</div>`;
                        for (const sem in grouped[sy]) {
                            html += `<div style="font-size:0.7rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin:8px 0 5px 5px; border-left: 2px solid #e5e7eb; padding-left: 8px;">${sem}</div>`;
                            grouped[sy][sem].forEach(sub => {
                                const isChecked = enrolledSet.has(String(sub.id)) ? 'checked' : '';
                                html += `
                                    <div style="margin-bottom:6px; background:white; padding:8px; border-radius:6px; border:1px solid #e5e7eb;">
                                        <label style="cursor:pointer; display:flex; align-items:center; margin:0;">
                                            <input type="checkbox" class="swal-sub-check" value="${sub.id}" ${isChecked} style="margin-right:10px; width:18px; height:18px;">
                                            <span style="font-size:0.85rem; font-weight:500; color:var(--text-main);">${sub.name}</span>
                                        </label>
                                    </div>
                                `;
                            });
                        }
                    }
                }
                html += '</div>';
                
                Swal.fire({
                    title: `Manage Subjects for<br>${name}`,
                    html: html,
                    showCancelButton: true,
                    confirmButtonText: 'Save Enrollment',
                    confirmButtonColor: 'var(--primary)',
                    preConfirm: () => {
                        const checked = Array.from(document.querySelectorAll('.swal-sub-check:checked')).map(el => el.value);
                        return fetch('api/manage_enrollment.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ 
                                action: 'save_enrollments', 
                                qr_code: qr,
                                subjects: JSON.stringify(checked)
                            })
                        })
                        .then(r => r.json())
                        .then(res => {
                            if(res.status !== 'success') throw new Error(res.message);
                            return res;
                        })
                        .catch(err => {
                            Swal.showValidationMessage(err.message);
                        });
                    }
                }).then(res => {
                    if(res.isConfirmed) {
                        Swal.fire('Success', 'Subject enrollment updated.', 'success');
                    }
                });
            })
            .catch(err => Swal.fire('Error', 'Failed to load subjects', 'error'));
        }

        // Initialize Animated List
        document.addEventListener('DOMContentLoaded', () => {
            initAnimatedList('.scroll-list-container', {
                onItemSelect: (item) => {
                    const profileLink = item.querySelector('.student-name');
                    if(profileLink) window.location.href = profileLink.href;
                }
            });
        });

        // Birthday Image Upload Handler for Modal
        document.getElementById('m-bday-upload').addEventListener('change', async function(e) {
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
                    document.getElementById('m-bday-img').value = res.path;
                    document.getElementById('m-bday-preview').innerHTML = `<img src="${res.path}" style="width:100%; height:100%; object-fit:cover;">`;
                    Toast.fire({ icon: 'success', title: 'Thumbnail uploaded' });
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
