<?php
// subjects.php - Standalone Academic Subject Portal
require 'includes/db.php';

// Fetch Subjects for Subject Management Section with SQLite group_concat schedules
$subjects = $pdo->query("
    SELECT s.*, 
           (SELECT GROUP_CONCAT(day_of_week || ' ' || start_time || '-' || end_time, '||') 
            FROM schedules 
            WHERE subject_id = s.id) as schedule_list
    FROM subjects s
    ORDER BY s.school_year DESC, s.semester DESC, s.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Calculate metrics dynamically
$totalCount = count($subjects);
$academicCount = count(array_filter($subjects, function($s) { return ($s['category'] ?? 'subject') === 'subject'; }));
$eventCount = $totalCount - $academicCount;

// Fetch current active school year
$stmt = $pdo->query("SELECT active_school_year FROM settings LIMIT 1");
$activeYear = $stmt ? ($stmt->fetchColumn() ?: '') : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Portal | QR Tools</title>
    <link href="assets/css/style.css?v=1.3" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        /* Premium subject card layout styling */
        .subjects-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow-neu-out);
            padding: 2.5rem;
            margin-bottom: 2rem;
            transition: all 0.3s;
        }
        .subjects-card:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            transform: translateY(-1px);
        }

        .subjects-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2rem;
            padding-bottom: 0.85rem;
            border-bottom: 2px solid color-mix(in srgb, var(--text-muted) 8%, transparent);
        }
        .subjects-header-icon {
            font-size: 1.15rem;
            color: var(--primary);
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: color-mix(in srgb, var(--primary) 10%, transparent);
            box-shadow: var(--shadow-neu-out-sm);
        }
        .subjects-card-header h4 {
            margin: 0;
            font-weight: 900;
            letter-spacing: -0.04em;
            font-size: 1.15rem;
            font-family: 'Outfit', sans-serif;
            color: var(--text-main);
        }

        /* Subject Management Row Styles */
        .subj-row:last-child { border-bottom: none !important; }
        .subj-row:hover { background: color-mix(in srgb, var(--primary) 4%, transparent); }
        .subj-action-btn {
            width: 34px; height: 34px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--border); background: var(--bg-card);
            color: var(--text-muted); cursor: pointer; font-size: 0.85rem;
            transition: all 0.2s cubic-bezier(0.16,1,0.3,1);
        }
        .subj-action-btn:hover { background: var(--bg-hover); color: var(--primary); border-color: var(--primary); transform: translateY(-1px); }
        .subj-delete-btn:hover { color: var(--danger); border-color: var(--danger); }

        /* ── Premium Subject Custom Modals Overhaul ── */
        .custom-modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .custom-modal-overlay.active {
            opacity: 1;
            display: flex;
        }
        .custom-modal-body {
            background: var(--bg-card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-out-lg);
            border-radius: 24px;
            width: 95%;
            max-width: 480px;
            padding: 0;
            overflow: hidden;
            position: relative;
            transform: scale(0.92) translateY(10px);
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .custom-modal-overlay.active .custom-modal-body {
            transform: scale(1) translateY(0);
        }
        .custom-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem 1.75rem 1rem;
            border-bottom: 1px solid color-mix(in srgb, var(--text-muted) 12%, transparent);
        }
        .custom-modal-header .header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .custom-modal-header .header-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: color-mix(in srgb, var(--primary) 10%, transparent);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: var(--shadow-neu-out-sm);
        }
        .custom-modal-header .header-text h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.25;
            color: var(--text-main);
        }
        .custom-modal-header .header-text small {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        .custom-modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border) !important;
            background: var(--bg-main) !important;
            color: var(--text-muted) !important;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.25s var(--ease-out-expo);
            font-size: 0.9rem;
        }
        .custom-modal-close:hover {
            background: var(--bg-hover) !important;
            color: var(--danger) !important;
            border-color: var(--danger) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
        }
        .custom-modal-content {
            padding: 1.5rem 1.75rem 1.75rem;
        }
        .custom-modal-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.75rem 1.25rem;
            border-top: 1px solid color-mix(in srgb, var(--text-muted) 8%, transparent);
            background: color-mix(in srgb, var(--bg-main) 30%, var(--bg-card));
        }
        .custom-modal-footer button {
            font-weight: 800;
            font-size: 0.82rem;
            border-radius: 10px;
        }

        /* Form Styles inside Modals */
        .modal-field {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            margin-bottom: 1.25rem;
        }
        .modal-field:last-child {
            margin-bottom: 0;
        }
        .modal-field label {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin: 0;
        }
        .modal-field input, .modal-field select {
            padding: 0.8rem 1rem;
            font-size: 0.88rem;
            border-radius: 12px;
            border: 1px solid var(--border) !important;
            background-color: var(--bg-main) !important;
            color: var(--text-main) !important;
            transition: all 0.25s var(--ease-out-expo);
            font-weight: 600;
        }
        .modal-field input:focus, .modal-field select:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 15%, transparent) !important;
            outline: none;
            background-color: var(--bg-card) !important;
        }
        .modal-field select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' class='bi bi-chevron-down' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 1rem center !important;
            background-size: 0.8rem !important;
            padding-right: 2.75rem !important;
        }
        html.dark .modal-field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' class='bi bi-chevron-down' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E") !important;
        }

        /* Success/Error Specific Style */
        .notif-modal-body {
            text-align: center;
            padding: 2.5rem 2rem 2rem;
        }
        .notif-icon-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.25rem;
            position: relative;
            animation: bounceCircle 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes bounceCircle {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        .notif-success-circle {
            background: color-mix(in srgb, var(--success) 12%, transparent);
            color: var(--success);
            box-shadow: 0 10px 25px -5px color-mix(in srgb, var(--success) 35%, transparent);
        }
        .notif-error-circle {
            background: color-mix(in srgb, var(--danger) 12%, transparent);
            color: var(--danger);
            box-shadow: 0 10px 25px -5px color-mix(in srgb, var(--danger) 35%, transparent);
        }
        .notif-title {
            font-size: 1.35rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
            font-family: 'Outfit', sans-serif;
            color: var(--text-main);
        }
        .notif-message {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        .notif-btn {
            padding: 0.75rem 2.25rem;
            border-radius: 50px;
            border: none;
            font-weight: 800;
            font-size: 0.88rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.25s;
        }
        .notif-success-btn {
            background: var(--success);
            color: white;
        }
        .notif-success-btn:hover {
            background: color-mix(in srgb, var(--success) 85%, black);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px color-mix(in srgb, var(--success) 35%, transparent);
        }
        .notif-error-btn {
            background: var(--danger);
            color: white;
        }
        .notif-error-btn:hover {
            background: color-mix(in srgb, var(--danger) 85%, black);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px color-mix(in srgb, var(--danger) 35%, transparent);
        }

        /* Delete Specific Modal */
        .delete-modal-body {
            text-align: center;
            padding: 2.25rem 2rem 1.75rem;
        }
        .delete-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: color-mix(in srgb, var(--danger) 10%, transparent);
            color: var(--danger);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            margin: 0 auto 1.25rem;
            box-shadow: 0 8px 20px -4px color-mix(in srgb, var(--danger) 30%, transparent);
        }

        @media (max-width: 600px) {
            .subjects-card { padding: 1.5rem !important; border-radius: 18px; }
            .btn-primary { width: 100%; padding: 1rem !important; }
            .subj-row {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 12px;
                padding: 1.25rem 1rem !important;
            }
            .subj-row > div:last-child {
                justify-content: flex-end;
                border-top: 1px dashed var(--border);
                padding-top: 10px;
                margin-top: 4px;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="max-width: 800px; padding-top: 3rem;">
        
        <div class="animate-fade-up">
            <!-- Back Navigation Header -->
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 2rem;">
                <div>
                    <h1 style="margin: 0; font-size: 1.6rem; font-weight: 900; letter-spacing: -0.03em;">Academic Subject Portal</h1>
                    <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem; font-weight: 500;">Configure subject codes, rooms, lecturers, and class schedules.</p>
                </div>
            </div>

            <!-- Subject Management Card -->
            <div class="subjects-card" id="subject-management-card">
                <div class="subjects-card-header" style="margin-bottom: 1.5rem;">
                    <i class="bi bi-book subjects-header-icon"></i>
                    <h4>Subject List & Schedules</h4>
                    <button type="button" onclick="openAddSubjectModal()" class="btn btn-primary btn-sm" style="margin-left:auto; border-radius:10px; padding:0.45rem 1.1rem; font-weight:800; font-size:0.82rem; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="bi bi-plus-lg"></i> Add Subject
                    </button>
                </div>

                <!-- Sleek Mini Stats Dashboard Grid -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 18px; padding: 1rem 0.5rem; box-shadow: var(--shadow-neu-in-sm);">
                    <div style="text-align: center; border-right: 1px solid var(--border);">
                        <span style="font-size: 1.35rem; font-weight: 850; color: var(--primary); display: block; font-family:'Outfit', sans-serif; line-height: 1.15;"><?= $totalCount ?></span>
                        <span style="font-size: 0.58rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-top: 2px;">Total Classes</span>
                    </div>
                    <div style="text-align: center; border-right: 1px solid var(--border);">
                        <span style="font-size: 1.35rem; font-weight: 850; color: #10b981; display: block; font-family:'Outfit', sans-serif; line-height: 1.15;"><?= $academicCount ?></span>
                        <span style="font-size: 0.58rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-top: 2px;">Academic</span>
                    </div>
                    <div style="text-align: center;">
                        <span style="font-size: 1.35rem; font-weight: 850; color: #f59e0b; display: block; font-family:'Outfit', sans-serif; line-height: 1.15;"><?= $eventCount ?></span>
                        <span style="font-size: 0.58rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-top: 2px;">Special Events</span>
                    </div>
                </div>

                <!-- Live Search and Dropdown Filters -->
                <div style="position: relative; margin-bottom: 1.25rem;">
                    <i class="bi bi-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.95rem; z-index: 10;"></i>
                    <input type="text" id="subjSearchInput" class="form-control" placeholder="Search subjects by name, year, or semester..." style="padding-left: 2.75rem; border-radius: 12px; height: 42px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-bottom: 0.4rem;">Category</label>
                        <select id="subjFilterCategory" class="form-control" style="border-radius: 12px; height: 40px; font-weight: 700; font-size: 0.82rem;">
                            <option value="all">All Categories</option>
                            <option value="subject">Academic Subjects</option>
                            <option value="event">Special Events</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; display: block; margin-bottom: 0.4rem;">School Year</label>
                        <select id="subjFilterSY" class="form-control" style="border-radius: 12px; height: 40px; font-weight: 700; font-size: 0.82rem;">
                            <option value="all">All School Years</option>
                            <?php
                            $syOptions = array_unique(array_filter(array_column($subjects, 'school_year')));
                            sort($syOptions);
                            foreach ($syOptions as $syOption):
                            ?>
                                <option value="<?= htmlspecialchars(strtolower($syOption)) ?>"><?= htmlspecialchars($syOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 500; border-top: 1px solid var(--border); padding-top: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 6px;">
                    <i class="bi bi-info-circle-fill" style="color: var(--primary); font-size: 0.85rem;"></i>
                    <span>Active school year subjects are highlighted with a glowing <b>Active</b> badge.</span>
                </div>

                <?php if (empty($subjects)): ?>
                    <div style="text-align:center; padding:2.5rem 1rem; color:var(--text-muted);">
                        <i class="bi bi-book" style="font-size:2.5rem; opacity:0.3;"></i>
                        <p style="margin-top:0.75rem; font-weight:700;">No subjects found. Add your first subject.</p>
                    </div>
                <?php else: ?>
                    <!-- Group by School Year -->
                    <?php
                    $grouped = [];
                    foreach ($subjects as $s) {
                        $sy = $s['school_year'] ?: 'No School Year';
                        $sem = $s['semester'] ?: 'No Semester';
                        $grouped[$sy][$sem][] = $s;
                    }
                    ?>
                    <div id="subjListContainer">
                        <?php foreach ($grouped as $sy => $semData): ?>
                            <div class="sy-group-block" data-sy="<?= htmlspecialchars($sy) ?>" style="margin-bottom:1.5rem;">
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:0.85rem;">
                                    <span style="font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted);"><?= htmlspecialchars($sy) ?></span>
                                    <?php if ($sy === $activeYear): ?>
                                        <span style="background:color-mix(in srgb,var(--primary) 12%,transparent); color:var(--primary); font-size:0.65rem; font-weight:800; padding:2px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:0.05em; box-shadow: 0 0 10px color-mix(in srgb, var(--primary) 20%, transparent);">Active SY</span>
                                    <?php else: ?>
                                        <span style="background:color-mix(in srgb,var(--text-muted) 8%,transparent); color:var(--text-muted); font-size:0.65rem; font-weight:800; padding:2px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:0.05em;">Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <?php foreach ($semData as $sem => $subs): ?>
                                    <div class="sem-group-block" data-sem="<?= htmlspecialchars($sem) ?>" style="margin-bottom:1rem; background:var(--bg-main); border:1px solid var(--border); border-radius:16px; overflow:hidden;">
                                        <div style="padding:0.6rem 1rem; border-bottom:1px solid var(--border); background:color-mix(in srgb,var(--text-muted) 5%,transparent);">
                                            <span style="font-size:0.72rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.06em;"><?= htmlspecialchars($sem) ?></span>
                                        </div>
                                        <?php foreach ($subs as $sub): ?>
                                            <div class="subj-row" id="subj-row-<?= $sub['id'] ?>" 
                                                 data-id="<?= $sub['id'] ?>"
                                                 data-name="<?= htmlspecialchars($sub['name']) ?>" 
                                                 data-semester="<?= htmlspecialchars($sub['semester']) ?>"
                                                 data-sy="<?= htmlspecialchars($sy) ?>"
                                                 data-category="<?= htmlspecialchars($sub['category'] ?? 'subject') ?>" 
                                                 data-code="<?= htmlspecialchars($sub['code'] ?? '') ?>"
                                                 data-room="<?= htmlspecialchars($sub['room'] ?? '') ?>"
                                                 data-lecturer="<?= htmlspecialchars($sub['lecturer'] ?? '') ?>"
                                                 style="display:flex; justify-content:space-between; align-items:center; padding:0.95rem 1rem; border-bottom:1px solid color-mix(in srgb,var(--border) 50%,transparent); transition:background 0.2s;">
                                                <div style="flex:1; min-width:0;">
                                                    <p style="margin:0; font-weight:700; font-size:0.92rem; color:var(--text-main); display:flex; align-items:center; flex-wrap:wrap; gap:6px; line-height:1.25;">
                                                        <?= htmlspecialchars($sub['name']) ?>
                                                        <?php if (!empty($sub['code'])): ?>
                                                            <span style="font-family:'JetBrains Mono', monospace; font-size:0.68rem; background:color-mix(in srgb, var(--primary) 10%, transparent); color:var(--primary); padding:2px 6px; border-radius:6px; font-weight:700;"><?= htmlspecialchars($sub['code']) ?></span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <div style="display:flex; gap:12px; align-items:center; margin-top:4px; flex-wrap:wrap;">
                                                        <span style="font-size:0.68rem; color:var(--text-muted); font-weight:600; display:inline-flex; align-items:center; gap:2px;"><i class="bi bi-tag" style="font-size:0.75rem;"></i><?= ucfirst($sub['category'] ?? 'subject') ?></span>
                                                        <?php if (!empty($sub['room'])): ?>
                                                            <span style="font-size:0.68rem; color:var(--text-muted); font-weight:600; display:inline-flex; align-items:center; gap:2px;"><i class="bi bi-geo-alt" style="font-size:0.75rem;"></i><?= htmlspecialchars($sub['room']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($sub['lecturer'])): ?>
                                                            <span style="font-size:0.68rem; color:var(--text-muted); font-weight:600; display:inline-flex; align-items:center; gap:2px;"><i class="bi bi-person" style="font-size:0.75rem;"></i><?= htmlspecialchars($sub['lecturer']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (!empty($sub['schedule_list'])): ?>
                                                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
                                                            <?php foreach (explode('||', $sub['schedule_list']) as $sched): ?>
                                                                <span style="font-family:'JetBrains Mono', monospace; font-size:0.65rem; background:color-mix(in srgb, var(--primary) 6%, transparent); color:var(--text-main); border:1px solid color-mix(in srgb, var(--primary) 12%, transparent); padding:2px 8px; border-radius:30px; font-weight:750; display:inline-flex; align-items:center; gap:4px;">
                                                                    <i class="bi bi-clock" style="font-size:0.7rem; color:var(--primary);"></i>
                                                                    <?= htmlspecialchars($sched) ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="display:flex; gap:8px; flex-shrink:0;">
                                                    <a href="view_subject_attendance.php?id=<?= $sub['id'] ?>" class="subj-action-btn" title="View Attendance Reports" style="color: var(--primary); border-color: color-mix(in srgb, var(--primary) 30%, transparent); text-decoration: none;">
                                                        <i class="bi bi-bar-chart-line"></i>
                                                    </a>
                                                    <a href="manual.php?subject_id=<?= $sub['id'] ?>" class="subj-action-btn" title="Manual Clock Entry" style="color: #10b981; border-color: color-mix(in srgb, #10b981 30%, transparent); text-decoration: none;">
                                                        <i class="bi bi-person-check"></i>
                                                    </a>
                                                    <button type="button" onclick="editSubject(<?= $sub['id'] ?>)" class="subj-action-btn" title="Edit Subject">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" onclick="deleteSubject(<?= $sub['id'] ?>, '<?= addslashes($sub['name']) ?>')" class="subj-action-btn subj-delete-btn" title="Delete Subject">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Empty State Fallback -->
                <div id="subjEmptyState" style="display:none; text-align:center; padding:3rem 1rem; color:var(--text-muted);">
                    <i class="bi bi-journal-x" style="font-size:2.5rem; opacity:0.3;"></i>
                    <p style="margin-top:0.75rem; font-weight:700; font-family:'Outfit', sans-serif;">No subjects match your filters.</p>
                </div>
            </div>
        </div>

    </main>

    <!-- Premium Custom Modal Overlays -->
    <!-- 1. Subject Management Modal -->
    <div id="subjectModal" class="custom-modal-overlay" onclick="if(event.target == this) closeSubjectModal()">
        <div class="custom-modal-body">
            <div class="custom-modal-header">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="bi bi-book" id="subjModalIcon"></i>
                    </div>
                    <div class="header-text">
                        <h3 id="subjModalTitle">Add New Subject</h3>
                        <small id="subjModalSubtitle">Specify subject configuration details</small>
                    </div>
                </div>
                <button type="button" onclick="closeSubjectModal()" class="custom-modal-close" title="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <form id="subjForm" onsubmit="submitSubjectForm(event)">
                <input type="hidden" id="subjId" value="">
                <div class="custom-modal-content" style="max-height: calc(75vh - 120px); overflow-y: auto; padding: 1.5rem 1.75rem;">
                    <div class="modal-field">
                        <label for="subjName">Subject Name *</label>
                        <input type="text" id="subjName" placeholder="e.g. Web Development" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="modal-field" style="margin-bottom: 0;">
                            <label for="subjCode">Subject Code</label>
                            <input type="text" id="subjCode" placeholder="e.g. IT-312">
                        </div>
                        <div class="modal-field" style="margin-bottom: 0;">
                            <label for="subjRoom">Room</label>
                            <input type="text" id="subjRoom" placeholder="e.g. CL-2">
                        </div>
                    </div>

                    <div class="modal-field">
                        <label for="subjLecturer">Lecturer</label>
                        <input type="text" id="subjLecturer" placeholder="e.g. Dr. John Doe">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="modal-field" style="margin-bottom: 0;">
                            <label for="subjSem">Semester *</label>
                            <input type="text" id="subjSem" placeholder="e.g. 1st Semester" required>
                        </div>
                        <div class="modal-field" style="margin-bottom: 0;">
                            <label for="subjSY">School Year *</label>
                            <input type="text" id="subjSY" placeholder="e.g. SY 2024-2025" required>
                        </div>
                    </div>

                    <div class="modal-field">
                        <label for="subjCategory">Category</label>
                        <select id="subjCategory">
                            <option value="subject">Academic Subject</option>
                            <option value="event">Special Event</option>
                        </select>
                    </div>

                    <!-- Interactive Schedules Manager (Edit Mode Only) -->
                    <div id="scheduleSection" style="margin-top: 1.5rem; border-top: 1px dashed var(--border); padding-top: 1.25rem; display: none;">
                        <label style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); display: block; margin-bottom: 0.6rem;">Class Schedule</label>
                        
                        <!-- Render active schedules list -->
                        <div id="modalSchedulesList" style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 1rem;">
                            <!-- Filled via JS -->
                        </div>
                        
                        <!-- Inline add schedule controls -->
                        <div style="background: var(--bg-main); border: 1px solid var(--border); border-radius: 12px; padding: 0.75rem; display: flex; flex-direction: column; gap: 8px;">
                            <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Add New Class Schedule</div>
                            <div style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 6px; align-items: center;">
                                <select id="schedDay" class="form-control" style="height: 34px; padding: 0.35rem 0.5rem; font-size: 0.78rem; border-radius: 8px; font-weight:600; background: var(--bg-card);">
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                                <input type="time" id="schedStart" class="form-control" style="height: 34px; padding: 0.35rem 0.5rem; font-size: 0.78rem; border-radius: 8px; font-weight:600; value="08:00">
                                <input type="time" id="schedEnd" class="form-control" style="height: 34px; padding: 0.35rem 0.5rem; font-size: 0.78rem; border-radius: 8px; font-weight:600; value="10:00">
                            </div>
                            <button type="button" onclick="addModalSchedule()" class="btn btn-primary btn-sm" style="align-self: flex-end; padding: 0.35rem 1rem; border: none; border-radius: 8px; font-size: 0.75rem;">
                                <i class="bi bi-plus-lg"></i> Add Day & Time
                            </button>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" onclick="closeSubjectModal()" class="btn btn-ghost" style="border-radius: 10px; padding: 0.6rem 1.25rem;">Discard</button>
                    <button type="submit" class="btn btn-primary" id="subjModalSubmitBtn" style="border-radius: 10px; padding: 0.6rem 1.5rem; border: none;">Add Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="custom-modal-overlay" onclick="if(event.target == this) closeDeleteModal()">
        <div class="custom-modal-body" style="max-width: 400px;">
            <div class="delete-modal-body">
                <div class="delete-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h3 style="font-weight: 800; font-size: 1.25rem; letter-spacing: -0.02em; margin-bottom: 0.5rem; color: var(--text-main);">Delete Subject?</h3>
                <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 1.5rem; font-weight: 500;">
                    Are you sure you want to permanently delete <b id="deleteSubjName" style="color:var(--text-main);">Subject</b>?<br>
                    <span style="color: var(--danger); font-weight: 600;">This action cannot be undone and will erase all associated attendance logs.</span>
                </p>
                <input type="hidden" id="deleteSubjId" value="">
                <div style="display: flex; gap: 0.75rem;">
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-ghost" style="flex: 1; border-radius: 12px; padding: 0.75rem; justify-content: center;">Cancel</button>
                    <button type="button" onclick="confirmDeleteSubject()" class="btn" style="flex: 1; background: var(--danger); color: white; border: none; border-radius: 12px; padding: 0.75rem; justify-content: center; font-weight: 700;">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Notification Modal (Success / Error) -->
    <div id="notificationModal" class="custom-modal-overlay" onclick="if(event.target == this) closeNotifModal()">
        <div class="custom-modal-body" style="max-width: 400px;">
            <div class="notif-modal-body">
                <div id="notifIconCircle" class="notif-icon-circle">
                    <i id="notifIcon" class="bi"></i>
                </div>
                <h3 id="notifTitle" class="notif-title">Notification</h3>
                <p id="notifMessage" class="notif-message">Message details go here.</p>
                <button type="button" id="notifCloseBtn" onclick="closeNotifModal()" class="notif-btn">OK</button>
            </div>
        </div>
    </div>

    <script>
        const Toast = Swal.mixin({
            toast: true, position: 'bottom-end', showConfirmButton: false,
            timer: 2500, timerProgressBar: true
        });

        // Synthesize Premium Sound FX using Web Audio API
        let modalAudioCtx = null;
        function playModalSound(type) {
            try {
                if (!modalAudioCtx) {
                    modalAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (modalAudioCtx.state === 'suspended') {
                    modalAudioCtx.resume();
                }
                const now = modalAudioCtx.currentTime;
                
                if (type === 'success') {
                    const notes = [329.63, 392.00, 523.25, 659.25]; // E4, G4, C5, E5 arpeggio
                    notes.forEach((freq, idx) => {
                        const osc = modalAudioCtx.createOscillator();
                        const gain = modalAudioCtx.createGain();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, now + idx * 0.06);
                        
                        gain.gain.setValueAtTime(0, now + idx * 0.06);
                        gain.gain.linearRampToValueAtTime(0.04, now + idx * 0.06 + 0.03);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.06 + 0.25);
                        
                        osc.connect(gain);
                        gain.connect(modalAudioCtx.destination);
                        
                        osc.start(now + idx * 0.06);
                        osc.stop(now + idx * 0.06 + 0.3);
                    });
                } else if (type === 'error') {
                    const osc = modalAudioCtx.createOscillator();
                    const gain = modalAudioCtx.createGain();
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(150, now);
                    osc.frequency.linearRampToValueAtTime(80, now + 0.25);
                    
                    gain.gain.setValueAtTime(0.08, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
                    
                    osc.connect(gain);
                    gain.connect(modalAudioCtx.destination);
                    
                    osc.start(now);
                    osc.stop(now + 0.3);
                } else if (type === 'click') {
                    const osc = modalAudioCtx.createOscillator();
                    const gain = modalAudioCtx.createGain();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(800, now);
                    
                    gain.gain.setValueAtTime(0.02, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.015);
                    
                    osc.connect(gain);
                    gain.connect(modalAudioCtx.destination);
                    
                    osc.start(now);
                    osc.stop(now + 0.02);
                }
            } catch (e) {
                console.warn("Audio Context error:", e);
            }
        }

        // Custom Notification Modal Logic
        let shouldReloadOnNotifClose = false;

        function showNotifModal(type, title, message, reloadOnClose = false) {
            shouldReloadOnNotifClose = reloadOnClose;
            
            const notifModal = document.getElementById('notificationModal');
            const circle = document.getElementById('notifIconCircle');
            const icon = document.getElementById('notifIcon');
            const titleEl = document.getElementById('notifTitle');
            const msgEl = document.getElementById('notifMessage');
            const btn = document.getElementById('notifCloseBtn');

            titleEl.innerText = title;
            msgEl.innerText = message;

            // Reset classes
            circle.className = 'notif-icon-circle';
            btn.className = 'notif-btn';

            if (type === 'success') {
                circle.classList.add('notif-success-circle');
                icon.className = 'bi bi-check-lg';
                btn.classList.add('notif-success-btn');
                playModalSound('success');
            } else {
                circle.classList.add('notif-error-circle');
                icon.className = 'bi bi-exclamation-lg';
                btn.classList.add('notif-error-btn');
                playModalSound('error');
            }

            notifModal.classList.add('active');
        }

        function closeNotifModal() {
            playModalSound('click');
            const notifModal = document.getElementById('notificationModal');
            notifModal.classList.remove('active');
            
            if (shouldReloadOnNotifClose) {
                setTimeout(() => location.reload(), 150);
            }
        }

        // Subject Modal Actions (Add / Edit)
        function openAddSubjectModal() {
            document.getElementById('subjId').value = '';
            document.getElementById('subjName').value = '';
            document.getElementById('subjCode').value = '';
            document.getElementById('subjRoom').value = '';
            document.getElementById('subjLecturer').value = '';
            document.getElementById('subjSY').value = '<?= htmlspecialchars($activeYear) ?>';
            document.getElementById('subjSem').value = '1st Semester';
            document.getElementById('subjCategory').value = 'subject';

            document.getElementById('subjModalTitle').innerText = 'Add New Subject';
            document.getElementById('subjModalSubtitle').innerText = 'Specify subject configuration details';
            document.getElementById('subjModalSubmitBtn').innerHTML = '<i class="bi bi-plus-lg"></i> Add Subject';
            document.getElementById('subjModalIcon').className = 'bi bi-book';

            // Hide schedules manager in Add mode
            document.getElementById('scheduleSection').style.display = 'none';

            playModalSound('click');
            document.getElementById('subjectModal').classList.add('active');
        }

        function editSubject(id) {
            const row = document.getElementById('subj-row-' + id);
            const name = row.getAttribute('data-name');
            const semester = row.getAttribute('data-semester');
            const sy = row.getAttribute('data-sy');
            const category = row.getAttribute('data-category');
            const code = row.getAttribute('data-code');
            const room = row.getAttribute('data-room');
            const lecturer = row.getAttribute('data-lecturer');

            document.getElementById('subjId').value = id;
            document.getElementById('subjName').value = name;
            document.getElementById('subjCode').value = code;
            document.getElementById('subjRoom').value = room;
            document.getElementById('subjLecturer').value = lecturer;
            document.getElementById('subjSY').value = sy;
            document.getElementById('subjSem').value = semester;
            document.getElementById('subjCategory').value = category;

            document.getElementById('subjModalTitle').innerText = 'Edit Subject';
            document.getElementById('subjModalSubtitle').innerText = 'Update subject configuration details';
            document.getElementById('subjModalSubmitBtn').innerHTML = '<i class="bi bi-save"></i> Save Changes';
            document.getElementById('subjModalIcon').className = 'bi bi-pencil-square';

            // Show schedules manager & refresh schedules list
            document.getElementById('scheduleSection').style.display = 'block';
            refreshModalSchedules(id);

            playModalSound('click');
            document.getElementById('subjectModal').classList.add('active');
        }

        function closeSubjectModal() {
            playModalSound('click');
            document.getElementById('subjectModal').classList.remove('active');
        }

        // Inline Class Schedules Managers
        function refreshModalSchedules(subjectId) {
            const list = document.getElementById('modalSchedulesList');
            list.innerHTML = '<div style="font-size:0.75rem; color:var(--text-muted); text-align:center; padding:0.5rem;"><span class="spinner-border spinner-border-sm" role="status"></span> Loading schedules...</div>';
            
            fetch('api/subject_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_schedules', subject_id: subjectId })
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    renderModalSchedules(res.data);
                } else {
                    list.innerHTML = '<div style="font-size:0.75rem; color:var(--danger); text-align:center; padding:0.5rem;">Failed to load schedules.</div>';
                }
            })
            .catch(() => {
                list.innerHTML = '<div style="font-size:0.75rem; color:var(--danger); text-align:center; padding:0.5rem;">Error loading schedules.</div>';
            });
        }

        function renderModalSchedules(schedules) {
            const list = document.getElementById('modalSchedulesList');
            list.innerHTML = '';
            
            if (schedules.length === 0) {
                list.innerHTML = '<div style="font-size:0.75rem; color:var(--text-muted); text-align:center; padding:0.5rem; font-style:italic;">No schedules defined for this subject.</div>';
                return;
            }
            
            schedules.forEach(s => {
                const item = document.createElement('div');
                item.style = 'display:flex; justify-content:space-between; align-items:center; background:var(--bg-main); border:1px solid var(--border); border-radius:10px; padding:0.5rem 0.75rem;';
                
                const timeStr = `${s.day_of_week} • ${s.start_time} - ${s.end_time}`;
                
                item.innerHTML = `
                    <span style="font-size:0.78rem; font-weight:700; color:var(--text-main);">${timeStr}</span>
                    <button type="button" onclick="deleteModalSchedule(${s.id})" class="subj-action-btn subj-delete-btn" style="width:26px; height:26px; border-radius:6px; font-size:0.75rem;" title="Remove Schedule">
                        <i class="bi bi-trash"></i>
                    </button>
                `;
                list.appendChild(item);
            });
        }

        function addModalSchedule() {
            const subjectId = document.getElementById('subjId').value;
            const day = document.getElementById('schedDay').value;
            const start = document.getElementById('schedStart').value;
            const end = document.getElementById('schedEnd').value;
            
            if (!subjectId) return;
            
            playModalSound('click');
            
            fetch('api/subject_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'add_schedule',
                    subject_id: subjectId,
                    day: day,
                    start: start,
                    end: end
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Toast.fire({ icon: 'success', title: 'Schedule added successfully.' });
                    refreshModalSchedules(subjectId);
                } else {
                    showNotifModal('error', 'Schedule Error', res.message);
                }
            })
            .catch(() => {
                showNotifModal('error', 'System Error', 'Could not add schedule.');
            });
        }

        function deleteModalSchedule(scheduleId) {
            const subjectId = document.getElementById('subjId').value;
            if (!subjectId) return;
            
            playModalSound('click');
            
            fetch('api/subject_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete_schedule',
                    id: scheduleId
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    Toast.fire({ icon: 'success', title: 'Schedule removed.' });
                    refreshModalSchedules(subjectId);
                } else {
                    showNotifModal('error', 'Schedule Error', res.message);
                }
            })
            .catch(() => {
                showNotifModal('error', 'System Error', 'Could not remove schedule.');
            });
        }

        function submitSubjectForm(event) {
            event.preventDefault();
            
            const id = document.getElementById('subjId').value;
            const name = document.getElementById('subjName').value.trim();
            const code = document.getElementById('subjCode').value.trim();
            const room = document.getElementById('subjRoom').value.trim();
            const lecturer = document.getElementById('subjLecturer').value.trim();
            const sy = document.getElementById('subjSY').value.trim();
            const sem = document.getElementById('subjSem').value.trim();
            const cat = document.getElementById('subjCategory').value;

            if (!name || !sy || !sem) {
                showNotifModal('error', 'Validation Error', 'Please fill in all required fields.');
                return;
            }

            const isEdit = id !== '';
            const action = isEdit ? 'update_subject' : 'add_subject';
            
            const params = {
                action: action,
                name: name,
                semester: sem,
                school_year: sy,
                category: cat,
                code: code,
                room: room,
                lecturer: lecturer
            };

            if (isEdit) {
                params.id = id;
                params.is_active = 1;
            }

            // Close form modal first
            document.getElementById('subjectModal').classList.remove('active');

            fetch('api/subject_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params)
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    showNotifModal('success', isEdit ? 'Subject Updated' : 'Subject Added', res.message, true);
                } else {
                    showNotifModal('error', 'Operation Failed', res.message);
                }
            })
            .catch(() => {
                showNotifModal('error', 'System Error', 'Could not process subject request. Please try again.');
            });
        }

        // Delete Modal Actions
        function deleteSubject(id, name) {
            document.getElementById('deleteSubjId').value = id;
            document.getElementById('deleteSubjName').innerText = name;

            playModalSound('click');
            document.getElementById('deleteConfirmModal').classList.add('active');
        }

        function closeDeleteModal() {
            playModalSound('click');
            document.getElementById('deleteConfirmModal').classList.remove('active');
        }

        function confirmDeleteSubject() {
            const id = document.getElementById('deleteSubjId').value;
            
            // Close delete modal first
            document.getElementById('deleteConfirmModal').classList.remove('active');

            const row = document.getElementById('subj-row-' + id);
            if (row) row.style.opacity = '0.4';

            fetch('api/subject_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'delete_subject', id: id })
            })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    showNotifModal('success', 'Subject Deleted', 'The subject has been permanently removed.', true);
                } else {
                    if (row) row.style.opacity = '1';
                    showNotifModal('error', 'Delete Failed', res.message);
                }
            })
            .catch(() => {
                if (row) row.style.opacity = '1';
                showNotifModal('error', 'System Error', 'Could not process delete request. Please try again.');
            });
        }

        // Live Search & Client-Side Filtering
        function filterSubjects() {
            const query = document.getElementById('subjSearchInput').value.toLowerCase().trim();
            const catFilter = document.getElementById('subjFilterCategory').value;
            const syFilter = document.getElementById('subjFilterSY').value;

            const syBlocks = document.querySelectorAll('.sy-group-block');
            let totalVisible = 0;

            syBlocks.forEach(syBlock => {
                const syVal = syBlock.getAttribute('data-sy').toLowerCase();
                const semBlocks = syBlock.querySelectorAll('.sem-group-block');
                let syBlockVisibleCount = 0;

                semBlocks.forEach(semBlock => {
                    const semVal = semBlock.getAttribute('data-sem').toLowerCase();
                    const rows = semBlock.querySelectorAll('.subj-row');
                    let semBlockVisibleCount = 0;

                    rows.forEach(row => {
                        const name = row.getAttribute('data-name').toLowerCase();
                        const cat = row.getAttribute('data-category');
                        
                        const matchesQuery = !query || name.includes(query) || syVal.includes(query) || semVal.includes(query);
                        const matchesCat = catFilter === 'all' || cat === catFilter;
                        const matchesSY = syFilter === 'all' || syVal === syFilter;

                        if (matchesQuery && matchesCat && matchesSY) {
                            row.style.display = 'flex';
                            semBlockVisibleCount++;
                            syBlockVisibleCount++;
                            totalVisible++;
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    if (semBlockVisibleCount > 0) {
                        semBlock.style.display = 'block';
                    } else {
                        semBlock.style.display = 'none';
                    }
                });

                if (syBlockVisibleCount > 0) {
                    syBlock.style.display = 'block';
                } else {
                    syBlock.style.display = 'none';
                }
            });

            const emptyState = document.getElementById('subjEmptyState');
            if (totalVisible === 0) {
                if (emptyState) emptyState.style.display = 'block';
            } else {
                if (emptyState) emptyState.style.display = 'none';
            }
        }

        // Initialize Filter Listeners
        document.addEventListener('DOMContentLoaded', () => {
            const search = document.getElementById('subjSearchInput');
            const cat = document.getElementById('subjFilterCategory');
            const sy = document.getElementById('subjFilterSY');
            
            if (search) search.addEventListener('input', filterSubjects);
            if (cat) cat.addEventListener('change', filterSubjects);
            if (sy) sy.addEventListener('change', filterSubjects);
        });
    </script>

</body>
</html>
