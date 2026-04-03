<?php
// groups.php - Group Randomizer
require 'includes/db.php';

// Fetch Students
$students = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Define Navbar Actions
$navbar_actions = '
    <div class="nav-actions">
        <button onclick="exportGroups()" class="btn btn-ghost btn-sm" style="border-radius:50px;" title="Export">
            <i class="bi bi-download"></i> <span class="hide-mobile">Export</span>
        </button>
        <button onclick="openSettings()" class="btn btn-ghost btn-sm" style="border-radius:50px;" title="Settings">
            <i class="bi bi-gear-fill"></i> <span class="hide-mobile">Setup</span>
        </button>
        <button onclick="openLoadGroups()" class="btn btn-ghost btn-sm" style="border-radius:50px;" title="Load">
            <i class="bi bi-folder2-open"></i> <span class="hide-mobile">Saved</span>
        </button>
    </div>
';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Randomizer | QR Tools</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/theme_loader.php'; ?>
    <style>
        .group-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            height: 100%;
            transition: transform 0.2s;
        }
        .group-card:hover { transform: translateY(-2px); }
        .group-header {
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.75rem;
            display: flex; justify-content: space-between; align-items: center;
            text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.85rem;
        }
        .student-item {
            padding: 0.5rem 0;
            font-size: 0.9rem;
            color: var(--text-main);
            border-bottom: 1px solid var(--border);
            font-weight: 500;
        }
        .student-item:last-child { border-bottom: none; }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <main class="container" style="padding-top: 3rem;">

        <!-- Result Area -->
        <div id="groupsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
             <div class="card flex-center" style="grid-column: 1 / -1; padding: 5rem 2rem; color: var(--text-muted); flex-direction: column; border-radius: 20px; border-style: dashed;">
                <i class="bi bi-people" style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.2;"></i>
                <h3 style="color: var(--text-muted); margin-bottom: 0.5rem;">Ready to Randomize?</h3>
                <p style="margin-bottom: 2rem;">Configure your grouping rules and click the button below.</p>
                <div style="display: flex; gap: 1rem;">
                    <button onclick="randomize()" class="btn btn-primary" style="padding: 0.8rem 2rem; border-radius: 50px; font-weight: 800;">
                        <i class="bi bi-shuffle"></i> Randomize Now
                    </button>
                    <button id="saveBtn" onclick="saveGroup()" class="btn btn-ghost" style="padding: 0.8rem 2rem; border-radius: 50px; font-weight: 800; display: none;">
                        <i class="bi bi-save"></i> Save Layout
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

    <script>
        const students = <?= json_encode(array_column($students, 'name')) ?>;
        
        function openSettings() { document.getElementById('settingsModal').style.display = 'flex'; }
        function closeSettings() { document.getElementById('settingsModal').style.display = 'none'; }
        
        function updateLabel() {
            const mode = document.getElementById('groupMode').value;
            document.getElementById('valueLabel').innerText = mode === 'count' ? "Number of Groups" : "Members per Group";
        }

        function saveAndRandomize() {
            closeSettings();
            randomize();
        }

        function randomize() {
            const mode = document.getElementById('groupMode').value;
            const val = parseInt(document.getElementById('groupValue').value) || 1;
            let shuffled = [...students];
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

            const container = document.getElementById('groupsContainer');
            container.innerHTML = '';
            groups.forEach((group, i) => {
                let html = `
                <div class="group-card animate-fade-up" style="animation-delay: ${i * 0.05}s">
                    <div class="group-header">
                        <span>Group ${i + 1}</span>
                        <span class="badge" style="background: var(--bg-main); color: var(--primary); font-weight:800; border:none;">${group.length}</span>
                    </div>
                    <div>
                `;
                group.forEach(s => html += `<div class="student-item">${s}</div>`);
                html += `</div></div>`;
                container.innerHTML += html;
            });
            document.getElementById('saveBtn').style.display = 'inline-block';
        }
        
        function saveGroup() {
            Swal.fire({ title: 'Save Layout', input: 'text', inputLabel: 'Batch Name', showCancelButton: true })
            .then((result) => {
                if (result.isConfirmed && result.value) {
                    const cards = document.querySelectorAll('.group-card');
                    let currentGroups = [];
                    cards.forEach(card => {
                        let members = [];
                        card.querySelectorAll('.student-item').forEach(s => members.push(s.innerText));
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
                        <div><strong style="display:block; font-size:1rem;">\${g.name}</strong><small style="color:var(--text-muted);">\${new Date(g.created_at).toLocaleDateString()}</small></div>
                        <button onclick="deleteGroup(event, \${g.id})" class="btn btn-ghost" style="border:none; color:var(--danger);"><i class="bi bi-trash"></i></button>
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
            Swal.fire({ title: 'Delete Group?', text: "This cannot be undone.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#000', cancelButtonColor: '#f1f1f1', confirmButtonText: 'Yes, delete' })
            .then((result) => { if (result.isConfirmed) { fetch('api/groups_process.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action: 'delete_group', id: id }) }).then(() => openLoadGroups()); } });
        }

        function loadGroupIntoView(jsonMembers) {
            const groups = JSON.parse(jsonMembers); closeLoadGroups();
            const container = document.getElementById('groupsContainer'); container.innerHTML = '';
            groups.forEach((group, i) => {
                let html = `<div class="group-card animate-fade-up" style="animation-delay: \${i * 0.05}s"><div class="group-header"><span>Group \${i + 1}</span><span class="badge" style="background: var(--bg-main); color:var(--primary); font-weight:800; border:none;">\${group.length}</span></div><div>`;
                group.forEach(s => html += `<div class="student-item">\${s}</div>`);
                html += `</div></div>`;
                container.innerHTML += html;
            });
            document.getElementById('saveBtn').style.display = 'inline-block';
        }

        function exportGroups() {
            const cards = document.querySelectorAll('.group-card');
            if(cards.length === 0) { Swal.fire('No Data', 'Please randomize or load a group first.', 'info'); return; }
            let text = "Groups Export - " + new Date().toLocaleString() + "\n\n";
            cards.forEach(card => {
                text += `--- \${card.querySelector('.group-header span').innerText} ---\n`;
                card.querySelectorAll('.student-item').forEach(s => text += `\${s.innerText}\n`);
                text += "\n";
            });
            const blob = new Blob([text], { type: 'text/plain' });
            const anchor = document.createElement('a'); anchor.download = 'groups_export.txt'; anchor.href = window.URL.createObjectURL(blob); anchor.click();
        }
    </script>
</body>
</html>
