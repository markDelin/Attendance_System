<?php
// schedule.php - Subject Schedule Maker
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

// Fetch Metadata
$settingsQuery = $pdo->query("SELECT * FROM schedule_settings");
$meta = [];
while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
    $meta[$row['key']] = $row['value'];
}

// Defaults
$semester1 = $meta['semester1'] ?? '1st Sem';
$semester2 = $meta['semester2'] ?? '2nd Sem';
$activeSem = $meta['active_sem'] ?? '2'; // 1 or 2
$year = $meta['year'] ?? 'YEAR 2025-2026';
$sectionCourse = $meta['section_course'] ?? 'BS Information System';
$sectionName = $meta['section_name'] ?? 'BSIS-2A';

// Fetch Subjects & Schedule
$stmt = $pdo->query("SELECT s.*, sch.day_of_week, sch.start_time, sch.end_time 
                     FROM subjects s 
                     JOIN schedules sch ON s.id = sch.subject_id 
                     ORDER BY sch.day_of_week, sch.start_time");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize data
$grouped = [];
foreach ($data as $row) {
    // Unique key per subject+time slot to merge days
    $key = $row['name'] . '_' . $row['start_time'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'room' => $row['room'],
            'lecturer' => $row['lecturer'],
            // Use AM/PM to avoid ambiguity when saving back to DB (1:00 -> 1 AM vs 1 PM)
            'time' => date('g:i A', strtotime($row['start_time'])) . ' - ' . date('g:i A', strtotime($row['end_time'])),
            'raw_start' => $row['start_time'], // For sorting
            'days' => []
        ];
    }
    $grouped[$key]['days'][] = $row['day_of_week'];
}

$monTue = [];
$sat = [];
$other = [];

foreach ($grouped as $g) {
    sort($g['days']);
    $dayStr = implode(' & ', $g['days']);
    $g['dayStr'] = $dayStr;
    
    // Sort logic
    if (strpos($dayStr, 'Monday') !== false || strpos($dayStr, 'Tuesday') !== false) {
        $monTue[] = $g;
    } elseif (strpos($dayStr, 'Saturday') !== false) {
        $sat[] = $g;
    } else {
        $other[] = $g; // Just in case
    }
}

// Sort by time using raw_start (24h format)
$sortByTime = function($a, $b) {
    return strcmp($a['raw_start'], $b['raw_start']);
};
usort($monTue, $sortByTime);
usort($sat, $sortByTime);
usort($other, $sortByTime);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Schedule</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Ensure Swal is here -->
    <?php include 'includes/theme_loader.php'; ?>
    <link rel="stylesheet" href="assets/css/AnimatedList.css">
    <script src="assets/js/AnimatedList.js"></script>
    <style>
        /* Refined Controls */
        .controls {
            margin: 2rem auto;
            max-width: 900px;
            display: flex; flex-direction: column; gap: 1.5rem;
            background: var(--bg-card);
            padding: 1.5rem; 
            border-radius: 24px; 
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .theme-group-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: block;
        }

        .time-slot {
            font-size: 0.75rem; color: var(--text-muted); text-align: right; padding-right: 10px;
            font-weight: 600; vertical-align: middle; width: 60px;
        }
        .day-header {
            text-align: center; font-weight: 700; color: var(--text-main);
            padding: 10px; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border);
        }
        .schedule-cell {
            border: 1px solid var(--border);
            height: 50px;
            vertical-align: top;
            padding: 5px;
            transition: background 0.2s;
        }
        .schedule-cell:hover { background: var(--bg-hover); }
        
        .class-block {
            padding: 4px 8px; border-radius: 4px;
            font-size: 0.75rem; margin-bottom: 2px;
            cursor: pointer;
            box-shadow: none; border: 1px solid var(--border);
        }
        .theme-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
        }

        .btn-theme {
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center;
            gap: 8px;
            width: 100px;
            height: 90px;
            border: 2px solid var(--border);
            background: var(--bg-card);
            color: var(--text-muted);
            border-radius: 16px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .btn-theme:hover {
            transform: translateY(-4px);
            border-color: #cbd5e1;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .btn-theme.active {
            background: var(--bg-main);
            color: var(--text-main);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.1);
        }
        
        /* Theme Preview Circle */
        .theme-preview-color { 
            width: 32px; 
            height: 32px; 
            background: var(--bg-card); border-radius: 20px; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.2); 
            padding: 4rem; position: relative; width: 100%; max-width: 100%;
        }

        /* Adjust Mobile grid */
        @media (max-width: 768px) {
            .btn-theme {
                width: 80px;
                height: 70px;
                font-size: 0.75rem;
            }
            .theme-preview-color {
                width: 24px; height: 24px;
            }
        }

        .template-preview {
            width: 100% !important; 
            margin: 0 auto; 
            background: var(--bg-card); 
            padding: 3rem; 
            min-height: 900px;
            /* Realistic Paper Shadow */
            box-shadow: 
                0 1px 1px rgba(0,0,0,0.05), 
                0 2px 2px rgba(0,0,0,0.05), 
                0 4px 4px rgba(0,0,0,0.05), 
                0 8px 8px rgba(0,0,0,0.05),
                0 16px 16px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        /* Editing Styles */
        .edit-overlay { 
            display: none; position: absolute; top:0; left:0; right:0; bottom:0; 
            background: var(--glass-bg); cursor: pointer; 
            align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.2s;
            backdrop-filter: blur(2px);
            z-index: 10;
        }
        .edit-mode .row-container:hover .edit-overlay { display: flex; opacity: 1; }
        .edit-overlay i { background: var(--bg-card); padding: 8px; border-radius: 50%; box-shadow: 0 2px 8px rgba(0,0,0,0.1); color: var(--primary); }

        .row-container { position: relative; }

        /* Edit Button Styling */
        .btn-edit-header { display: none; margin-top: 10px; font-size: 0.85rem; width: 100%; border-radius: 8px; justify-content: center; }
        .edit-mode .btn-edit-header { display: inline-flex; }

        /* --- THEMES (Existing + New) --- */
        /* Classic */
        .theme-classic .schedule-container { font-family: 'Arial', sans-serif; color: black; border: 2px solid black; border-radius: 0; padding: 20px; background: white; }
        .theme-classic table { width: 100%; border-collapse: collapse; border: 2px solid black; font-size: 14px; }
        .theme-classic th, .theme-classic td { border: 2px solid black; padding: 8px; text-align: center; }
        .theme-classic th { font-weight: bold; padding: 15px 5px; background: transparent; }
        /* ... rest of themes ... */ 

        .theme-classic .vertical-text { 
            writing-mode: vertical-rl; 
            transform: rotate(180deg); 
            font-weight: bold; 
            font-size: 1.1rem; 
            padding: 5px; 
            white-space: nowrap;
            display: inline-block;
        }
        
        .theme-classic .header-title { font-family: 'Georgia', serif; font-style: italic; font-size: 2.5rem; font-weight: bold; text-align: center; margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .theme-classic .sub-header { text-align: center; font-weight: bold; border-top: 2px solid black; padding: 5px; }
        .theme-classic .footer-info { text-align: center; font-weight: bold; margin-top: 20px; font-size: 1.5rem; border: 2px solid black; padding: 10px; }

        /* Modern */
        .theme-modern .schedule-container { font-family: 'Inter', system-ui, sans-serif; background: #ffffff; border-radius: 16px; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #f1f5f9; }
        .theme-modern .header-title { font-size: 2.2rem; font-weight: 800; background: linear-gradient(to right, #1e293b, #334155); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-align: left; margin-bottom: 2rem; letter-spacing: -0.025em; padding-bottom: 20px; border-bottom: 2px solid #f1f5f9; }
        .theme-modern table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .theme-modern th { background: #f8fafc; color: #475569; font-weight: 600; padding: 16px; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .theme-modern td { padding: 20px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.95rem; vertical-align: middle; }
        .theme-modern .vertical-text { background: #3b82f6; color: white; font-weight: 600; writing-mode: vertical-rl; transform: rotate(180deg); padding: 12px 8px; text-align: center; border-radius: 8px; font-size: 0.85rem; letter-spacing: 1px; width: 100%; box-sizing: border-box;}
        .theme-modern .footer-info { text-align: left; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; color: #64748b; font-size: 0.9rem; }

        /* Minimal */
        .theme-minimal .schedule-container { font-family: 'Inter', sans-serif; background: #ffffff; padding: 40px; color: #0f172a; }
        .theme-minimal .header-title { font-size: 1.8rem; font-weight: 600; text-align: center; letter-spacing: -0.5px; margin-bottom: 50px; }
        .theme-minimal table { width: 100%; border-collapse: collapse; }
        .theme-minimal th { text-align: left; font-weight: 500; color: #94a3b8; font-size: 0.85rem; padding-bottom: 24px; border-bottom: 1px solid #f1f5f9; }
        .theme-minimal td { padding: 24px 0; border-bottom: 1px solid #f8fafc; vertical-align: top; color: #1e293b; }
        .theme-minimal .vertical-text { writing-mode: vertical-rl; transform: rotate(180deg); text-align: center; color: #94a3b8; font-size: 0.85rem; font-weight: 500; letter-spacing: 2px; text-transform: uppercase; }
        .theme-minimal .footer-info { text-align: center; color: #cbd5e1; font-size: 0.85rem; margin-top: 40px; }

        /* Corporate */
        .theme-corporate .schedule-container { font-family: 'Segoe UI', system-ui, sans-serif; background: #fff; padding: 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .theme-corporate .header-title { background: #1e293b; color: white; padding: 40px; text-align: center; font-size: 2rem; font-weight: 300; letter-spacing: 1px; margin-bottom: 0; }
        .theme-corporate table { width: 100%; border-collapse: collapse; }
        .theme-corporate th { background: #334155; color: #f8fafc; padding: 16px; font-weight: 500; font-size: 0.9rem; text-align: left; border-right: 1px solid #475569; }
        .theme-corporate td { padding: 16px; border-bottom: 1px solid #e2e8f0; color: #334155; }
        .theme-corporate tr:nth-child(even) { background-color: #f8fafc; }
        .theme-corporate .vertical-text { writing-mode: vertical-rl; transform: rotate(180deg); text-align: center; color: white; background: #475569; padding: 0; height: 100%; width: 100%; display: flex; align-items: center; justify-content: center; font-weight: 500; letter-spacing: 1px; }
        .theme-corporate .footer-info { text-align: center; background: #f1f5f9; color: #475569; padding: 20px; font-weight: 600; font-size: 0.9rem; border-top: 1px solid #e2e8f0; }

        /* Pastel */
        .theme-pastel .schedule-container { font-family: 'Quicksand', 'Nunito', sans-serif; background: #fff5f7; padding: 35px; border-radius: 30px; border: 8px solid white; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); }
        .theme-pastel .header-title { color: #db2777; font-size: 2.5rem; text-align: center; font-weight: 800; margin-bottom: 30px; letter-spacing: -1px; }
        .theme-pastel table { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
        .theme-pastel th { color: #be185d; background: transparent; padding: 15px; font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; opacity: 0.7; }
        .theme-pastel td { background: white; padding: 20px; border-radius: 16px; color: #831843; font-weight: 600; box-shadow: 0 4px 6px -1px rgba(253, 164, 175, 0.1); border: 2px solid transparent; transition: all 0.2s; }
        .theme-pastel tr:hover td { border-color: #fbcfe8; transform: translateY(-2px); }
        .theme-pastel .vertical-text { writing-mode: vertical-rl; transform: rotate(180deg); text-align: center; color: #db2777; font-weight: 800; font-size: 1.1rem; letter-spacing: 2px; }
        .theme-pastel .footer-info { text-align: center; color: #f472b6; font-weight: 700; margin-top: 30px; font-size: 1rem; opacity: 0.8; }

        /* Dark */
        .theme-dark .schedule-container { font-family: 'Inter', sans-serif; background: #0f172a; padding: 40px; color: #e2e8f0; border: 1px solid #1e293b; border-radius: 12px; }
        .theme-dark .header-title { color: #38bdf8; font-size: 2rem; font-weight: 700; text-align: center; letter-spacing: -0.5px; border-bottom: 1px solid #1e293b; padding-bottom: 25px; margin-bottom: 35px; }
        .theme-dark table { width: 100%; border-collapse: collapse; }
        .theme-dark th { border-bottom: 1px solid #1e293b; color: #94a3b8; padding: 20px; text-align: left; font-weight: 500; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; }
        .theme-dark td { border-bottom: 1px solid #1e293b; padding: 20px; color: #f1f5f9; }
        .theme-dark .vertical-text { writing-mode: vertical-rl; transform: rotate(180deg); text-align: center; color: #38bdf8; font-weight: 600; letter-spacing: 1px; }
        .theme-dark .footer-info { text-align: center; color: #475569; margin-top: 40px; font-size: 0.85rem; }

        /* Lavender */
        .theme-lavender .schedule-container { font-family: 'Outfit', sans-serif; background: linear-gradient(180deg, #fdf4ff 0%, #fae8ff 100%); padding: 40px; border-radius: 24px; border: 1px solid #e9d5ff; }
        .theme-lavender .header-title { color: #9333ea; font-weight: 800; text-align: center; font-size: 2.2rem; margin-bottom: 2rem; text-transform: lowercase; letter-spacing: -1px; }
        .theme-lavender table { width: 100%; border-collapse: separate; border-spacing: 0; background: rgba(255,255,255,0.6); border-radius: 20px; overflow: hidden; border: 1px solid #f0abfc; }
        .theme-lavender th { background: #f5d0fe; color: #a855f7; padding: 18px; text-transform: uppercase; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.5px; }
        .theme-lavender td { padding: 18px; border-bottom: 1px solid #f5d0fe; color: #6b21a8; font-weight: 500; }
        .theme-lavender .vertical-text { writing-mode: vertical-rl; transform: rotate(180deg); text-align: center; color: #9333ea; font-weight: 700; background: #e9d5ff; height: 100%; display:flex; align-items:center; justify-content:center; border-radius: 12px; }
        .theme-lavender .footer-info { text-align: center; color: #c084fc; margin-top: 25px; font-weight: 600; font-size: 0.9rem; }

        /* Ocean */
        .theme-ocean .schedule-container { font-family: 'DM Sans', sans-serif; background: #ecfeff; padding: 40px; border-radius: 0; border-top: 12px solid #06b6d4; }
        .theme-ocean .header-title { color: #0e7490; font-size: 2.2rem; font-weight: 700; text-align: left; padding-bottom: 10px; margin-bottom: 30px; letter-spacing: -0.5px; }
        .theme-ocean table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 10px 15px -3px rgba(6, 182, 212, 0.1); border-radius: 8px; overflow: hidden; }
        .theme-ocean th { background: #06b6d4; color: white; padding: 20px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; font-size: 0.8rem; }
        .theme-ocean td { padding: 20px; border-bottom: 1px solid #cffafe; color: #155e75; font-weight: 500; }
        .theme-ocean .vertical-text { writing-mode: vertical-rl; transform: rotate(180deg); text-align: center; color: #0891b2; font-weight: 700; letter-spacing: 1px; }
        .theme-ocean .footer-info { text-align: right; color: #0e7490; margin-top: 30px; font-weight: 700; font-size: 1rem; border-top: 2px solid #a5f3fc; padding-top: 15px; }

        /* Cherry */
        .theme-cherry .schedule-container { font-family: 'Fredoka', 'Comic Sans MS', cursive; background: #fff1f2; padding: 30px; border: 4px solid #f43f5e; border-radius: 24px; box-shadow: 10px 10px 0 #f43f5e; }
        .theme-cherry .header-title { color: #e11d48; text-align: center; font-size: 2.5rem; background: white; border: 3px solid #f43f5e; padding: 15px; border-radius: 50px; margin-bottom: 30px; font-weight: 700; box-shadow: 4px 4px 0 #fecdd3; }
        .theme-cherry table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .theme-cherry th { background: transparent; color: #f43f5e; padding: 10px; text-align: center; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; }
        .theme-cherry td { background: white; border: 2px solid #f43f5e; border-radius: 12px; padding: 15px; color: #be123c; text-align: center; font-weight: 600; }
        .theme-cherry .vertical-text { writing-mode: vertical-rl; transform: rotate(180deg); text-align: center; color: #e11d48; font-weight: 800; font-size: 1.2rem; }
        .theme-cherry .footer-info { text-align: center; background: #fce7f3; color: #9f1239; border-radius: 12px; padding: 10px; margin-top: 25px; font-weight: 700; }

        /* Mobile Optimization (20:9 Force-Fix) */
        @media (max-width: 768px) {
            .navbar .container {
                padding: 0.75rem 1rem;
            }
            
            .navbar .nav-actions {
                display: flex;
                gap: 5px;
            }

            .controls {
                padding: 1.25rem;
                width: 100%;
                margin: 1rem 0;
                border-radius: 0;
            }

            .theme-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                width: 100%;
            }

            .btn-theme {
                width: 100%;
                height: 80px;
            }
            
            /* The "Card-View Force Fix" for the complex table */
            #schedule-table thead { display: none; }
            #schedule-table, #schedule-table tbody, #schedule-table tr, #schedule-table td {
                display: block;
                width: 100%;
            }

            .template-preview {
                width: 100% !important;
                padding: 1.5rem !important;
                box-shadow: none;
            }

            #schedule-table tr.row-container {
                margin-bottom: 1.5rem;
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 1rem;
                background: var(--bg-main);
                position: relative;
            }

            #schedule-table td {
                border: none;
                padding: 0.25rem 0;
                text-align: left;
            }

            #schedule-table td[rowspan] {
                background: var(--primary);
                color: white;
                margin: -1rem -1rem 1rem -1rem;
                padding: 0.75rem 1rem;
                border-radius: 16px 16px 0 0;
                font-size: 0.8rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.1em;
            }
            
            /* Reset vertical-text for mobile card headers */
            .vertical-text {
                writing-mode: horizontal-tb;
                transform: none;
                display: block;
                padding: 0;
                font-size: 0.85rem;
            }

            #schedule-table td:nth-child(2)::before { content: "TIME: "; font-weight: 800; color: var(--text-muted); font-size: 0.7rem; }
            #schedule-table td:nth-child(3)::before { content: "CODE: "; font-weight: 800; color: var(--text-muted); font-size: 0.7rem; }
            #schedule-table td:nth-child(5)::before { content: "ROOM: "; font-weight: 800; color: var(--text-muted); font-size: 0.7rem; }
            #schedule-table td:nth-child(6)::before { content: "PROF: "; font-weight: 800; color: var(--text-muted); font-size: 0.7rem; }

            .header-title {
                font-size: 1.5rem !important;
                gap: 8px;
            }
            .footer-info {
                text-align: center !important;
            }
        }
    </style>
</head>
<body>

    <?php 
    $navbar_actions = '
        <div class="nav-actions">
            <button onclick="toggleEditMode()" class="btn btn-ghost" id="btn-edit" style="color: var(--text-main); border-radius: 50px;">
                <i class="bi bi-pencil"></i> <span class="hide-mobile">Edit</span>
            </button>
            <button onclick="downloadImage()" class="btn btn-primary" id="btn-download" style="border-radius: 50px;">
                <i class="bi bi-download"></i> <span class="hide-mobile">Download</span>
            </button>
        </div>
    ';
    include 'includes/navbar.php'; 
    ?>

    <main class="container">
        
        <div class="controls animate-fade-up">
            <div class="theme-group-label" style="text-align: center; width: 100%; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px;">
                <i class="bi bi-palette"></i> Customize Design
            </div>
            
            <div class="mobile-force-stack" style="display: flex; gap: 2rem; width: 100%; justify-content: center; flex-wrap: wrap;">
                
                <!-- Professional Themes -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: center;">
                    <span class="theme-group-label">Professional</span>
                    <div class="theme-grid">
                        <button onclick="setTheme('classic')" class="btn-theme active" id="btn-template-classic">
                            <div class="theme-preview-color" style="background:#000;"></div> 
                            <span>Classic</span>
                        </button>
                        <button onclick="setTheme('modern')" class="btn-theme" id="btn-template-modern">
                            <div class="theme-preview-color" style="background:#594d5b;"></div>
                            <span>Modern</span>
                        </button>
                        <button onclick="setTheme('corporate')" class="btn-theme" id="btn-template-corporate">
                            <div class="theme-preview-color" style="background:#334155;"></div>
                            <span>Corporate</span>
                        </button>
                        <button onclick="setTheme('minimal')" class="btn-theme" id="btn-template-minimal">
                            <div class="theme-preview-color" style="background:#f1f5f9; border: 2px solid #ddd;"></div>
                            <span>Minimal</span>
                        </button>
                        <button onclick="setTheme('dark')" class="btn-theme" id="btn-template-dark">
                            <div class="theme-preview-color" style="background:#1a1a1a;"></div>
                            <span>Dark</span>
                        </button>
                    </div>
                </div>

                <!-- Vertical Divider -->
                <div class="mobile-hide-divider" style="width: 1px; background: #e2e8f0; height: auto;"></div>

                <!-- Creative Themes -->
                <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: center;">
                    <span class="theme-group-label">Creative</span>
                    <div class="theme-grid">
                        <button onclick="setTheme('pastel')" class="btn-theme" id="btn-template-pastel">
                            <div class="theme-preview-color" style="background:#ffb7b2;"></div>
                            <span>Pastel</span>
                        </button>
                        <button onclick="setTheme('lavender')" class="btn-theme" id="btn-template-lavender">
                            <div class="theme-preview-color" style="background:#ce93d8;"></div>
                            <span>Lavender</span>
                        </button>
                        <button onclick="setTheme('ocean')" class="btn-theme" id="btn-template-ocean">
                            <div class="theme-preview-color" style="background:#00acc1;"></div>
                            <span>Ocean</span>
                        </button>
                        <button onclick="setTheme('cherry')" class="btn-theme" id="btn-template-cherry">
                            <div class="theme-preview-color" style="background:#ef5350;"></div>
                            <span>Cherry</span>
                        </button>
                    </div>
                </div>

            </div>

             <button onclick="editHeader()" class="btn btn-secondary btn-edit-header" style="background:#e2e8f0; color: #475569; padding: 0.4rem 1rem; margin-top: 1rem; border-radius: 50px;">
                <i class="bi bi-gear-fill"></i> Edit Header/Footer
            </button>
        </div>

        <div id="schedule-capture" class="theme-classic"> 
            <div class="scroll-list-container">
                <div class="top-gradient"></div>
                <div class="bottom-gradient"></div>
                <div class="scroll-list no-scrollbar" style="max-height: 85vh; padding: 2rem;">
                    <div class="template-preview schedule-container">
                        <div class="header-title">
                     <?php if(true): ?>
                    <i class="bi bi-cpu-fill" style=" font-size: 2rem;"></i>
                    <span>Subject Schedule</span>
                    <i class="bi bi-journal-text" style="font-size: 2rem;"></i>
                     <?php endif; ?>
                </div>

                <table id="schedule-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Days</th>
                            <th style="width: 155px;">Time</th>
                            <th style="width: 100px;">Subject Code</th>
                            <th>Subject Name</th>
                            <th style="width: 100px;">Room</th>
                            <th style="width: 200px;">Lecturer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- MonTue -->
                        <?php $first=true; $rows=count($monTue); foreach($monTue as $i => $row): ?>
                        <tr class="row-container animated-item" ondblclick="editRow(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <?php if($first): ?>
                                <td rowspan="<?= $rows ?>" style="vertical-align: middle;">
                                    <div class="vertical-text">Monday & Tuesday</div>
                                </td>
                                <?php $first=false; ?>
                            <?php endif; ?>
                            
                            <td>
                                <?= $row['time'] ?>
                                <div class="edit-overlay" onclick="editRow(<?= htmlspecialchars(json_encode($row)) ?>)"><i class="bi bi-pencil"></i></div>
                            </td>
                            <td><?= $row['code'] ?></td>
                            <td style="font-weight: bold;"><?= $row['name'] ?></td>
                            <td><?= $row['room'] ?></td>
                            <td><?= $row['lecturer'] ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Saturday -->
                        <?php $first=true; $rows=count($sat); foreach($sat as $i => $row): ?>
                        <tr class="row-container animated-item" ondblclick="editRow(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <?php if($first): ?>
                                <td rowspan="<?= $rows ?>" style="vertical-align: middle;">
                                    <div class="vertical-text">Saturday</div>
                                </td>
                                <?php $first=false; ?>
                            <?php endif; ?>
                            
                            <td>
                                <?= $row['time'] ?>
                                <div class="edit-overlay" onclick="editRow(<?= htmlspecialchars(json_encode($row)) ?>)"><i class="bi bi-pencil"></i></div>
                            </td>
                            <td><?= $row['code'] ?></td>
                            <td style="font-weight: bold;"><?= $row['name'] ?></td>
                            <td><?= $row['room'] ?></td>
                            <td><?= $row['lecturer'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Others -->
                        <?php $first=true; $rows=count($other); foreach($other as $i => $row): ?>
                        <tr class="row-container animated-item" ondblclick="editRow(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <?php if($first): ?>
                                <td rowspan="<?= $rows ?>" style="vertical-align: middle;">
                                    <div class="vertical-text"><?= $row['dayStr'] ?></div>
                                </td>
                                <?php $first=false; ?>
                            <?php endif; ?>
                            
                            <td>
                                <?= $row['time'] ?>
                                <div class="edit-overlay" onclick="editRow(<?= htmlspecialchars(json_encode($row)) ?>)"><i class="bi bi-pencil"></i></div>
                            </td>
                            <td><?= $row['code'] ?></td>
                            <td style="font-weight: bold;"><?= $row['name'] ?></td>
                            <td><?= $row['room'] ?></td>
                            <td><?= $row['lecturer'] ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <tr>
                            <td colspan="6" class="sub-header" style="height: 30px; vertical-align: middle;">
                                <?= $year ?>
                            </td>
                        </tr>

                    </tbody>
                </table>

                <div class="footer-info">
                   <span class="<?= $activeSem == '1' ? 'active-sem' : '' ?>" style="border: 2px solid black; padding: 5px 15px; margin-right: 10px; font-size: 1rem; <?= $activeSem == '1' ? 'background:black; color:white;' : '' ?>">
                        <?= $semester1 ?>
                   </span>
                   <span class="<?= $activeSem == '2' ? 'active-sem' : '' ?>" style="border: 2px solid black; padding: 5px 15px; font-size: 1rem; <?= $activeSem == '2' ? 'background:black; color:white;' : '' ?>">
                        <?= $semester2 ?>
                   </span>
                   
                   <div style="margin-top: 15px;">
                       <?= $sectionCourse ?><br>
                       <span style="font-size: 2rem;"><?= $sectionName ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        let isEditMode = false;
        
        // Metadata (passed from PHP)
        let meta = {
            year: '<?= $year ?>',
            semester1: '<?= $semester1 ?>',
            semester2: '<?= $semester2 ?>',
            active_sem: '<?= $activeSem ?>',
            section_course: '<?= $sectionCourse ?>',
            section_name: '<?= $sectionName ?>'
        };

        function setTheme(theme) {
            document.getElementById('schedule-capture').className = 'theme-' + theme;
            document.querySelectorAll('.btn-theme').forEach(b => b.classList.remove('active'));
            document.getElementById('btn-template-' + theme).classList.add('active');
        }

        function toggleEditMode() {
            isEditMode = !isEditMode;
            document.body.classList.toggle('edit-mode', isEditMode);
            
            document.getElementById('btn-edit').classList.toggle('active', isEditMode);
            document.getElementById('btn-edit').innerHTML = isEditMode ? '<i class="bi bi-x-lg"></i> Exit Edit Mode' : '<i class="bi bi-pencil"></i> Edit Mode';
        }

        function editRow(data) {
            if (!isEditMode) return;

            Swal.fire({
                title: 'Edit Class',
                html: `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; text-align: left;">
                        <div style="grid-column: span 2;">
                            <label>Subject Name</label>
                            <input id="swal-name" class="swal2-input" style="margin:0; width:100%;" value="${data.name}">
                        </div>
                        <div>
                            <label>Code</label>
                            <input id="swal-code" class="swal2-input" style="margin:0; width:100%;" value="${data.code}">
                        </div>
                        <div>
                            <label>Room</label>
                            <input id="swal-room" class="swal2-input" style="margin:0; width:100%;" value="${data.room}">
                        </div>
                        <div style="grid-column: span 2;">
                            <label>Lecturer</label>
                            <input id="swal-lecturer" class="swal2-input" style="margin:0; width:100%;" value="${data.lecturer}">
                        </div>
                        <div style="grid-column: span 2; border-top: 1px solid #eee; margin-top: 10px; padding-top:10px;">
                            <strong>Schedule</strong>
                        </div>
                        <div>
                            <label>Days (e.g. Mon & Tue)</label>
                            <input id="swal-days" class="swal2-input" style="margin:0; width:100%;" value="${data.dayStr}">
                        </div>
                        <div>
                            <label>Time (e.g. 7:30-9:00)</label>
                            <input id="swal-time" class="swal2-input" style="margin:0; width:100%;" value="${data.time}">
                        </div>
                    </div>
                `,
                width: 600,
                showCancelButton: true,
                confirmButtonText: 'Save',
                preConfirm: () => {
                    return {
                        id: data.id,
                        name: document.getElementById('swal-name').value,
                        code: document.getElementById('swal-code').value,
                        room: document.getElementById('swal-room').value,
                        lecturer: document.getElementById('swal-lecturer').value,
                        days: document.getElementById('swal-days').value,
                        time: document.getElementById('swal-time').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    saveSingleUpdate({ row: result.value });
                }
            });
        }

        function editHeader() {
            Swal.fire({
                title: 'Edit Header & Footer',
                html: `
                    <div style="text-align: left; gap: 10px; display: flex; flex-direction: column;">
                        <div>
                            <label>Academic Year</label>
                            <input id="meta-year" class="swal2-input" style="margin:0; width:100%;" value="${meta.year}">
                        </div>
                        <div>
                            <label>Course Name</label>
                            <input id="meta-course" class="swal2-input" style="margin:0; width:100%;" value="${meta.section_course}">
                        </div>
                        <div>
                            <label>Section</label>
                            <input id="meta-section" class="swal2-input" style="margin:0; width:100%;" value="${meta.section_name}">
                        </div>
                        <div>
                            <label>Active Semester</label>
                            <select id="meta-active-sem" class="swal2-select" style="margin:0; width:100%; display:block;">
                                <option value="1" ${meta.active_sem == '1' ? 'selected' : ''}>1st Semester</option>
                                <option value="2" ${meta.active_sem == '2' ? 'selected' : ''}>2nd Semester</option>
                            </select>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                             <div>
                                <label>Sem 1 Label</label>
                                <input id="meta-sem1" class="swal2-input" style="margin:0; width:100%;" value="${meta.semester1}">
                             </div>
                             <div>
                                <label>Sem 2 Label</label>
                                <input id="meta-sem2" class="swal2-input" style="margin:0; width:100%;" value="${meta.semester2}">
                             </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Save Details',
                preConfirm: () => {
                    return {
                        year: document.getElementById('meta-year').value,
                        section_course: document.getElementById('meta-course').value,
                        section_name: document.getElementById('meta-section').value,
                        active_sem: document.getElementById('meta-active-sem').value,
                        semester1: document.getElementById('meta-sem1').value,
                        semester2: document.getElementById('meta-sem2').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    saveSingleUpdate({ meta: result.value });
                }
            });
        }

        async function saveSingleUpdate(payload) {
            // Helper to handle API call for one update
            // payload struct: { row: {...} } OR { meta: {...} }
            
            // Transform 'row' to 'rows' list for API compat
            let apiPayload = {};
            if (payload.row) {
                apiPayload.rows = [payload.row];
            }
            if (payload.meta) {
                apiPayload.meta = payload.meta;
            }

            try {
                const res = await fetch('api/save_schedule.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(apiPayload)
                });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => location.reload());
                } else {
                    throw new Error(data.error);
                }
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
        }

        function downloadImage() {
             const element = document.getElementById('schedule-capture');
             // Temporarily disable scroll list for capture
             const scrollList = element.querySelector('.scroll-list');
             const originalMaxHeight = scrollList.style.maxHeight;
             scrollList.style.maxHeight = 'none';

             html2canvas(element, { 
                scale: 2, 
                backgroundColor: null,
                onclone: (clonedDoc) => {
                    // Fix vertical text alignment for export
                    const verticalTexts = clonedDoc.querySelectorAll('.vertical-text');
                    
                    verticalTexts.forEach(el => {
                        el.style.writingMode = 'horizontal-tb';
                        el.style.webkitWritingMode = 'horizontal-tb';
                        el.style.whiteSpace = 'nowrap';
                        
                        el.style.position = 'absolute';
                        el.style.top = '50%';
                        el.style.left = '50%';
                        el.style.transform = 'translate(-50%, -50%) rotate(-90deg)';
                        
                        if(el.parentElement && el.parentElement.tagName === 'TD') {
                            el.parentElement.style.position = 'relative';
                            el.parentElement.style.height = 'auto';
                        }
                    });
                }
            }).then(canvas => {
                 scrollList.style.maxHeight = originalMaxHeight;
                 const link = document.createElement('a');
                link.download = 'schedule.png';
                link.href = canvas.toDataURL();
                link.click();
             });
        }

        // Initialize Animated List
        document.addEventListener('DOMContentLoaded', () => {
            initAnimatedList('.scroll-list-container');
        });
    </script>
</body>
</html>
