<?php
// manual.php - Manual Entry
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';

// Fetch users
$stmt = $pdo->query("SELECT * FROM users ORDER BY name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Entry | QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .search-area {
            position: sticky;
            top: var(--header-height);
            z-index: 90;
            background: rgba(248, 250, 252, 0.9);
            backdrop-filter: blur(5px);
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .student-list {
            display: flex; flex-direction: column; gap: 0.75rem;
            padding-bottom: 4rem;
        }

        .student-row {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }
        
        .student-info h5 { margin: 0; font-size: 1rem; color: var(--text-main); }
        .student-info small { color: var(--text-muted); font-family: monospace; }
        
        .action-group { display: flex; gap: 0.5rem; }
        
        .btn-status {
            border: 1px solid var(--border);
            background: white;
            color: var(--text-muted);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; gap: 0.3rem;
        }
        .btn-status:hover { background: #f8fafc; }

        /* Active States */
        .student-row.present .btn-present { background: #dcfce7; border-color: #166534; color: #166534; }
        .student-row.late .btn-late { background: #ffedd5; border-color: #d97706; color: #d97706; }
        .student-row.absent .btn-absent { background: #fee2e2; border-color: #dc2626; color: #dc2626; }

        @media (max-width: 600px) {
            .student-row { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .action-group { width: 100%; justify-content: space-between; }
            .btn-status { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- Nav -->
    <nav class="navbar">
        <a href="index.php" class="btn btn-ghost" style="border: none; padding-left: 0;">
            <i class="bi bi-arrow-left"></i> <span class="d-none-mobile">Dashboard</span>
        </a>
        <h3 class="text-gradient">Manual Entry</h3>
        <div class="flex-center" style="gap: 10px;">
             <!-- Mode Switcher -->
             <div class="btn-group">
                <button onclick="setMode('daily')" id="btnDaily" class="btn btn-primary btn-sm">Daily</button>
                <button onclick="setMode('subject')" id="btnSubject" class="btn btn-ghost btn-sm">Subject</button>
             </div>
        </div>
    </nav>

    <!-- Subject Controls (Hidden by default) -->
    <div id="subjectControls" style="display:none; padding-top:1rem; padding-bottom:0;">
        <div class="card mobile-stack" style="padding:1rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
            <div style="flex:1;" class="mobile-full">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <label style="font-size:0.8rem; font-weight:bold; color:var(--text-muted); margin-bottom:0.2rem;">Select Subject</label>
                    <div id="subjectActions" style="display:none; font-size:0.8rem;">
                       <a href="#" onclick="editCurrentSubject()" style="color:var(--primary); margin-right:10px;">Edit</a>
                       <a href="#" onclick="manageSchedule()" style="color:var(--secondary); margin-right:10px;">Schedule</a>
                       <a href="#" onclick="deleteCurrentSubject()" style="color:var(--danger);">Delete</a>
                    </div>
                </div>
                <select id="subjectSelect" class="form-control" onchange="loadSubjectData()">
                    <option value="">Loading...</option>
                </select>
            </div>
            <div style="display:flex; gap:0.5rem; align-items:flex-end;" class="mobile-btn-grid">
                <button onclick="goToScan()" class="btn btn-success" title="Scan for this Subject" id="btnScanSubject" style="display:none;"><i class="bi bi-qr-code-scan"></i> Scan</button>
                <button onclick="openAddSubject()" class="btn btn-ghost" title="Add Subject"><i class="bi bi-plus-lg"></i></button>
                <button onclick="resetSubjectAttendance()" class="btn btn-ghost" title="Reset Today's Attendance" id="btnResetSubject" style="display:none; color:var(--danger); border-color:var(--danger);"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button onclick="markAllPresent()" class="btn btn-ghost" title="Mark All Present" id="btnMarkAll" style="display:none; color:var(--success); border-color:var(--success);"><i class="bi bi-check-all"></i></button>
                <button onclick="exportSubject()" class="btn btn-ghost" title="Export"><i class="bi bi-download"></i></button>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="search-area">
        <div class="container">
            <div style="position: relative;">
                <i class="bi bi-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search by name..." autocomplete="off" style="padding-left: 3rem;">
            </div>
        </div>
    </div>

    <main class="container">
        <div id="studentList" class="student-list animate-fade-up">
            <?php foreach ($users as $user): ?>
                <div class="student-row" id="row-<?= $user['qr_code'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>">
                    
                    <div class="student-info" onclick="window.location.href='profile.php?qr=<?= urlencode($user['qr_code']) ?>'" style="cursor: pointer;">
                        <h5 style="text-decoration: underline; text-decoration-color: var(--text-muted); text-underline-offset: 4px;"><?= htmlspecialchars($user['name']) ?></h5>
                        <small><?= $user['qr_code'] ?></small>
                    </div>

                    <div class="action-group">
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'present')" class="btn-status btn-present" title="Mark Present">
                            <i class="bi bi-check-circle"></i> Present
                        </button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'late')" class="btn-status btn-late" title="Mark Late">
                            <i class="bi bi-alarm"></i> Late
                        </button>
                        <button onclick="setStatus('<?= $user['qr_code'] ?>', '<?= $user['name'] ?>', 'absent')" class="btn-status btn-absent" title="Mark Absent">
                            <i class="bi bi-x-circle"></i> Absent
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        // Search
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.student-row').forEach(row => {
                const name = row.dataset.name.toLowerCase();
                row.style.display = name.includes(term) ? 'flex' : 'none';
            });
        });

        // SweetAlert Toast Mixin
        const Toast = Swal.mixin({
            toast: true, position: 'bottom-end', showConfirmButton: false,
            timer: 2000, timerProgressBar: true
        });

        // Mode Logic
        let currentMode = 'daily'; // 'daily' or 'subject'
        let currentSubjectId = null;

        function setMode(mode) {
            currentMode = mode;
            document.getElementById('btnDaily').className = mode === 'daily' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
            document.getElementById('btnSubject').className = mode === 'subject' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
            
            document.getElementById('subjectControls').style.display = mode === 'subject' ? 'block' : 'none';
            
            if(mode === 'subject') {
                loadSubjects();
            } else {
                currentSubjectId = null;
                // Ideally reload daily status here, but for simplicity we rely on manual toggle
            }
        }

        // Subject Logic
        function loadSubjects() {
            fetch('api/subject_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'get_subjects' })
            })
            .then(r => r.json())
            .then(res => {
                const sel = document.getElementById('subjectSelect');
                sel.innerHTML = '<option value="">-- Select Subject --</option>';
                
                if (res.data) {
                    for (const [sem, subjects] of Object.entries(res.data)) {
                        let optgroup = document.createElement('optgroup');
                        optgroup.label = sem;
                        subjects.forEach(s => {
                            let opt = document.createElement('option');
                            opt.value = s.id;
                            opt.innerText = s.name;
                            optgroup.appendChild(opt);
                        });
                        sel.appendChild(optgroup);
                    }
                }
            });
        }

        function openAddSubject() {
            Swal.fire({
                title: 'New Subject',
                html: `
                    <input id="swal-name" class="swal2-input" placeholder="Subject Name (e.g. Math 101)">
                    <input id="swal-sem" class="swal2-input" placeholder="Semester (e.g. 1st Sem 2025-2026)">
                `,
                showCancelButton: true,
                preConfirm: () => {
                    return [
                        document.getElementById('swal-name').value,
                        document.getElementById('swal-sem').value
                    ]
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const [name, sem] = result.value;
                    if(!name || !sem) return Swal.fire('Error', 'All fields required', 'error');
                    
                    fetch('api/subject_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'add_subject', name: name, semester: sem })
                    })
                    .then(r => r.json())
                    .then(d => {
                        if(d.status === 'success') {
                            Swal.fire('Success', 'Subject Added', 'success');
                            loadSubjects();
                        }
                    });
                }
            });
        }
        
        function loadSubjectData() {
            currentSubjectId = document.getElementById('subjectSelect').value;
            currentSubjectId = document.getElementById('subjectSelect').value;
            const btnMarkAll = document.getElementById('btnMarkAll');
            const btnReset = document.getElementById('btnResetSubject');
            const btnScan = document.getElementById('btnScanSubject');
            const subjectActions = document.getElementById('subjectActions');
            
            if (!currentSubjectId) {
                btnMarkAll.style.display = 'none';
                btnReset.style.display = 'none';
                btnScan.style.display = 'none';
                subjectActions.style.display = 'none';
                 // Reset UI
                document.querySelectorAll('.student-row').forEach(row => {
                    row.classList.remove('present', 'late', 'absent');
                });
                return;
            }
            
            btnMarkAll.style.display = 'inline-flex';
            btnReset.style.display = 'inline-flex';
            btnScan.style.display = 'inline-flex';
            subjectActions.style.display = 'block';

            // Fetch Realtime Status
            fetch('api/subject_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'get_subject_attendance', subject_id: currentSubjectId })
            })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    // Reset first
                    document.querySelectorAll('.student-row').forEach(row => {
                        row.classList.remove('present', 'late', 'absent');
                    });
                    
                    // Apply statuses
                    for (const [qr, status] of Object.entries(res.data)) {
                        const row = document.getElementById('row-' + qr);
                        if(row) row.classList.add(status);
                    }
                }
            });
        }

        function markAllPresent() {
             if(!currentSubjectId) return;
             Swal.fire({
                 title: 'Mark All Present?',
                 text: 'This will mark all remaining students as Present for this subject.',
                 icon: 'question',
                 showCancelButton: true,
                 confirmButtonText: 'Yes, Mark All'
             }).then((res) => {
                 if(res.isConfirmed) {
                     fetch('api/subject_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'mark_all', subject_id: currentSubjectId })
                    })
                    .then(r => r.json())
                    .then(d => {
                        Swal.fire('Done', d.message, 'success');
                        loadSubjectData(); // Refresh UI
                    });
                 }
             });
        }
        
        function resetSubjectAttendance() {
             if(!currentSubjectId) return;
             Swal.fire({
                 title: 'Reset Attendance?',
                 text: 'This will clear ALL attendance records for this subject for TODAY. Are you sure?',
                 icon: 'warning',
                 showCancelButton: true,
                 confirmButtonColor: '#d33',
                 confirmButtonText: 'Yes, Reset'
             }).then((res) => {
                 if(res.isConfirmed) {
                     fetch('api/subject_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'reset_attendance', subject_id: currentSubjectId })
                    })
                    .then(r => r.json())
                    .then(d => {
                        Swal.fire('Reset', d.message, 'success');
                        loadSubjectData(); // Refresh UI
                    });
                 }
             });
        }
        
        function editCurrentSubject() {
             if(!currentSubjectId) return;
             const sel = document.getElementById('subjectSelect');
             const name = sel.options[sel.selectedIndex].text;
             const sem = sel.options[sel.selectedIndex].parentNode.label; // optgroup label

             Swal.fire({
                title: 'Edit Subject',
                html: `
                    <input id="swal-edit-name" class="swal2-input" value="${name}">
                    <input id="swal-edit-sem" class="swal2-input" value="${sem}">
                `,
                showCancelButton: true,
                preConfirm: () => {
                    return [
                        document.getElementById('swal-edit-name').value,
                        document.getElementById('swal-edit-sem').value
                    ]
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const [n, s] = result.value;
                    fetch('api/subject_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'update_subject', id: currentSubjectId, name: n, semester: s })
                    })
                    .then(r => r.json())
                    .then(d => {
                        if(d.status === 'success') {
                            Swal.fire('Updated', 'Subject updated', 'success');
                            loadSubjects(); 
                        }
                    });
                }
            });
        }

        function deleteCurrentSubject() {
            if(!currentSubjectId) return;
            Swal.fire({
                title: 'Delete Subject?',
                text: 'This will delete the subject and ALL its attendance records forever.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, Delete it'
            }).then((res) => {
                if(res.isConfirmed) {
                     fetch('api/subject_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'delete_subject', id: currentSubjectId })
                    })
                    .then(r => r.json())
                    .then(d => {
                         Swal.fire('Deleted', 'Subject deleted', 'success');
                         loadSubjects();
                         currentSubjectId = null;
                         loadSubjectData();
                    });
                }
            });
        }
        
        function manageSchedule() {
            if(!currentSubjectId) return;

            // 1. Fetch current schedule
            fetch('api/subject_process.php', {
                method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'get_schedules', subject_id: currentSubjectId })
            })
            .then(r => r.json())
            .then(res => {
                let html = '<div style="text-align:left; margin-bottom:1rem;">';
                if(res.data && res.data.length > 0) {
                     res.data.forEach(s => {
                         html += `
                            <div style="display:flex; justify-content:space-between; border-bottom:1px solid #eee; padding:5px;">
                                <span><b>${s.day_of_week}</b>: ${s.start_time} - ${s.end_time}</span>
                                <button onclick="deleteSchedule(${s.id})" class="btn btn-sm btn-ghost" style="color:red; padding:0 5px;"><i class="bi bi-trash"></i></button>
                            </div>
                         `;
                     });
                } else {
                    html += '<small>No schedule set.</small>';
                }
                html += '</div>';

                // Add Form
                html += `
                    <div style="border-top:1px solid #ccc; padding-top:10px;">
                        <select id="swal-sch-day" class="swal2-input" style="margin-bottom:10px;">
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                        <div style="display:flex; gap:10px;">
                            <input type="time" id="swal-sch-start" class="swal2-input" placeholder="Start">
                            <input type="time" id="swal-sch-end" class="swal2-input" placeholder="End">
                        </div>
                    </div>
                `;

                Swal.fire({
                    title: 'Manage Schedule',
                    html: html,
                    showCloseButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Add Schedule',
                    cancelButtonText: 'Close',
                    didOpen: () => {
                         // Hack to allow delete button clicks inside Swal
                         window.deleteSchedule = (id) => {
                             fetch('api/subject_process.php', {
                                method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: new URLSearchParams({ action: 'delete_schedule', id: id })
                            }).then(() => {
                                Swal.close();
                                setTimeout(manageSchedule, 100); // Re-open to refresh
                            });
                         };
                    },
                    preConfirm: () => {
                         return [
                            document.getElementById('swal-sch-day').value,
                            document.getElementById('swal-sch-start').value,
                            document.getElementById('swal-sch-end').value
                         ]
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const [day, start, end] = result.value;
                        if(!start || !end) return Swal.fire('Error', 'Times required', 'error');
                        
                        fetch('api/subject_process.php', {
                            method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({ 
                                action: 'add_schedule', 
                                subject_id: currentSubjectId,
                                day: day, start: start, end: end
                            })
                        }).then(r => r.json()).then(d => {
                            Swal.fire('Added', d.message, 'success').then(() => manageSchedule());
                        });
                    }
                });
            });
        }
        

        
        function goToScan() {
            if(!currentSubjectId) return;
            window.location.href = `scan.php?subject_id=${currentSubjectId}`;
        }
        
        function exportSubject() {
            if(!currentSubjectId) return Swal.fire('Wait', 'Select a subject first', 'info');
            Swal.fire({
                title: 'Export Subject Record',
                html: `
                    <div style="display:flex; justify-content:center; gap:20px; margin-bottom:15px;">
                        <label><input type="radio" name="ex-mode" value="single" checked onchange="toggleExMode()"> Single Day</label>
                        <label><input type="radio" name="ex-mode" value="range" onchange="toggleExMode()"> Date Range</label>
                    </div>
                    <div>
                        <input type="date" id="swal-s-start" class="swal2-input" value="<?= date('Y-m-d') ?>">
                        <div id="ex-end-div" style="display:none; margin-top:10px;">
                            <label style="font-size:0.8rem;">To</label><br>
                            <input type="date" id="swal-s-end" class="swal2-input" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                `,
                showCancelButton: true,
                didOpen: () => {
                     window.toggleExMode = () => {
                         const mode = document.querySelector('input[name="ex-mode"]:checked').value;
                         const endDiv = document.getElementById('ex-end-div');
                         endDiv.style.display = (mode === 'range') ? 'block' : 'none';
                     };
                }
            }).then((res) => {
                if(res.isConfirmed) {
                    const mode = document.querySelector('input[name="ex-mode"]:checked').value;
                    const start = document.getElementById('swal-s-start').value;
                    let end = start;
                    
                    if (mode === 'range') {
                        end = document.getElementById('swal-s-end').value;
                    }
                    
                    window.open(`api/export_subject.php?subject_id=${currentSubjectId}&start=${start}&end=${end}`, '_blank');
                }
            })
        }

        async function setStatus(qr, name, status) {
            
            if (currentMode === 'subject') {
                if (!currentSubjectId) {
                    Toast.fire({ icon: 'warning', title: 'Select a subject first!' });
                    return;
                }
                
                // Subject Mode Call
                 fetch('api/subject_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 
                        action: 'record_attendance', 
                        subject_id: currentSubjectId,
                        qr_code: qr, 
                        status: status
                    })
                })
                .then(r => r.json())
                .then(d => {

                     Toast.fire({ icon: 'success', title: `${name}: ${status.toUpperCase()} (Subject)` });
                     // Update UI immediately without full reload
                     const row = document.getElementById('row-' + qr);
                     row.classList.remove('present', 'late', 'absent');
                     row.classList.add(status);
                });
                return;
            }

            // Daily Mode (Original Logic)
            if (status === 'absent') {
               // Remarks logic removed
            }

            const row = document.getElementById('row-' + qr);
            row.style.opacity = '0.7';

            fetch('api/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ 
                    manual_entry: true, 
                    qr_code: qr, 
                    name: name, 
                    force_status: status
                })
            })
            .then(r => r.json())
            .then(data => {
                row.style.opacity = '1';
                // ... (rest of success logic)

                row.classList.remove('present', 'late', 'absent');

                if (data.status === 'success' || data.status === 'duplicate') {
                    // Update UI to match the status returned (or what we asked for if duplicate)
                    let finalStatus = data.attendance_status || status; // fallback if simplified API
                    
                    row.classList.add(finalStatus);
                    
                    // Specific message for successful change
                    if(data.status == 'success') {
                        Toast.fire({ icon: 'success', title: `${name}: ${finalStatus.toUpperCase()}` });
                    } else {
                        Toast.fire({ icon: 'info', title: `${name}: Already ${finalStatus.toUpperCase()}` });
                    }
                } else {
                    Toast.fire({ icon: 'error', title: data.message });
                }
            })
            .catch(() => {
                row.style.opacity = '1';
                Toast.fire({ icon: 'error', title: 'Network error' });
            });
        }
        
        // Auto-Detect Subject on Load
        document.addEventListener('DOMContentLoaded', () => {
             // Only auto-switch if we are in Subject Mode context or if we want to prompt user
             // For Manual Entry, let's auto-switch mode IF a class is ongoing? 
             // Or just auto-select the dropdown if mode is switched.
             
             // Let's hook into the setMode or valid loadSubjects promise.
             // But here we are in manual.php, let's check for class.
             fetch('api/subject_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'get_current_subject' })
            })
            .then(r => r.json())
            .then(d => {
                if(d.status === 'success') {
                     // Found a class!
                     Swal.fire({
                         title: 'Ongoing Class Detected',
                         text: `It's time for ${d.data.name}. Switch to Subject Mode?`,
                         icon: 'info',
                         showCancelButton: true,
                         confirmButtonText: 'Yes, Switch'
                     }).then((res) => {
                         if(res.isConfirmed) {
                             setMode('subject');
                             // Wait for dropdown to populate then select
                             setTimeout(() => {
                                 const sel = document.getElementById('subjectSelect');
                                 if(sel) {
                                     sel.value = d.data.id;
                                     loadSubjectData(); // Trigger change
                                 }
                             }, 500);
                         }
                     });
                }
            });
        });
    </script>
</body>
</html>