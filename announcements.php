<?php
// announcements.php - Student Attendance System Announcements
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Announcements | QR Tools</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .preview-bubble {
            background: var(--bg-main);
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: var(--shadow-neu-in-sm);
            margin-top: 1.5rem;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: var(--text-main);
            position: relative;
            max-width: 100%;
            word-wrap: break-word;
        }
        .preview-label {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            display: block;
        }
        .template-btn {
            background: var(--bg-card);
            border: none;
            padding: 1rem;
            border-radius: 12px;
            text-align: left;
            box-shadow: var(--shadow-neu-out-sm);
            transition: all 0.3s var(--ease-out-expo);
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            width: 100%;
            margin-bottom: 1rem;
        }
        .template-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-neu-out);
        }
        .template-btn i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        .template-btn div {
            display: flex;
            flex-direction: column;
        }
        .template-btn b {
            font-size: 0.9rem;
            color: var(--text-main);
        }
        .template-btn small {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        /* Progress Animation */
        .progress-frame {
            width: 100%;
            height: 8px;
            background: var(--bg-main);
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
            box-shadow: var(--shadow-neu-in-sm);
        }
        .progress-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary), #10b981);
            transition: width 0.4s ease;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="max-width: 900px; padding-top: 2.5rem;">
        <div class="animate-fade-up" style="margin-bottom: 2.5rem;">
            <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; letter-spacing: -0.05em;">Announcements</h1>
            <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500; margin:0;">Send direct notifications to the Telegram group.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
            
            <!-- Composer Card -->
            <div class="animate-fade-up">
                <div class="card" style="padding: 2.5rem; height: 100%;">
                    <h4 style="margin-bottom: 1.5rem; font-weight: 800; letter-spacing: -0.04em;">Custom Announcement</h4>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; display: block;">Message Content</label>
                        <textarea id="custom_message" class="form-control" style="height: 200px; resize: none; border-radius: 16px;" placeholder="Type your announcement here... HTML tags like <b>, <i>, <code> are supported."></textarea>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <span class="preview-label">Live Preview (Mockup)</span>
                        <div id="message_preview" class="preview-bubble">
                            <div id="preview_content"><i>Start typing to see preview...</i></div>
                            <div style="margin-top: 1rem; color: var(--text-muted); font-size: 0.85rem; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 0.5rem; font-family: monospace;">
                                -System Admin
                            </div>
                        </div>
                    </div>

                    <button onclick="sendAnnouncement('custom')" class="btn btn-primary hover-lift" style="width: 100%; padding: 1rem; border-radius: 50px; font-weight: 800;">
                        <i class="bi bi-send-fill"></i> Broadcast Now
                    </button>
                </div>
            </div>

            <!-- Templates Card -->
            <div class="animate-fade-up stagger-1">
                <div class="card" style="padding: 2.5rem; height: 100%;">
                    <h4 style="margin-bottom: 1.5rem; font-weight: 800; letter-spacing: -0.04em;">Default Options</h4>
                    
                    <button onclick="promptSetCount()" class="template-btn">
                        <i class="bi bi-people-fill"></i>
                        <div>
                            <b>Student Count Only</b>
                            <small>Broadcast regular count for SET A, B, or C</small>
                        </div>
                    </button>

                    <button onclick="promptNewStudent()" class="template-btn">
                        <i class="bi bi-person-plus-fill"></i>
                        <div>
                            <b>New Student Notice</b>
                            <small>Announce a new arrival</small>
                        </div>
                    </button>

                    <button onclick="triggerSystemRefresh()" class="template-btn" style="border: 1px solid rgba(var(--primary-rgb), 0.2); background: linear-gradient(145deg, var(--bg-card), rgba(var(--primary-rgb), 0.02));">
                        <i class="bi bi-arrow-repeat" style="color: #10b981;"></i>
                        <div>
                            <b>System Reset Notice</b>
                            <small>Notify TG of new subjects/SY reset</small>
                        </div>
                    </button>

                    <div style="margin-top: 2rem; padding: 1.5rem; background: rgba(92, 107, 192, 0.05); border-radius: 16px; border: 1px dashed var(--primary);">
                        <h5 style="font-size: 0.85rem; color: var(--primary); margin-bottom: 0.5rem;"><i class="bi bi-info-circle"></i> Admin Protocol</h5>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0; line-height: 1.4;">
                            All messages are queued and processed by the background worker. Messages include the official system footer automatically.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        const textarea = document.getElementById('custom_message');
        const preview = document.getElementById('preview_content');

        textarea.addEventListener('input', () => {
            const val = textarea.value.trim();
            if (val === '') {
                preview.innerHTML = '<i>Start typing to see preview...</i>';
            } else {
                // Simple HTML rendering for preview (just enough to see basics)
                // We convert newlines to <br> for the preview
                preview.innerHTML = val.replace(/\n/g, '<br>');
            }
        });

        async function sendAnnouncement(type, content = '') {
            if (type === 'custom') {
                content = textarea.value.trim();
                if (!content) {
                    Swal.fire('Error', 'Message cannot be empty.', 'error');
                    return;
                }
            }

            Swal.fire({
                title: 'Queueing Announcement...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const formData = new FormData();
                formData.append('type', type);
                formData.append('content', content);

                const response = await fetch('api/process_announcement.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();

                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    if (type === 'custom') {
                        textarea.value = '';
                        textarea.dispatchEvent(new Event('input'));
                    }
                } else {
                    throw new Error(res.message);
                }
            } catch (e) {
                Swal.fire('Error', e.message, 'error');
            }
        }

        async function promptSetCount() {
            const { value: set } = await Swal.fire({
                title: 'Select SET',
                input: 'select',
                inputOptions: {
                    'A': 'SET A',
                    'B': 'SET B',
                    'C': 'SET C'
                },
                inputPlaceholder: 'Choose a set',
                showCancelButton: true,
                confirmButtonText: 'Broadcast Count',
                confirmButtonColor: 'var(--primary)'
            });

            if (set) {
                sendAnnouncement('student_count', set);
            }
        }

        async function promptNewStudent() {
            const { value: name } = await Swal.fire({
                title: 'New Student Notice',
                input: 'text',
                inputLabel: 'Student Name (Optional)',
                inputPlaceholder: 'Leave blank for Unknown',
                showCancelButton: true,
                confirmButtonText: 'Broadcast Notice',
                confirmButtonColor: 'var(--primary)'
            });

            if (name !== undefined) {
                sendAnnouncement('new_student', name);
            }
        }

        async function triggerSystemRefresh() {
            const { isConfirmed } = await Swal.fire({
                title: 'System Refresh',
                text: 'Are you sure you want to announce a system-wide subject reset?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Reset & Announce',
                confirmButtonColor: '#10b981'
            });

            if (!isConfirmed) return;

            // Visual "Frame" Loading Animation
            Swal.fire({
                title: 'Refreshing System Data',
                html: `
                    <div style="text-align: left; font-size: 0.8rem; color: var(--text-muted);">
                        <div id="status-step">Initializing protocols...</div>
                        <div class="progress-frame">
                            <div id="progress-fill" class="progress-bar-fill"></div>
                        </div>
                    </div>
                `,
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    const fill = document.getElementById('progress-fill');
                    const status = document.getElementById('status-step');
                    
                    const steps = [
                        { p: '30%', t: 'Initializing protocols...' },
                        { p: '60%', t: 'Clearing subject cache...' },
                        { p: '100%', t: 'Finalizing updates...' }
                    ];

                    let currentStep = 0;
                    const interval = setInterval(() => {
                        if (currentStep < steps.length) {
                            fill.style.width = steps[currentStep].p;
                            status.innerText = steps[currentStep].t;
                            currentStep++;
                        } else {
                            clearInterval(interval);
                            sendAnnouncement('system_refresh');
                        }
                    }, 800);
                }
            });
        }
    </script>
</body>
</html>
