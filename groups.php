<?php
// groups.php - Group Randomizer
require 'includes/db.php';

// Fetch Students
$students = $pdo->query("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Define Navbar Actions
$navbar_actions = '
    <button id="saveBtn" onclick="saveGroup()" class="btn-icon" title="Save Layout" style="display: none;">
        <i class="bi bi-save" style="font-size: 0.95rem;"></i>
    </button>
    <button onclick="exportGroups()" class="btn-icon" title="Export Groups">
        <i class="bi bi-download" style="font-size: 0.95rem;"></i>
    </button>
    <button onclick="openParticipants()" class="btn-icon" title="Select Participants">
        <i class="bi bi-people" style="font-size: 0.95rem;"></i>
    </button>
    <button onclick="openSettings()" class="btn-icon" title="Group Settings">
        <i class="bi bi-gear" style="font-size: 0.95rem;"></i>
    </button>
    <button onclick="openLoadGroups()" class="btn-icon" title="Load Saved Groups">
        <i class="bi bi-folder2-open" style="font-size: 0.95rem;"></i>
    </button>
';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Randomizer | QR Tools</title>
    <link href="assets/css/style.css?v=1.3" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .group-card {
            background: var(--bg-card);
            border: none;
            border-radius: 16px;
            padding: 1.25rem;
            height: 100%;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-neu-out-sm);
        }
        .group-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-neu-out); }
        .group-header {
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.75rem;
            padding-bottom: 0.6rem;
            border-bottom: 2px solid color-mix(in srgb, var(--primary) 10%, transparent);
            display: flex; justify-content: space-between; align-items: center;
            text-transform: uppercase; letter-spacing: 0.06em; font-size: 0.72rem;
        }
        .group-header .badge {
            background: color-mix(in srgb, var(--primary) 10%, transparent);
            color: var(--primary);
            font-weight: 800;
            border: none;
            border-radius: 8px;
            padding: 3px 10px;
            font-size: 0.68rem;
        }
        .student-item {
            padding: 0.5rem 0.6rem;
            font-size: 0.82rem;
            color: var(--text-main);
            font-weight: 600;
            border-radius: 8px;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s;
        }
        .student-item:hover { background: var(--bg-main); }
        .student-item .item-num {
            width: 22px; height: 22px; border-radius: 6px;
            background: var(--bg-main); color: var(--text-muted);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.6rem; font-weight: 800; flex-shrink: 0;
            font-family: 'Outfit', sans-serif;
        }

        /* 🎰 Shuffler Roulette & Celebration Styles */
        .shuffler-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.25s ease-out;
        }
        .shuffler-overlay.active {
            opacity: 1;
            display: flex;
        }
        .shuffler-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-out-lg);
            border-radius: 20px;
            width: 90%;
            max-width: 420px;
            padding: 2.25rem;
            text-align: center;
            position: relative;
            transform: scale(0.95);
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }
        .shuffler-overlay.active .shuffler-box {
            transform: scale(1);
        }
        .shuffler-glow-circle {
            position: absolute;
            top: -60px; left: 50%; transform: translateX(-50%);
            width: 240px; height: 240px;
            background: radial-gradient(circle, color-mix(in srgb, var(--primary) 8%, transparent) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        .slot-window {
            height: 120px; /* Displays 3 items at 40px each */
            overflow: hidden;
            position: relative;
            background: var(--bg-main);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-neu-in-sm);
            border-radius: 14px;
            margin: 1.5rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 600px;
        }
        .slot-window::before, .slot-window::after {
            content: '';
            position: absolute; left: 0; width: 100%; height: 35px;
            z-index: 2;
            pointer-events: none;
        }
        .slot-window::before {
            top: 0;
            background: linear-gradient(to bottom, var(--bg-main) 15%, transparent 100%);
        }
        .slot-window::after {
            bottom: 0;
            background: linear-gradient(to top, var(--bg-main) 15%, transparent 100%);
        }
        .slot-ribbon {
            position: absolute;
            top: 40px; /* Align scrolling tape with center window item (40px offset) */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
            transform-style: preserve-3d;
            transition: transform 0.05s linear;
        }
        .slot-name {
            height: 40px; /* 40px item height */
            line-height: 40px;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--text-main);
            letter-spacing: -0.01em;
            text-transform: capitalize;
            white-space: nowrap;
            width: 100%;
            text-align: center;
            font-family: 'Outfit', sans-serif;
            transition: transform 0.08s ease, opacity 0.08s ease, filter 0.08s ease, color 0.1s ease;
            transform-origin: center center -40px;
            backface-visibility: hidden;
        }
        .slot-indicator {
            position: absolute;
            left: 0; right: 0;
            height: 40px;
            top: 40px; /* Center slot segment of the 120px window */
            border-top: 1.5px solid var(--primary);
            border-bottom: 1.5px solid var(--primary);
            background: color-mix(in srgb, var(--primary) 4%, transparent);
            pointer-events: none;
            z-index: 3;
        }
        
        #confettiCanvas {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            pointer-events: none;
            z-index: 10001;
            display: none;
        }

        /* 🖨️ Responsive Printable PDF grid styling */
        @media print {
            body {
                background: white !important;
                color: black !important;
                padding: 1cm !important;
                margin: 0 !important;
            }
            nav, .bottom-nav, .btn-icon, .btn, .modal-overlay, #saveBtn, .ip-access, .glass-panel, header, footer {
                display: none !important;
            }
            .container {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            #groupsContainer {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 20px !important;
                margin-top: 0 !important;
            }
            .group-card {
                background: white !important;
                border: 1.5px solid #000 !important;
                border-radius: 12px !important;
                box-shadow: none !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                padding: 1rem !important;
            }
            .group-header {
                color: #000 !important;
                border-bottom: 2px solid #000 !important;
                font-size: 0.8rem !important;
            }
            .group-header .badge {
                border: 1px solid #000 !important;
                color: #000 !important;
                background: transparent !important;
                font-weight: 800 !important;
            }
            .student-item {
                color: #000 !important;
                background: transparent !important;
            }
            .student-item .item-num {
                background: #f0f0f0 !important;
                color: #000 !important;
                border: 1px solid #ccc !important;
            }
        }
    </style>
</head>
<body>

    <!-- 🎰 Shuffler Roulette Overlay & Confetti Canvas -->
    <div id="shufflerOverlay" class="shuffler-overlay">
        <div class="shuffler-box">
            <div class="shuffler-glow-circle"></div>
            <div style="font-weight: 800; font-size: 0.72rem; text-transform: uppercase; color: var(--primary); letter-spacing: 0.1em; z-index: 1; position: relative;">Generating Groups</div>
            <h3 style="font-weight: 800; font-size: 1.5rem; color: var(--text-main); margin-top: 0.25rem; letter-spacing: -0.03em; z-index: 1; position: relative; font-family: 'Outfit', sans-serif;">Classmate Shuffler</h3>
            
            <div class="slot-window">
                <div class="slot-indicator"></div>
                <div id="slotRibbon" class="slot-ribbon">
                    <!-- Classmate names populated dynamically -->
                </div>
            </div>
            
            <div style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; z-index: 1; position: relative;">Shuffling classmates at random...</div>
        </div>
    </div>
    
    <canvas id="confettiCanvas"></canvas>

    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="padding-top: 3rem;">

        <!-- Result Area -->
        <div id="groupsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; margin-top: 1rem;">
             <div class="glass-panel flex-center" style="grid-column: 1 / -1; padding: 4rem 2rem; color: var(--text-muted); flex-direction: column; border-radius: 20px;">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: color-mix(in srgb, var(--primary) 8%, transparent); display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                    <i class="bi bi-people" style="font-size: 2rem; color: var(--primary); opacity: 0.5;"></i>
                </div>
                <h3 style="color: var(--text-main); margin-bottom: 0.35rem; font-weight: 800; font-size: 1.2rem; letter-spacing: -0.02em;">Ready to Randomize?</h3>
                <p style="margin-bottom: 1.5rem; font-size: 0.82rem;">Configure your grouping rules and click the button below.</p>
                <div style="display: flex; gap: 0.75rem;">
                    <button onclick="randomize()" class="btn btn-primary" style="padding: 0.7rem 1.75rem; border-radius: 50px; font-weight: 800; font-size: 0.85rem;">
                        <i class="bi bi-shuffle"></i> Randomize Now
                    </button>
                </div>
             </div>
        </div>

    </main>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal-overlay" onclick="if(event.target == this) closeSettings()">
        <div class="modal-body" style="max-width: 400px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h4 style="margin: 0; font-weight: 800; letter-spacing: -0.02em;">Generator Settings</h4>
                <button onclick="closeSettings()" style="background:none; border:none; font-size: 1.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:700; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Grouping Strategy</label>
                <select id="groupMode" class="form-control" onchange="updateLabel()">
                    <option value="count">Fixed Number of Groups</option>
                    <option value="size">Fixed Members per Group</option>
                </select>
            </div>

            <div style="margin-bottom: 2.5rem;">
                <label id="valueLabel" style="display:block; margin-bottom:0.5rem; font-weight:700; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Number of Groups</label>
                <input type="number" id="groupValue" class="form-control" value="4" min="1">
            </div>

            <button onclick="saveAndRandomize()" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem; border-radius: 12px; font-weight: 800;">
                Update & Regenerate
            </button>
        </div>
    </div>

    <!-- Load Groups Modal -->
    <div id="loadGroupsModal" class="modal-overlay" onclick="if(event.target == this) closeLoadGroups()">
        <div class="modal-body" style="max-width: 500px; max-height: 80vh;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h4 style="margin: 0; font-weight: 800; letter-spacing: -0.02em;">Saved Groups</h4>
                <button onclick="closeLoadGroups()" style="background:none; border:none; font-size: 1.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="savedGroupsList" style="display: flex; flex-direction: column; gap: 1rem;"></div>
        </div>
    </div>

    <!-- Participants Modal -->
    <div id="participantsModal" class="modal-overlay" onclick="if(event.target == this) closeParticipants()">
        <div class="modal-body" style="max-width: 450px; max-height: 85vh; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-shrink: 0;">
                <h4 style="margin: 0; font-weight: 800; letter-spacing: -0.02em;">Manage Participants <span id="participantCount" class="badge" style="background: var(--bg-main); color: var(--primary); font-size: 0.8rem; margin-left: 8px;"></span></h4>
                <button onclick="closeParticipants()" style="background:none; border:none; font-size: 1.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            <div style="margin-bottom: 1rem; flex-shrink: 0;">
                <input type="text" id="participantSearch" class="form-control" placeholder="Search students..." onkeyup="filterParticipants()">
            </div>
            <div id="participantsList" style="overflow-y: auto; flex-grow: 1; padding-right: 5px; margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 4px;">
                <!-- Filled via JS -->
            </div>
            <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                <button onclick="selectAll(true)" class="btn btn-ghost" style="flex: 1; font-weight: 700; border-radius: 8px; font-size: 0.8rem;">Select All</button>
                <button onclick="selectAll(false)" class="btn btn-ghost" style="flex: 1; font-weight: 700; border-radius: 8px; font-size: 0.8rem;">Deselect All</button>
            </div>
            <button onclick="saveAndRandomizeParticipants()" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem; border-radius: 12px; font-weight: 800; margin-top: 1rem; flex-shrink: 0;">
                Save & Regenerate
            </button>
        </div>
    </div>

    <script>
        window.addEventListener('error', function(e) {
            const file = e.filename ? e.filename.split('/').pop() : 'unknown';
            console.error("Global JS Error: ", e.message, " in ", file, " at ", e.lineno);
            Swal.fire({
                icon: 'error',
                title: 'Application Error',
                text: `${e.message} in [${file}:${e.lineno}]`,
                confirmButtonColor: 'var(--primary)'
            });
        });

        const allClassmates = <?= json_encode(array_column($students, 'name')) ?>;
        let activeClassmates = [...allClassmates];
        
        function openSettings() { document.getElementById('settingsModal').style.display = 'flex'; }
        function closeSettings() { document.getElementById('settingsModal').style.display = 'none'; }
        
        // Participants Modal Logic
        function openParticipants() { 
            document.getElementById('participantsModal').style.display = 'flex'; 
            renderParticipants();
        }
        function closeParticipants() { document.getElementById('participantsModal').style.display = 'none'; }
        
        function renderParticipants() {
            const list = document.getElementById('participantsList');
            const search = document.getElementById('participantSearch').value.toLowerCase();
            list.innerHTML = '';
            
            allClassmates.forEach(s => {
                if (s.toLowerCase().includes(search)) {
                    const isChecked = activeClassmates.includes(s);
                    list.innerHTML += `
                        <label style="display: flex; align-items: center; gap: 10px; padding: 0.6rem 0.8rem; background: var(--bg-main); border-radius: 8px; cursor: pointer; transition: 0.15s; margin:0;">
                            <input type="checkbox" value="${s}" class="participant-checkbox" ${isChecked ? 'checked' : ''} style="width: 1.1rem; height: 1.1rem; accent-color: var(--primary); cursor: pointer;">
                            <span style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);">${s}</span>
                        </label>
                    `;
                }
            });
            updateParticipantCount();
            
            // Add change listener to update count immediately
            document.querySelectorAll('.participant-checkbox').forEach(cb => {
                cb.addEventListener('change', updateParticipantCount);
            });
        }
        
        function filterParticipants() {
            renderParticipants();
        }
        
        function updateParticipantCount() {
            document.querySelectorAll('.participant-checkbox').forEach(cb => {
                if (cb.checked && !activeClassmates.includes(cb.value)) activeClassmates.push(cb.value);
                if (!cb.checked && activeClassmates.includes(cb.value)) activeClassmates = activeClassmates.filter(s => s !== cb.value);
            });
            document.getElementById('participantCount').innerText = activeClassmates.length + ' / ' + allClassmates.length;
        }

        function selectAll(check) {
            if (check) {
                activeClassmates = [...allClassmates];
            } else {
                activeClassmates = [];
            }
            renderParticipants();
        }

        function saveAndRandomizeParticipants() {
            closeParticipants();
            randomize();
        }

        function updateLabel() {
            const mode = document.getElementById('groupMode').value;
            document.getElementById('valueLabel').innerText = mode === 'count' ? "Number of Groups" : "Members per Group";
        }

        function saveAndRandomize() {
            closeSettings();
            randomize();
        }

        // 🎰 synthesized Sound FX
        let audioCtx = null;
        function playTickSound(frequency = 700, duration = 0.015) {
            try {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
                const osc = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(frequency, audioCtx.currentTime);
                
                gainNode.gain.setValueAtTime(0.04, audioCtx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);
                
                osc.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                osc.start();
                osc.stop(audioCtx.currentTime + duration);
            } catch(e) {
                console.error(e);
            }
        }

        function playSuccessSound() {
            try {
                if (!audioCtx) {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
                const now = audioCtx.currentTime;
                
                const notes = [261.63, 329.63, 392.00, 523.25]; // C4, E4, G4, C5 arpeggio
                notes.forEach((freq, idx) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, now + idx * 0.08);
                    
                    gain.gain.setValueAtTime(0, now + idx * 0.08);
                    gain.gain.linearRampToValueAtTime(0.06, now + idx * 0.08 + 0.04);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.08 + 0.3);
                    
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    
                    osc.start(now + idx * 0.08);
                    osc.stop(now + idx * 0.08 + 0.35);
                });
            } catch(e) {
                console.error(e);
            }
        }

        // 🎇 Confetti Canvas Burst Implementation
        const canvas = document.getElementById('confettiCanvas');
        const ctx = canvas.getContext('2d');
        let confetti = [];
        let confettiActive = false;
        
        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        
        class ConfettiParticle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height - canvas.height;
                this.r = Math.random() * 6 + 4;
                this.d = Math.random() * canvas.height;
                this.color = `hsl(${Math.random() * 360}, 90%, 60%)`;
                this.tilt = Math.random() * 10 - 5;
                this.tiltAngleChan = Math.random() * 0.05 + 0.02;
                this.tiltAngle = 0;
            }
            update() {
                this.y += Math.random() * 2 + 3;
                this.x += Math.sin(this.tiltAngle) * 0.6;
                this.tiltAngle += this.tiltAngleChan;
                this.tilt = Math.sin(this.tiltAngle - 0.5) * 6;
            }
        }
        
        function setupConfetti() {
            confetti = [];
            for (let i = 0; i < 150; i++) {
                confetti.push(new ConfettiParticle());
            }
        }
        
        function drawConfetti() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let remaining = 0;
            confetti.forEach(p => {
                p.update();
                if (p.y < canvas.height) {
                    remaining++;
                    ctx.beginPath();
                    ctx.lineWidth = p.r;
                    ctx.strokeStyle = p.color;
                    ctx.moveTo(p.x + p.tilt + p.r / 2, p.y);
                    ctx.lineTo(p.x + p.tilt, p.y + p.tilt + p.r / 2);
                    ctx.stroke();
                }
            });
            
            if (remaining > 0 && confettiActive) {
                requestAnimationFrame(drawConfetti);
            } else {
                canvas.style.display = 'none';
                confettiActive = false;
            }
        }
        
        function triggerConfetti() {
            resizeCanvas();
            setupConfetti();
            canvas.style.display = 'block';
            confettiActive = true;
            drawConfetti();
        }

        let nextPreCalculatedGroups = null;
        let isRandomizing = false;

        // 🎰 Slot-Machine Shuffler Animations (Extremely Realistic Mechanics per Group)
        function randomize() {
            if (isRandomizing) return;
            if (activeClassmates.length === 0) {
                Swal.fire('No Classmates', 'Please select at least one participant.', 'warning');
                return;
            }

            isRandomizing = true;
            confettiActive = false;
            canvas.style.display = 'none';

            // Disable buttons during randomizing
            const randomizeBtn = document.querySelector('button[onclick="randomize()"]');
            if (randomizeBtn) {
                randomizeBtn.disabled = true;
                randomizeBtn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Randomizing...';
            }

            // 1. Pre-calculate the groups instantly
            const mode = document.getElementById('groupMode').value;
            const val = parseInt(document.getElementById('groupValue').value) || 1;
            let shuffled = [...activeClassmates];
            for (let i = shuffled.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
            }

            let groups = [];
            if (mode === 'count') {
                const numGroups = Math.max(1, val);
                for(let i=0; i<numGroups; i++) groups.push([]);
                shuffled.forEach((student, index) => groups[index % numGroups].push(student));
            } else {
                const size = Math.max(1, val);
                let currentGroup = [];
                shuffled.forEach(student => {
                    if(currentGroup.length >= size) { groups.push(currentGroup); currentGroup = []; }
                    currentGroup.push(student);
                });
                if(currentGroup.length > 0) groups.push(currentGroup);
            }

            // Save groups globally
            nextPreCalculatedGroups = groups.filter(g => g.length > 0);

            if (nextPreCalculatedGroups.length === 0) {
                Swal.fire('No Groups', 'Could not form any groups.', 'warning');
                isRandomizing = false;
                if (randomizeBtn) {
                    randomizeBtn.disabled = false;
                    randomizeBtn.innerHTML = '<i class="bi bi-shuffle"></i> Randomize Now';
                }
                return;
            }

            const container = document.getElementById('groupsContainer');
            container.innerHTML = '';
            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn) saveBtn.style.display = 'none';

            // Start group-by-group reveal sequence
            revealGroupSequence(0);
        }

        function revealGroupSequence(groupIdx) {
            const groups = nextPreCalculatedGroups || [];
            if (groupIdx >= groups.length) {
                // All groups revealed! Lock in save layout button and final celebration
                isRandomizing = false;
                const randomizeBtn = document.querySelector('button[onclick="randomize()"]');
                if (randomizeBtn) {
                    randomizeBtn.disabled = false;
                    randomizeBtn.innerHTML = '<i class="bi bi-shuffle"></i> Randomize Now';
                }
                const saveBtn = document.getElementById('saveBtn');
                if (saveBtn) saveBtn.style.display = 'inline-block';
                triggerConfetti();
                playSuccessSound();
                return;
            }

            const group = groups[groupIdx];
            const targetName = group[0]; // The group leader

            const container = document.getElementById('groupsContainer');
            const cardHtml = `
                <div id="group-card-${groupIdx}" class="group-card interactive-glow" style="border: 1px solid var(--border); opacity: 0; transform: translateY(20px); transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);">
                    <div class="group-header">
                        <span>Group ${groupIdx + 1}</span>
                        <span id="group-badge-${groupIdx}" class="badge" style="background: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--primary); font-weight:800; border:none; border-radius:8px; padding:3px 10px; font-size:0.68rem;">0 Members</span>
                    </div>
                    <div id="group-body-${groupIdx}" style="position: relative; min-height: 80px;">
                        <!-- Inline Card Slot Machine Cabinet -->
                        <div id="group-slot-box-${groupIdx}" style="margin: 0.5rem 0; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); background: var(--bg-main); height: 80px; position: relative; display: flex; align-items: center; justify-content: center; perspective: 400px; box-shadow: var(--shadow-neu-in-sm);">
                            <div style="position: absolute; left: 0; right: 0; height: 32px; top: 24px; border-top: 1.5px solid var(--primary); border-bottom: 1.5px solid var(--primary); background: color-mix(in srgb, var(--primary) 3%, transparent); z-index: 3; pointer-events: none;"></div>
                            <div id="group-slot-ribbon-${groupIdx}" style="position: absolute; top: 24px; display: flex; flex-direction: column; align-items: center; width: 100%; transition: transform 0.05s linear; transform-style: preserve-3d;">
                                <!-- Reel names -->
                            </div>
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 20px; background: linear-gradient(to bottom, var(--bg-main) 15%, transparent); z-index: 2; pointer-events: none;"></div>
                            <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 20px; background: linear-gradient(to top, var(--bg-main) 15%, transparent); z-index: 2; pointer-events: none;"></div>
                        </div>
                        <div id="group-members-${groupIdx}" style="display: none; flex-direction: column; gap: 2px;"></div>
                    </div>
                </div>
            `;

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = cardHtml.trim();
            const cardNode = tempDiv.firstElementChild;
            if (!cardNode) {
                console.error("Failed to parse cardNode for groupIdx: " + groupIdx);
                return;
            }
            container.appendChild(cardNode);

            // Trigger card entry transition
            setTimeout(() => {
                cardNode.style.opacity = '1';
                cardNode.style.transform = 'translateY(0)';
            }, 20);

            playTickSound(450, 0.025); // Deep mechanical click sound for card load

            // 🎰 Build shuffler reel inside card
            const ribbon = document.getElementById(`group-slot-ribbon-${groupIdx}`);
            
            // Build continuous scrolling tape of names using activeClassmates
            let uniqueNames = [...activeClassmates];
            // Shuffle uniqueNames to make tape order random, but ensure targetName is inside
            for (let i = uniqueNames.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [uniqueNames[i], uniqueNames[j]] = [uniqueNames[j], uniqueNames[i]];
            }

            const targetIdxInUnique = uniqueNames.indexOf(targetName);
            
            // Repeat uniqueNames 4 times to build the reel tape
            let spinNames = [];
            for (let k = 0; k < 4; k++) {
                spinNames = spinNames.concat(uniqueNames);
            }
            
            // The target index in the combined spinNames list
            const targetSpinIndex = uniqueNames.length * 2 + targetIdxInUnique;
            
            // Populate ribbon tape (32px item height in mini-slot)
            ribbon.innerHTML = spinNames.map(name => `
                <div class="slot-name-mini" style="height: 32px; line-height: 32px; font-size: 0.95rem; font-weight: 600; color: var(--text-main); text-transform: capitalize; white-space: nowrap; width: 100%; text-align: center; font-family: 'Outfit', sans-serif; transition: transform 0.08s ease, opacity 0.08s ease, filter 0.08s ease, color 0.1s ease; transform-origin: center center -32px; backface-visibility: hidden;">
                    ${name.replace(/^\d+\s+/, '')}
                </div>
            `).join('');

            const duration = 1600; // Snappy 1.6 seconds shuffles per group
            const startTime = performance.now();
            const slotHeight = 32; // 32px item height
            const maxScroll = targetSpinIndex * slotHeight; // Land EXACTLY centered on target classmate name!
            
            // Easing function: slow start -> constant fast spin -> sharp springy deceleration & snap
            function easeSlot(t) {
                if (t < 0.25) {
                    return Math.pow(t / 0.25, 2) * 0.12;
                } else if (t < 0.55) {
                    return 0.12 + ((t - 0.25) / 0.3) * 0.48;
                } else {
                    const decelProgress = (t - 0.55) / 0.45;
                    const c1 = 1.25; // spring back wobble coefficient
                    const c3 = c1 + 1;
                    const easeBack = 1 + c3 * Math.pow(decelProgress - 1, 3) + c1 * Math.pow(decelProgress - 1, 2);
                    return 0.60 + easeBack * 0.40;
                }
            }
            
            let lastTickIndex = -1;
            
            function animateCardSlot(now) {
                const elapsed = now - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const ease = easeSlot(progress);
                const currentY = ease * maxScroll;
                
                ribbon.style.transform = `translateY(-${currentY}px) translateZ(0)`;
                
                // Real-time slanting, scaling, blurring, and active-theme highlights
                const centerOffset = currentY;
                const itemElms = ribbon.querySelectorAll('.slot-name-mini');
                itemElms.forEach((elm, index) => {
                    const itemCenter = index * slotHeight;
                    const dist = Math.abs(itemCenter - centerOffset);
                    const ratio = Math.max(0, 1 - dist / 64);
                    
                    const scale = 0.85 + (ratio * 0.15); // 0.85x to 1.0x
                    const rotateX = (itemCenter - centerOffset) * -0.6; // Cylindrical cylinder slanting
                    const opacity = 0.25 + (ratio * 0.75); // Fades edge items
                    const blur = Math.max(0, 1.0 - (ratio * 1.5));
                    
                    elm.style.transform = `scale(${scale}) rotateX(${rotateX}deg) translateZ(5px)`;
                    elm.style.opacity = opacity;
                    elm.style.filter = blur > 0.08 ? `blur(${blur}px)` : 'none';
                    
                    if (ratio > 0.85) {
                        elm.style.color = 'var(--primary)';
                        elm.style.fontWeight = '800';
                    } else {
                        elm.style.color = 'var(--text-main)';
                        elm.style.fontWeight = '600';
                    }
                });
                
                const currentTickIndex = Math.floor(currentY / slotHeight);
                if (currentTickIndex !== lastTickIndex) {
                    lastTickIndex = currentTickIndex;
                    const pitch = 850 - (progress * 300) + (groupIdx * 30); // Dynamic pitch per group!
                    playTickSound(pitch, 0.012);
                }
                
                if (progress < 1) {
                    requestAnimationFrame(animateCardSlot);
                } else {
                    // Snap complete!
                    setTimeout(() => {
                        // Success chime for group leader
                        playTickSound(1200, 0.08); // Sweet high chime
                        
                        // Card flash glow transition
                        const card = document.getElementById(`group-card-${groupIdx}`);
                        if (card) {
                            card.style.boxShadow = '0 0 20px color-mix(in srgb, var(--primary) 30%, transparent)';
                            setTimeout(() => {
                                if (card) card.style.boxShadow = 'var(--shadow-neu-out)';
                            }, 300);
                        }

                        // Fade out slot box and render list
                        const slotBox = document.getElementById(`group-slot-box-${groupIdx}`);
                        const membersDiv = document.getElementById(`group-members-${groupIdx}`);
                        
                        if (slotBox) {
                            slotBox.style.transition = 'opacity 0.25s ease-out';
                            slotBox.style.opacity = '0';
                        }
                        
                        setTimeout(() => {
                            if (slotBox) slotBox.style.display = 'none';
                            if (membersDiv) membersDiv.style.display = 'flex';
                            
                            // Pop members in sequentially
                            revealMembersOfGroup(groupIdx, 0);
                        }, 250);
                    }, 400);
                }
            }
            
            // Start the card slot shuffler animation reel
            setTimeout(() => {
                requestAnimationFrame(animateCardSlot);
            }, 300);
        }

        function revealMembersOfGroup(groupIdx, memberIdx) {
            const groups = nextPreCalculatedGroups || [];
            const group = groups[groupIdx];
            
            if (memberIdx >= group.length) {
                // Done with this group, wait briefly and trigger next group reveal!
                setTimeout(() => {
                    revealGroupSequence(groupIdx + 1);
                }, 400);
                return;
            }
            
            const memberName = group[memberIdx];
            const membersDiv = document.getElementById(`group-members-${groupIdx}`);
            const badge = document.getElementById(`group-badge-${groupIdx}`);
            
            const memberHtml = `
                <div class="student-item" style="opacity: 0; transform: scale(0.6); transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);">
                    <span class="item-num">${memberIdx + 1}</span>
                    <span class="student-name">${memberName.replace(/^\d+\s+/, '')}</span>
                </div>
            `;
            
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = memberHtml.trim();
            const memberNode = tempDiv.firstElementChild;
            if (!memberNode) {
                console.error("Failed to parse memberNode for classmate: " + memberName);
                return;
            }
            if (membersDiv) membersDiv.appendChild(memberNode);
            
            // Update badge text
            if (badge) badge.innerText = `${memberIdx + 1} Member${memberIdx > 0 ? 's' : ''}`;
            
            // Bouncy pop transition
            setTimeout(() => {
                memberNode.style.opacity = '1';
                memberNode.style.transform = 'scale(1)';
            }, 20);
            
            playTickSound(880 + (memberIdx * 20), 0.012); // Ticking sound per member
            
            // Delay next member reveal
            setTimeout(() => {
                revealMembersOfGroup(groupIdx, memberIdx + 1);
            }, 180);
        }
        
        function saveGroup() {
            Swal.fire({ title: 'Save Layout', input: 'text', inputLabel: 'Batch Name', showCancelButton: true })
            .then((result) => {
                if (result.isConfirmed && result.value) {
                    const cards = document.querySelectorAll('.group-card');
                    let currentGroups = [];
                    cards.forEach(card => {
                        let members = [];
                        card.querySelectorAll('.student-item').forEach(s => {
                            const nameEl = s.querySelector('.student-name');
                            members.push(nameEl ? nameEl.innerText : s.innerText);
                        });
                        currentGroups.push(members);
                    });
                    fetch('api/groups_process.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'save_group', name: result.value, members: JSON.stringify(currentGroups) })
                    })
                    .then(r => r.json()).then(data => Swal.fire({ icon:'success', title:'Saved', text:data.message, toast:true, position:'top-end', showConfirmButton:false, timer:3000 }));
                }
            });
        }

        function openLoadGroups() {
            document.getElementById('loadGroupsModal').style.display = 'flex';
            fetch('api/groups_process.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action: 'load_groups' })
            })
            .then(r => r.json()).then(res => {
                const list = document.getElementById('savedGroupsList');
                list.innerHTML = '';
                if(res.data.length === 0) { list.innerHTML = '<p style="text-align:center; color: var(--text-muted); padding:3rem;">No saved groups yet.</p>'; return; }
                res.data.forEach(g => {
                    let btn = document.createElement('div');
                    btn.className = 'card'; btn.style.padding = '1.25rem'; btn.style.cursor = 'pointer'; btn.style.transition = 'all 0.2s';
                    btn.innerHTML = `<div style="display:flex; justify-content:space-between; align-items:center;">
                        <div><strong style="display:block; font-size:1rem;">${g.name}</strong><small style="color:var(--text-muted);">${new Date(g.created_at).toLocaleDateString()}</small></div>
                        <button onclick="deleteGroup(event, ${g.id})" class="btn btn-ghost" style="border:none; color:var(--danger);"><i class="bi bi-trash"></i></button>
                    </div>`;
                    btn.onmouseover = () => btn.style.borderColor = 'var(--primary)'; btn.onmouseout = () => btn.style.borderColor = 'var(--border)';
                    btn.onclick = (e) => { if(e.target.tagName !== 'I' && e.target.tagName !== 'BUTTON') loadGroupIntoView(g.members); };
                    list.appendChild(btn);
                });
            });
        }
        function closeLoadGroups() { document.getElementById('loadGroupsModal').style.display = 'none'; }
        
        function deleteGroup(e, id) {
            e.stopPropagation();
            Swal.fire({ 
                title: 'Delete Group Layout?', 
                text: "This action cannot be undone.", 
                icon: 'warning', 
                showCancelButton: true, 
                confirmButtonColor: '#ef4444', 
                cancelButtonColor: '#64748b', 
                confirmButtonText: 'Yes, delete' 
            }).then((result) => { 
                if (result.isConfirmed) { 
                    fetch('api/groups_process.php', { 
                        method: 'POST', 
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                        body: new URLSearchParams({ action: 'delete_group', id: id }) 
                    }).then(r => r.json()).then(data => {
                        if(data.status === 'success') {
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'The group layout has been removed.',
                                icon: 'success',
                                confirmButtonColor: 'var(--primary)'
                            }).then(() => openLoadGroups());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    }); 
                } 
            });
        }

        function loadGroupIntoView(jsonMembers) {
            const groups = JSON.parse(jsonMembers); closeLoadGroups();
            const container = document.getElementById('groupsContainer'); container.innerHTML = '';
            groups.forEach((group, i) => {
                let html = `<div class="group-card interactive-glow animate-fade-up" style="animation-delay: ${i * 0.05}s; border: 1px solid var(--border);"><div class="group-header"><span>Group ${i + 1}</span><span class="badge" style="background: var(--bg-main); color:var(--primary); font-weight:800; border:none; border-radius:8px; padding:3px 10px; font-size:0.68rem;">${group.length} Members</span></div><div>`;
                group.forEach((s, idx) => html += `<div class="student-item"><span class="item-num">${idx+1}</span><span class="student-name">${s.replace(/^\d+\s+/, '')}</span></div>`);
                html += `</div></div>`;
                container.innerHTML += html;
            });
            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn) saveBtn.style.display = 'inline-block';
        }

        function exportGroups() {
            const cards = document.querySelectorAll('.group-card');
            if(cards.length === 0) { 
                Swal.fire('No Layout Active', 'Please randomize classmates or load a layout first.', 'info'); 
                return; 
            }
            
            Swal.fire({
                title: 'Export Group Layout',
                text: 'Select your preferred export format below:',
                icon: 'question',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonColor: 'var(--primary)',
                denyButtonColor: 'var(--success)',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="bi bi-file-earmark-excel" style="margin-right: 6px;"></i> Styled Excel Sheet',
                denyButtonText: '<i class="bi bi-file-earmark-pdf" style="margin-right: 6px;"></i> PDF / Print Sheet',
                cancelButtonText: '<i class="bi bi-file-earmark-text" style="margin-right: 6px;"></i> Plain Text (TXT)'
            }).then((result) => {
                if (result.isConfirmed) {
                    let excelHtml = `
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Classmate Groups</x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  table { border-collapse: collapse; }
  td, th { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 10pt; vertical-align: middle; }
  .title-main { font-size: 16pt; font-weight: bold; color: #4f46e5; height: 40px; text-align: center; border-bottom: 1.5pt solid #4f46e5; }
  .title-sub { font-size: 9pt; color: #64748b; font-style: italic; height: 24px; text-align: center; }
  .header { background-color: #4f46e5; color: #ffffff; font-weight: bold; text-align: center; font-size: 11pt; height: 30px; border: 0.5pt solid #cbd5e1; }
  .group-header-row { height: 30px; }
  .group-title { background-color: #e0e7ff; color: #4338ca; font-weight: bold; font-size: 11pt; border: 0.5pt solid #cbd5e1; padding-left: 10px; text-align: left; }
  .item-row { height: 24px; }
  .item-num { text-align: center; color: #64748b; font-weight: bold; background-color: #f8fafc; border: 0.5pt solid #cbd5e1; }
  .item-name { padding-left: 12px; font-weight: 600; color: #0f172a; border: 0.5pt solid #cbd5e1; text-align: left; }
  .item-row-alt .item-name { background-color: #f8fafc; }
</style>
</head>
<body>
  <table style="width: 400px;">
    <tr><td colspan="2" class="title-main" style="border:none; border-bottom:1.5pt solid #4f46e5;">Classmate Group Layout</td></tr>
    <tr><td colspan="2" class="title-sub" style="border:none;">Generated on ${new Date().toLocaleString()} | Total Groups: ${cards.length}</td></tr>
    <tr><td colspan="2" style="height: 12px; border:none;"></td></tr>
    <tr>
      <th class="header" style="width: 80px;">Position</th>
      <th class="header" style="width: 320px;">Classmate Name</th>
    </tr>
`;

                    cards.forEach((card, cardIdx) => {
                        let groupName = card.querySelector('.group-header').firstElementChild.innerText;
                        groupName = groupName.replace(/\s*\(.*\)/, '').replace(/\s*\d+\s*members?/i, '').replace(/\s*\d+\s*classmates?/i, '').trim();
                        
                        excelHtml += `
    <tr class="group-header-row">
      <td colspan="2" class="group-title">${groupName.toUpperCase()}</td>
    </tr>
`;

                        card.querySelectorAll('.student-item').forEach((s, idx) => {
                            const pos = s.querySelector('.item-num').innerText;
                            const name = s.querySelector('.student-name').innerText;
                            const isAlt = idx % 2 === 1 ? ' item-row-alt' : '';
                            
                            excelHtml += `
    <tr class="item-row${isAlt}">
      <td class="item-num">${pos}</td>
      <td class="item-name">${name}</td>
    </tr>
`;
                        });
                    });

                    excelHtml += `
  </table>
</body>
</html>
`;
                    const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                    const anchor = document.createElement('a'); 
                    anchor.download = 'groups_export_' + new Date().toISOString().slice(0,10) + '.xls'; 
                    anchor.href = window.URL.createObjectURL(blob); 
                    anchor.click();
                    
                    Swal.fire({ icon:'success', title:'Excel Styled Sheet Exported', toast:true, position:'top-end', showConfirmButton:false, timer:2500 });
                } else if (result.isDenied) {
                    window.print();
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    let text = "Groups Export - " + new Date().toLocaleString() + "\n\n";
                    cards.forEach(card => {
                        text += `--- ${card.querySelector('.group-header span').innerText} ---\n`;
                        card.querySelectorAll('.student-item').forEach(s => {
                            const nameEl = s.querySelector('.student-name');
                            text += `${nameEl ? nameEl.innerText : s.innerText}\n`;
                        });
                        text += "\n";
                    });
                    const blob = new Blob([text], { type: 'text/plain' });
                    const anchor = document.createElement('a'); 
                    anchor.download = 'groups_export_' + new Date().toISOString().slice(0,10) + '.txt'; 
                    anchor.href = window.URL.createObjectURL(blob); 
                    anchor.click();
                    
                    Swal.fire({ icon:'success', title:'TXT Exported', toast:true, position:'top-end', showConfirmButton:false, timer:2500 });
                }
            });
        }
    </script>
</body>
</html>
