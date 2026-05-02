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
            padding: 1.5rem 0;
            margin-bottom: 1rem;
        }

        .search-container {
            position: relative;
            max-width: 650px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 1.1rem 1.25rem 1.1rem 3.5rem;
            border-radius: var(--radius-md);
            border: none;
            background: var(--bg-main);
            color: var(--text-main);
            font-size: 1rem;
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
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.25rem;
            pointer-events: none;
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
            padding: 1.25rem 1.5rem;
            background: var(--bg-card);
            color: var(--text-muted);
            font-weight: 800;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-bottom: 1px solid var(--bg-main);
        }

        .student-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--bg-main);
            vertical-align: middle;
            color: var(--text-main);
        }

        .student-table tr:hover {
            background: var(--bg-hover);
        }

        .student-name {
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            color: var(--text-main);
            text-decoration: none;
            transition: color 0.2s;
        }
        .student-name:hover { color: var(--primary); }

        .student-id {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            color: var(--text-muted);
            background: var(--bg-main);
            padding: 3px 8px;
            border-radius: 6px;
            box-shadow: var(--shadow-neu-in-sm);
        }

        .type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-neu-out-sm);
        }

        .type-regular { background: #f0fdf4; color: #166534; }
        .type-irregular { background: #fff1f2; color: #991b1b; }

        .btn-action {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            border: none;
            background: var(--bg-card);
            color: var(--text-muted);
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.2s var(--ease-out-expo);
            cursor: pointer;
        }

        .btn-action:hover {
            box-shadow: var(--shadow-neu-in-sm);
            color: var(--primary);
            transform: scale(0.95);
        }

        .btn-delete:hover {
            color: var(--danger);
        }

        @media (min-width: 769px) {
            .student-table { display: block; }
            .student-table thead, .student-table tbody { display: block; width: 100%; }
            .student-table tr {
                display: grid !important;
                grid-template-columns: 2fr 1fr 0.8fr 0.8fr !important;
                align-items: center;
                border: none !important;
                border-bottom: 1px solid var(--bg-main) !important;
                padding: 0.25rem 0;
            }
            .student-table th, .student-table td {
                padding: 1.25rem 1.5rem !important;
                border: none !important;
            }
            .column-actions { text-align: right; }
        }

        @media (max-width: 768px) {
            .search-area { padding: 1rem 0; }
            .data-table-container { background: transparent; box-shadow: none; overflow: visible; }
            .student-table thead { display: none; }
            .student-table tr {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1.25rem;
                background: var(--bg-card);
                border-radius: var(--radius-md);
                padding: 1.25rem;
                box-shadow: var(--shadow-neu-out-sm);
            }
            .student-table td { border: none; padding: 0 !important; }
            .student-table td:first-child { flex: 1; }
            .column-actions { width: auto; }
            .btn-action { width: 44px; height: 44px; }
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
                                <tr class="student-row animated-item hover-lift" 
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
                                echo ' data-bday-img="'.htmlspecialchars($user['birthday_image']??'').'"';
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

                    <div style="margin-top: 1.5rem;">
                        <label class="section-title" style="margin: 0 0 8px; border: none; padding: 0;">Birthday Thumbnail</label>
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <div id="m-bday-preview" style="width: 50px; height: 50px; background: var(--bg-main); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); overflow: hidden; border: 1px solid var(--border);">
                                <i class="bi bi-image"></i>
                            </div>
                            <div style="flex: 1;">
                                <input type="text" name="birthday_image" id="m-bday-img" class="form-control" style="margin-bottom: 5px;" placeholder="Image URL or upload...">
                                <input type="file" id="m-bday-upload" class="form-control" style="font-size: 0.75rem;" accept="image/*">
                            </div>
                        </div>
                        <small style="color: var(--text-muted); font-size: 0.7rem;">Optional image for automatic birthday greetings.</small>
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
