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

        /* PC Row Layout Styles - "Swiss Academic" Grid Integration */
        @media (min-width: 769px) {
            .student-table {
                display: block; /* Convert table to block for grid rows */
            }
            .student-table thead, .student-table tbody {
                display: block;
                width: 100%;
            }
            .student-table tr {
                display: grid !important;
                grid-template-columns: 2fr 1.2fr 0.8fr 0.6fr !important;
                align-items: center;
                background: transparent !important;
                border: none !important;
                border-bottom: 1px solid var(--border) !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                margin: 0 !important;
                transition: background 0.15s ease;
            }
            .student-table tr:hover {
                background: #f8fafc !important;
            }
            .student-table th, .student-table td {
                display: block !important;
                width: auto !important;
                padding: 1.1rem 1.5rem !important;
                border: none !important;
                overflow: hidden;
            }
            .column-actions {
                text-align: right;
            }

            /* Single Row Alignment for Student Identity & Course */
            .pc-row {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 10px !important;
                white-space: nowrap;
                overflow: hidden;
            }
            .student-name {
                flex-shrink: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                font-size: 0.95rem;
            }
            .pc-separator {
                display: inline-block;
                color: var(--text-muted);
                opacity: 0.4;
                font-size: 0.8rem;
                flex-shrink: 0;
            }
            .student-id {
                white-space: nowrap;
                font-size: 0.7rem;
                letter-spacing: 0.02em;
            }
        }
        
        /* Default Flex for the cell content */
        .cell-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        @media (max-width: 768px) {
            .pc-separator { display: none; }
            .pc-row { display: flex; flex-direction: column; }
        }

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
                display: none; 
            }

            .student-table tr {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: 0.5rem;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 0.6rem 0.85rem;
                box-shadow: var(--glass-shadow);
            }

            .student-table td {
                border: none;
                padding: 0 !important;
                display: block;
                width: auto !important;
                font-size: 0.85rem;
            }

            .student-table td:first-child {
                flex: 1;
                min-width: 0;
            }
            
            .student-table td:first-child .student-name {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                display: block;
            }

            .student-table .hide-mobile {
                display: none !important;
            }

            .student-table td::before {
                display: none;
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
                                <th class="column-student">Student Identity</th>
                                <th class="column-course hide-mobile">Course & Section</th>
                                <th class="column-type">Type</th>
                                <th class="column-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentBody">
                            <?php foreach ($allUsers as $idx => $user): 
                                $typeClass = ($user['student_type'] ?? 'regular') === 'regular' ? 'type-regular' : 'type-irregular';
                            ?>
                                <tr class="student-row animated-item" 
                                    data-name="<?= htmlspecialchars($user['name']) ?>"
                                    data-qr="<?= htmlspecialchars($user['qr_code']) ?>"
                                    id="row-<?= htmlspecialchars($user['qr_code']) ?>"
                                    style="margin-bottom: 0;"
                            <?php 
                                // Standardize dates for HTML input
                                $bday = !empty($user['birthday']) ? date('Y-m-d', strtotime($user['birthday'])) : '';
                                
                                // Attach datasets for editUser()
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
                            ?>
                        >
                            <td data-label="Student" class="column-student">
                                <div class="cell-content pc-row">
                                    <a href="profile.php?qr=<?= urlencode($user['qr_code']) ?>" class="student-name">
                                        <?= htmlspecialchars($user['name']) ?>
                                    </a>
                                    <span class="pc-separator">·</span>
                                    <div class="student-id-wrapper">
                                        <span class="student-id"><?= htmlspecialchars($user['qr_code']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Course" class="column-course hide-mobile">
                                <div class="cell-content pc-row">
                                    <span style="font-weight: 500; font-size: 0.85rem;"><?= htmlspecialchars($user['course'] ?? 'No Course') ?></span>
                                    <span class="pc-separator">·</span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($user['section'] ?? 'No Section') ?></span>
                                </div>
                            </td>
                            <td data-label="Type" class="column-type">
                                <span class="type-badge <?= $typeClass ?>"><?= ($user['student_type'] ?? 'regular') ?></span>
                            </td>
                            <td data-label="Actions" class="column-actions">
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
            </div>
        </div>

    </main>
    


    <!-- Custom Management Modal -->
    <div id="managementModal" class="modal-overlay" onclick="if(event.target == this) closeManagementModal()">
        <div class="modal-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h3 id="modalTitle" style="margin: 0; font-weight: 800; letter-spacing: -0.04em;">Student Profile</h3>
                <button onclick="closeManagementModal()" style="background: none; border: none; font-size: 1.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <form id="managementForm" onsubmit="submitManagementForm(event)">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="qr_original" id="modalQrOriginal">

                <div class="swal-grid-2">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Student ID / QR *</label>
                        <input type="text" name="qr_code" id="m-qr" class="form-control" required placeholder="e.g. 2024-0001">
                    </div>
                </div>

                <div class="swal-grid-2">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">First Name *</label>
                        <input type="text" name="first_name" id="m-fname" class="form-control" required placeholder="John">
                    </div>
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Last Name *</label>
                        <input type="text" name="last_name" id="m-lname" class="form-control" required placeholder="Doe">
                    </div>
                    <div style="grid-column: span 2;">
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Middle Initial</label>
                        <input type="text" name="middle_initial" id="m-middle" class="form-control" maxlength="2" placeholder="e.g. M">
                    </div>
                </div>

                <div class="swal-grid-2">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Email Address</label>
                        <input type="email" name="email" id="m-email" class="form-control" placeholder="john.doe@example.com">
                    </div>
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Mobile Contact</label>
                        <input type="text" name="contact_number" id="m-contact" class="form-control" placeholder="0917XXXXXXX">
                    </div>
                </div>

                <div class="swal-grid-2">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Course / Strand</label>
                        <input type="text" name="course" id="m-course" class="form-control" placeholder="e.g. BSCS">
                    </div>
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Section / Set</label>
                        <input type="text" name="section" id="m-section" class="form-control" placeholder="e.g. 2-A">
                    </div>
                </div>

                <div class="swal-grid-2">
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Student Type</label>
                        <select name="student_type" id="m-type" class="form-control">
                            <option value="regular">Regular</option>
                            <option value="irregular">Irregular / Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="section-title" style="margin: 0 0 10px; border: none; padding: 0;">Year Level</label>
                        <select name="year_level" id="m-year" class="form-control">
                            <option value="1st">1st Year</option>
                            <option value="2nd">2nd Year</option>
                            <option value="3rd">3rd Year</option>
                            <option value="4th">4th Year</option>
                        </select>
                    </div>
                </div>

                <!-- Fields for Edit mode (Hidden in Add mode) -->
                <div id="extendedFields" style="margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 2rem;">
                     <h6 style="text-transform: uppercase; letter-spacing: 0.1em; font-size: 0.7rem; color: var(--text-muted); font-weight: 800; margin-bottom: 1.5rem;">Additional Details</h6>
                     <div class="swal-grid-2">
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Birthday</label>
                            <input type="date" name="birthday" id="m-birthday" class="form-control">
                        </div>
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Sex</label>
                            <select name="sex" id="m-sex" class="form-control">
                                <option value="">Select...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="swal-grid-2" style="margin-top: 1rem;">
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Civil Status</label>
                            <input type="text" name="civil_status" id="m-civil" class="form-control">
                        </div>
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Religion</label>
                            <input type="text" name="religion" id="m-religion" class="form-control">
                        </div>
                    </div>
                    <div class="swal-grid-2" style="margin-top: 1rem;">
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Citizenship</label>
                            <input type="text" name="citizenship" id="m-citizenship" class="form-control">
                        </div>
                        <div>
                            <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Place of Birth</label>
                            <input type="text" name="place_of_birth" id="m-pob" class="form-control">
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 3rem; gap: 1rem;">
                    <button type="button" onclick="closeManagementModal()" class="btn btn-ghost" style="border: 1px solid var(--border); padding: 0.8rem 2rem; border-radius: 12px; font-weight: 600;">Discard</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem; font-weight: 800; border-radius: 12px;">Save Changes</button>
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
            document.getElementById('modalAction').value = 'add';
            document.getElementById('m-qr').readOnly = false;
            document.getElementById('extendedFields').style.display = 'none';
            showModal();
        }

        function editUser(btn) {
            const d = btn.closest('.student-row').dataset;
            const f = document.getElementById('managementForm');
            f.reset();
            
            document.getElementById('modalTitle').innerText = 'Edit Student Profile';
            document.getElementById('modalAction').value = 'update';
            document.getElementById('modalQrOriginal').value = d.qr;
            document.getElementById('extendedFields').style.display = 'block';
            
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
                             Swal.fire({
                                title: 'Deleted!',
                                text: 'The student and their history have been removed.',
                                icon: 'success',
                                confirmButtonColor: 'var(--primary)'
                            }).then(() => location.reload());
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

        // Initialize Animated List
        document.addEventListener('DOMContentLoaded', () => {
            initAnimatedList('.scroll-list-container', {
                onItemSelect: (item) => {
                    const profileLink = item.querySelector('.student-name');
                    if(profileLink) window.location.href = profileLink.href;
                }
            });
        });
    </script>
</body>
</html>
