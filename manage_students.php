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
    <style>
        .search-area {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
            margin-top: -1px;
        }

        .search-container {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--bg-main);
            color: var(--text-main);
            font-size: 0.95rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(23, 23, 23, 0.05);
            transform: translateY(-1px);
        }

        .search-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .section-title {
            margin: 2rem 0 1.5rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.75rem;
        }

        /* High-Density Data Table */
        .data-table-container {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.01);
        }

        .student-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .student-table th {
            text-align: left;
            padding: 1rem 1.5rem;
            background: #fafafa;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
        }

        .student-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            color: var(--text-main);
        }

        .student-table tr:last-child td {
            border-bottom: none;
        }

        .student-table tr {
            transition: background 0.2s;
        }

        .student-table tr:hover {
            background: #fdfdfd;
        }

        .student-name {
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            color: var(--text-main);
            text-decoration: none;
        }

        .student-id {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: var(--text-muted);
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .type-regular { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
        .type-irregular { background: #fff1f2; color: #991b1b; border: 1px solid #ffe4e6; }

        .btn-action {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: white;
            color: var(--text-muted);
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-action:hover {
            background: #f8fafc;
            color: var(--primary);
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .btn-delete:hover {
            background: #fef2f2;
            color: var(--danger);
            border-color: var(--danger);
        }

        /* Responsive Overhaul */
        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .student-table td { padding: 1rem; }
            .student-name { font-size: 0.95rem; }
            .search-area { padding: 0.75rem 0; }

            .table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .data-table-container {
                border: none;
                background: transparent;
                box-shadow: none;
            }

            .student-table, .student-table thead, .student-table tbody, .student-table th, .student-table td, .student-table tr {
                display: block;
            }

            .student-table thead {
                display: none; /* Hide header on mobile cards */
            }

            .student-table tr {
                margin-bottom: 1rem;
                background: white;
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 1rem;
                box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            }

            .student-table td {
                border: none;
                padding: 0.5rem 0 !important;
                display: flex;
                justify-content: space-between;
                align-items: center;
                width: 100% !important;
                text-align: left !important;
            }

            .student-table td:not(:last-child) {
                border-bottom: 1px solid #f8fafc;
            }

            .student-table td::before {
                content: attr(data-label);
                font-size: 0.7rem;
                font-weight: 700;
                text-transform: uppercase;
                color: var(--text-muted);
                letter-spacing: 0.05em;
            }

            .btn-action {
                width: 44px; /* Proper touch target size */
                height: 44px;
                font-size: 1.1rem;
            }
            
            .student-id {
                font-size: 0.7rem;
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
                <input type="text" id="searchInput" class="search-input" placeholder="Search by name or reference ID..." autocomplete="off">
            </div>
        </div>
    </div>

        <div class="data-table-container animate-fade-up">
            <div class="table-wrapper">
                <table class="student-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Student Identity</th>
                        <th class="hide-mobile">Course & Section</th>
                        <th>Type</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="studentBody">
                    <?php foreach ($allUsers as $idx => $user): 
                        $typeClass = ($user['student_type'] ?? 'regular') === 'regular' ? 'type-regular' : 'type-irregular';
                        $stagger = "stagger-" . min($idx + 1, 8);
                    ?>
                        <tr class="student-row animate-fade-up <?= $stagger ?>" 
                            data-name="<?= htmlspecialchars($user['name']) ?>"
                            data-qr="<?= htmlspecialchars($user['qr_code']) ?>"
                            id="row-<?= htmlspecialchars($user['qr_code']) ?>"
                            <?php 
                                // Attach datasets for editUser()
                                echo ' data-firstname="'.htmlspecialchars($user['first_name']??'').'"';
                                echo ' data-lastname="'.htmlspecialchars($user['last_name']??'').'"';
                                echo ' data-middle="'.htmlspecialchars($user['middle_initial']??'').'"';
                                echo ' data-course="'.htmlspecialchars($user['course']??'').'"';
                                echo ' data-section="'.htmlspecialchars($user['section']??'').'"';
                                echo ' data-type="'.htmlspecialchars($user['student_type']??'regular').'"';
                                echo ' data-birthday="'.htmlspecialchars($user['birthday']??'').'"';
                                echo ' data-sex="'.htmlspecialchars($user['sex']??'').'"';
                                echo ' data-civil="'.htmlspecialchars($user['civil_status']??'').'"';
                                echo ' data-religion="'.htmlspecialchars($user['religion']??'').'"';
                                echo ' data-citizenship="'.htmlspecialchars($user['citizenship']??'').'"';
                                echo ' data-contact="'.htmlspecialchars($user['contact_number']??'').'"';
                                echo ' data-pob="'.htmlspecialchars($user['place_of_birth']??'').'"';
                                echo ' data-year="'.htmlspecialchars($user['year_level']??'1st').'"';
                            ?>
                        >
                            <td data-label="Student">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <a href="profile.php?qr=<?= urlencode($user['qr_code']) ?>" class="student-name">
                                        <?= htmlspecialchars($user['name']) ?>
                                    </a>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="student-id"><?= htmlspecialchars($user['qr_code']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Course">
                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                    <span style="font-weight: 500; font-size: 0.85rem;"><?= htmlspecialchars($user['course'] ?? 'No Course') ?></span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($user['section'] ?? 'No Section') ?></span>
                                </div>
                            </td>
                            <td data-label="Type">
                                <span class="type-badge <?= $typeClass ?>"><?= ($user['student_type'] ?? 'regular') ?></span>
                            </td>
                            <td data-label="Actions" style="text-align:right;">
                                <div style="display:flex; gap:8px; justify-content:flex-end;">
                                    <button onclick="editUser(this)" class="btn-action" title="Edit Profile">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <?php if(($user['student_type'] ?? 'regular') === 'irregular'): ?>
                                        <button onclick="manageSubjects(this)" class="btn-action" style="color:var(--warning)" title="Manage Subjects">
                                            <i class="bi bi-journal-text"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="deleteUser(this)" class="btn-action btn-delete" title="Move to Trash">
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

    </main>
    


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

        function addStudent() {
            Swal.fire({
                title: 'Add New Student',
                width: 'auto',
                customClass: {
                    popup: 'responsive-modal'
                },
                html: `
                    <style>
                        .swal-grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:10px; }
                        .swal2-input, .swal2-select { 
                            margin: 5px 0 !important; 
                            width: 100% !important; 
                            font-size: 0.9rem !important;
                             height: auto !important;
                            padding: 0.6rem !important;
                        }
                        .swal2-select { padding-right: 2rem !important; }
                        @media (max-width: 600px) {
                            .swal-grid-2 { grid-template-columns: 1fr; gap:5px; }
                        }
                    </style>
                    <div style="text-align:left; display:flex; flex-direction:column; gap:10px;">
                        
                        <div class="form-floating-custom">
                             <label><i class="bi bi-qr-code"></i> Student ID / QR Code *</label>
                             <input type="text" id="swal-id" class="swal2-input" placeholder="e.g. 2024-0001">
                        </div>

                        <div class="swal-grid-2">
                            <div>
                                <label style="font-size:0.85rem; font-weight:600; color:#555;">First Name *</label>
                                <input type="text" id="swal-firstname" class="swal2-input" placeholder="John">
                            </div>
                            <div>
                                <label style="font-size:0.85rem; font-weight:600; color:#555;">Last Name *</label>
                                <input type="text" id="swal-lastname" class="swal2-input" placeholder="Doe">
                            </div>
                        </div>
                        
                        <div class="swal-grid-2">
                             <div>
                                <label style="font-size:0.85rem; font-weight:600; color:#555;">Course / Strand</label>
                                <input type="text" id="swal-course" class="swal2-input" placeholder="BSIS">
                            </div>
                             <div>
                                <label style="font-size:0.85rem; font-weight:600; color:#555;">Section / Set</label>
                                <input type="text" id="swal-section" class="swal2-input" placeholder="2-A">
                            </div>
                        </div>

                         <div class="swal-grid-2">
                             <div>
                                <label style="font-size:0.85rem; font-weight:600; color:#555;">M.I.</label>
                                <input type="text" id="swal-middle" class="swal2-input" maxlength="3" placeholder="M.">
                            </div>
                             <div>
                                <label style="font-size:0.85rem; font-weight:600; color:#555;">Student Type</label>
                                <select id="swal-type" class="swal2-select" style="width:100%;">
                                    <option value="regular" selected>Regular Student</option>
                                    <option value="irregular">Irregular / Other</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top:10px; font-size:0.85rem; color:#666; font-style:italic; background:#f8fafc; padding:8px; border-radius:6px;">
                            <i class="bi bi-info-circle"></i> Basic info only. You can add full details (Birthday, Contact, etc.) later by editing the student profile.
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-person-plus"></i> Add Student',
                confirmButtonColor: 'var(--primary)',
                preConfirm: () => {
                   return {
                       qr_code: document.getElementById('swal-id').value.trim(),
                       first_name: document.getElementById('swal-firstname').value.trim(),
                       last_name: document.getElementById('swal-lastname').value.trim(),
                       middle_initial: document.getElementById('swal-middle').value.trim(),
                       student_type: document.getElementById('swal-type').value,
                       course: document.getElementById('swal-course').value.trim(),
                       section: document.getElementById('swal-section').value.trim()
                   };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const data = result.value;
                    if (!data.qr_code || !data.first_name || !data.last_name) {
                         Swal.fire('Error', 'ID, First Name, and Last Name are required.', 'error');
                         return;
                    }

                    fetch('api/manage_users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'add', ...data })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if(res.status === 'success') {
                             Swal.fire('Added!', 'New student registered successfully.', 'success').then(() => location.reload());
                        } else {
                             Swal.fire('Error', res.message, 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Network Error', 'error'));
                }
            });
        }

        function editUser(btn) {
           const row = btn.closest('.student-row');
           const qr_code = row.dataset.qrOriginal || row.dataset.qr; 
           const d = row.dataset;
           
            Swal.fire({
                title: 'Edit Student',
                width: 'auto',
                customClass: {
                    popup: 'responsive-modal'
                },
                html: `
                    <style>
                        .swal-grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:10px; }
                        /* Override SweetAlert2 default input styles to fit grid */
                        .swal2-input, .swal2-select { 
                            margin: 5px 0 !important; 
                            width: 100% !important; 
                            font-size: 0.9rem !important;
                            height: auto !important;
                            padding: 0.6rem !important;
                        }
                        .swal2-select { padding-right: 2rem !important; }
                        
                        @media (max-width: 600px) {
                            .swal-grid-2 { grid-template-columns: 1fr; gap:5px; }
                            .responsive-modal { width: 95% !important; padding: 0.5em !important; }
                        }
                    </style>
                    <div style="text-align:left; max-height:70vh; overflow-y:auto; overflow-x:hidden; padding: 0 5px;">
                        
                        <!-- Section: Identity -->
                        <h6 style="color:var(--primary); border-bottom:1px solid #eee; padding-bottom:5px; margin-bottom:10px;">Identity</h6>
                        <div class="swal-grid-2">
                             <div>
                                <label style="font-size:0.8rem; font-weight:bold;">First Name *</label>
                                <input type="text" id="edit-firstname" class="swal2-input" value="${d.firstname}">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:bold;">Last Name *</label>
                                <input type="text" id="edit-lastname" class="swal2-input" value="${d.lastname}">
                            </div>
                        </div>
                        <div class="swal-grid-2" style="grid-template-columns: 80px 1fr;">
                             <div>
                                <label style="font-size:0.8rem; font-weight:bold;">M.I.</label>
                                <input type="text" id="edit-middle" class="swal2-input" value="${d.middle}" maxlength="3">
                            </div>
                             <div>
                                <label style="font-size:0.8rem; font-weight:bold;">Student Type</label>
                                <select id="edit-type" class="swal2-select" style="width:100%;">
                                    <option value="regular" ${d.type === 'regular' ? 'selected' : ''}>Regular</option>
                                    <option value="irregular" ${d.type === 'irregular' ? 'selected' : ''}>Irregular / Other</option>
                                </select>
                            </div>
                        </div>
                        
                         <!-- Section: Personal Details -->
                        <h6 style="color:var(--primary); border-bottom:1px solid #eee; padding-bottom:5px; margin-bottom:10px; margin-top:20px;">Personal Details</h6>
                        
                        <div class="swal-grid-2">
                             <div>
                                 <label style="font-size:0.8rem; font-weight:bold;">Course / Strand</label>
                                 <input type="text" id="edit-course" class="swal2-input" value="${d.course}" placeholder="e.g. BSCS">
                            </div>
                            <div>
                                 <label style="font-size:0.8rem; font-weight:bold;">Section / Set</label>
                                 <input type="text" id="edit-section" class="swal2-input" value="${d.section || ''}" placeholder="e.g. 2-A">
                            </div>
                        </div>

                         <div class="swal-grid-2">
                             <div>
                                <label style="font-size:0.8rem; font-weight:bold;">Birthday</label>
                                <input type="date" id="edit-birthday" class="swal2-input" value="${d.birthday}">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:bold;">Sex</label>
                                <select id="edit-sex" class="swal2-select" style="width:100%;">
                                    <option value="" disabled ${!d.sex ? 'selected' : ''}>Select...</option>
                                    <option value="Male" ${d.sex === 'Male' ? 'selected' : ''}>Male</option>
                                    <option value="Female" ${d.sex === 'Female' ? 'selected' : ''}>Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="swal-grid-2">
                             <div>
                                <label style="font-size:0.8rem; font-weight:bold;">Civil Status</label>
                                 <select id="edit-civil" class="swal2-select" style="width:100%;">
                                    <option value="" disabled ${!d.civil ? 'selected' : ''}>Select...</option>
                                    <option value="Single" ${d.civil === 'Single' ? 'selected' : ''}>Single</option>
                                    <option value="Married" ${d.civil === 'Married' ? 'selected' : ''}>Married</option>
                                    <option value="Widowed" ${d.civil === 'Widowed' ? 'selected' : ''}>Widowed</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:bold;">Religion</label>
                                <input type="text" id="edit-religion" class="swal2-input" value="${d.religion}">
                            </div>
                        </div>
                        
                        <div class="swal-grid-2">
                             <div>
                                 <label style="font-size:0.8rem; font-weight:bold;">Place of Birth</label>
                                 <input type="text" id="edit-pob" class="swal2-input" value="${d.pob}">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:bold;">Year Level</label>
                                <select id="edit-year" class="swal2-select" style="width:100%;">
                                    <option value="1st" ${d.year === '1st' ? 'selected' : ''}>1st Year</option>
                                    <option value="2nd" ${d.year === '2nd' ? 'selected' : ''}>2nd Year</option>
                                    <option value="3rd" ${d.year === '3rd' ? 'selected' : ''}>3rd Year</option>
                                    <option value="4th" ${d.year === '4th' ? 'selected' : ''}>4th Year</option>
                                </select>
                            </div>
                        </div>

                         <!-- Section: Contact -->
                        <h6 style="color:var(--primary); border-bottom:1px solid #eee; padding-bottom:5px; margin-bottom:10px; margin-top:20px;">Contact Info</h6>

                         <div class="swal-grid-2">
                             <div>
                                <label style="font-size:0.8rem; font-weight:bold;">Contact Number</label>
                                <input type="text" id="edit-contact" class="swal2-input" value="${d.contact}">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:bold;">Citizenship</label>
                                <input type="text" id="edit-citizenship" class="swal2-input" value="${d.citizenship}">
                            </div>
                        </div>

                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Save Changes',
                confirmButtonColor: 'var(--primary)',
                preConfirm: () => {
                   return {
                       qr_code: qr_code, // Keep original QR
                       first_name: document.getElementById('edit-firstname').value.trim(),
                       last_name: document.getElementById('edit-lastname').value.trim(),
                       middle_initial: document.getElementById('edit-middle').value.trim(),
                       birthday: document.getElementById('edit-birthday').value,
                       course: document.getElementById('edit-course').value.trim(),
                       sex: document.getElementById('edit-sex').value,
                       civil_status: document.getElementById('edit-civil').value,
                       religion: document.getElementById('edit-religion').value.trim(),
                       citizenship: document.getElementById('edit-citizenship').value.trim(),
                       contact_number: document.getElementById('edit-contact').value.trim(),
                       place_of_birth: document.getElementById('edit-pob').value.trim(),
                       student_type: document.getElementById('edit-type').value,
                       year_level: document.getElementById('edit-year').value,
                       section: document.getElementById('edit-section').value.trim()
                   };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                     const data = result.value;
                    if (!data.first_name || !data.last_name) {
                         Swal.fire('Error', 'First Name and Last Name are required.', 'error');
                         return;
                    }
                     fetch('api/manage_users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'update', ...data })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if(res.status === 'success') {
                             Swal.fire('Updated!', 'Student updated successfully.', 'success').then(() => location.reload());
                        } else {
                             Swal.fire('Error', res.message, 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Network Error', 'error'));
                }
            });
        }

        function deleteUser(btn) {
            const row = btn.closest('.student-row');
            const id = row.dataset.qrOriginal || row.dataset.qr;
            const name = row.dataset.nameDisplay || row.dataset.name;

            Swal.fire({
                title: 'Delete Student?',
                text: `This will delete ${name} and ALL their attendance history.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/manage_users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'delete', qr_code: id })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.status === 'success') {
                            Swal.fire('Deleted!', 'Student has been removed.', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
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
    </script>
</body>
</html>
