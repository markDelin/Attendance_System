<?php
// groups.php - Group Randomizer
require 'includes/db.php';

// Fetch Students
$students = $pdo->query("SELECT * FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Randomizer | QR Tools</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .group-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1rem;
            height: 100%;
        }
        .group-header {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
            display: flex; justify-content: space-between;
        }
        .student-item {
            padding: 0.3rem 0;
            font-size: 0.9rem;
            color: var(--text-main);
            border-bottom: 1px solid #f1f5f9;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="btn btn-ghost" style="border: none; padding-left: 0;">
            <i class="bi bi-arrow-left"></i> <span class="d-none-mobile">Dashboard</span>
        </a>
        <h3 class="text-gradient">Group Randomizer</h3>
        <div>
            <button onclick="exportGroups()" class="btn btn-ghost" title="Export">
                <i class="bi bi-download"></i> Export
            </button>
            <button onclick="openSettings()" class="btn btn-ghost" title="Settings">
                <i class="bi bi-gear-fill"></i> Settings
            </button>
            <button onclick="openLoadGroups()" class="btn btn-ghost" title="Load">
                <i class="bi bi-folder2-open"></i> Load
            </button>
            <button onclick="openLoadGroups()" class="btn btn-ghost" title="Load">
                <i class="bi bi-folder2-open"></i> Load
            </button>
        </div>
    </nav>

    <main class="container" style="padding-top: 2rem;">

        <!-- Result Area -->
        <div id="groupsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
            <!-- Groups will appear here -->
             <div class="card flex-center" style="grid-column: 1 / -1; padding: 3rem; color: var(--text-muted); flex-direction: column;">
                <i class="bi bi-people" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p>Configure settings and click Randomize</p>
                <button onclick="randomize()" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="bi bi-shuffle"></i> Randomize Now
                </button>
                <button id="saveBtn" onclick="saveGroup()" class="btn btn-ghost" style="margin-top: 1rem; display: none;">
                    <i class="bi bi-save"></i> Save Group
                </button>
                <button id="saveBtn" onclick="saveGroup()" class="btn btn-ghost" style="margin-top: 1rem; display: none;">
                    <i class="bi bi-save"></i> Save Group
                </button>
             </div>
        </div>

    </main>

    <!-- Settings Modal -->
    <div id="settingsModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center;">
        <div class="card animate-fade-up" style="width: 90%; max-width: 400px; padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <h4>Group Settings</h4>
                <button onclick="closeSettings()" style="background:none; border:none; font-size: 1.2rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Grouping Method</label>
                <select id="groupMode" class="form-control" onchange="updateLabel()">
                    <option value="count">By Number of Groups</option>
                    <option value="size">By Members per Group</option>
                </select>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label id="valueLabel" style="display:block; margin-bottom:0.5rem; font-weight:600;">Number of Groups</label>
                <input type="number" id="groupValue" class="form-control" value="4" min="1">
            </div>

            <button onclick="saveAndRandomize()" class="btn btn-primary" style="width: 100%;">
                Save & Randomize
            </button>
        </div>
    </div>

    <!-- Load Groups Modal -->
    <div id="loadGroupsModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center;">
        <div class="card animate-fade-up" style="width: 90%; max-width: 400px; padding: 1.5rem; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <h4>Saved Groups</h4>
                <button onclick="closeLoadGroups()" style="background:none; border:none; font-size: 1.2rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="savedGroupsList"></div>
        </div>
    </div>
    <!-- Load Groups Modal -->
    <div id="loadGroupsModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center;">
        <div class="card animate-fade-up" style="width: 90%; max-width: 400px; padding: 1.5rem; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <h4>Saved Groups</h4>
                <button onclick="closeLoadGroups()" style="background:none; border:none; font-size: 1.2rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="savedGroupsList"></div>
        </div>
    </div>

    <!-- Data -->
    <script>
        const students = <?= json_encode(array_column($students, 'name')) ?>;
        
        // Modal Logic
        function openSettings() { document.getElementById('settingsModal').style.display = 'flex'; }
        function closeSettings() { document.getElementById('settingsModal').style.display = 'none'; }
        
        function updateLabel() {
            const mode = document.getElementById('groupMode').value;
            document.getElementById('valueLabel').innerText = mode === 'count' ? "Number of Groups" : "Max Members per Group";
        }

        function saveAndRandomize() {
            closeSettings();
            randomize();
        }

        // Randomizer Logic
        function randomize() {
            const mode = document.getElementById('groupMode').value;
            const val = parseInt(document.getElementById('groupValue').value) || 1;
            
            // Shuffle
            let shuffled = [...students];
            for (let i = shuffled.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
            }

            let groups = [];

            if (mode === 'count') {
                // Split into N groups
                const numGroups = Math.max(1, val);
                for(let i=0; i<numGroups; i++) groups.push([]);
                
                shuffled.forEach((student, index) => {
                    groups[index % numGroups].push(student);
                });
            } else {
                // Split by Size
                const size = Math.max(1, val);
                let currentGroup = [];
                shuffled.forEach(student => {
                    if(currentGroup.length >= size) {
                        groups.push(currentGroup);
                        currentGroup = [];
                    }
                    currentGroup.push(student);
                });
                if(currentGroup.length > 0) groups.push(currentGroup);
            }

            // Render
            const container = document.getElementById('groupsContainer');
            container.innerHTML = '';
            
            groups.forEach((group, i) => {
                let html = `
                <div class="group-card animate-fade-up" style="animation-delay: ${i * 0.05}s">
                    <div class="group-header">
                        <span>Group ${i + 1}</span>
                        <span class="badge">${group.length}</span>
                    </div>
                    <div>
                `;
                group.forEach(s => {
                    html += `<div class="student-item">${s}</div>`;
                });
                html += `</div></div>`;
                container.innerHTML += html;
            });
            
            // Sound Effect
            new Audio('assets/audio/game-bonus-2-294436.mp3').play().catch(()=>{});
            
            document.getElementById('saveBtn').style.display = 'inline-block';
        }
        
        // Save/Load Logic
        function saveGroup() {
            Swal.fire({
                title: 'Save Group',
                input: 'text',
                inputLabel: 'Group Name',
                showCancelButton: true
            }).then((result) => {
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
                        body: new URLSearchParams({ 
                            action: 'save_group', 
                            name: result.value,
                            members: JSON.stringify(currentGroups)
                        })
                    })
                    .then(r => r.json())
                    .then(data => Swal.fire('Saved', data.message, 'success'));
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
            .then(r => r.json())
            .then(res => {
                const list = document.getElementById('savedGroupsList');
                list.innerHTML = '';
                if(res.data.length === 0) {
                    list.innerHTML = '<p style="text-align:center; color: var(--text-muted);">No saved groups.</p>';
                    return;
                }
                res.data.forEach(g => {
                    let btn = document.createElement('div');
                    btn.className = 'group-card';
                    btn.style.marginBottom = '10px';
                    btn.style.cursor = 'pointer';
                    btn.innerHTML = `
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong>\${g.name}</strong>
                            <button onclick="deleteGroup(event, \${g.id})" style="color:red; background:none; border:none;"><i class="bi bi-trash"></i></button>
                        </div>
                        <small style="color:var(--text-muted);">\${new Date(g.created_at).toLocaleDateString()}</small>
                    `;
                    btn.onclick = (e) => { if(e.target.tagName !== 'I' && e.target.tagName !== 'BUTTON') loadGroupIntoView(g.members); };
                    list.appendChild(btn);
                });
            });
        }
        
        function closeLoadGroups() { document.getElementById('loadGroupsModal').style.display = 'none'; }
        
        function deleteGroup(e, id) {
            e.stopPropagation();
            if(!confirm('Delete this group?')) return;
            fetch('api/groups_process.php', {
                 method: 'POST',
                 headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                 body: new URLSearchParams({ action: 'delete_group', id: id })
            }).then(() => openLoadGroups());
        }

        function loadGroupIntoView(jsonMembers) {
            const groups = JSON.parse(jsonMembers);
            closeLoadGroups();
            
            const container = document.getElementById('groupsContainer');
            container.innerHTML = '';
            
            groups.forEach((group, i) => {
                let html = `
                <div class="group-card animate-fade-up" style="animation-delay: \${i * 0.05}s">
                    <div class="group-header">
                        <span>Group \${i + 1}</span>
                        <span class="badge">\${group.length}</span>
                    </div>
                    <div>
                `;
                group.forEach(s => {
                    html += `<div class="student-item">\${s}</div>`;
                });
                html += `</div></div>`;
                container.innerHTML += html;
            });
            
            document.getElementById('saveBtn').style.display = 'inline-block';
        }

        function exportGroups() {
            const container = document.getElementById('groupsContainer');
            const cards = container.getElementsByClassName('group-card');
            
            if(cards.length === 0) {
                Swal.fire('No Groups', 'Please randomize first.', 'info');
                return;
            }

            let text = "Class Groups Export\nGenerated on " + new Date().toLocaleString() + "\n\n";

            Array.from(cards).forEach(card => {
                const title = card.querySelector('.group-header span:first-child').innerText;
                const count = card.querySelector('.group-header .badge').innerText;
                text += `--- ${title} (${count} members) ---\n`;
                
                const students = card.querySelectorAll('.student-item');
                students.forEach(s => text += `${s.innerText}\n`);
                text += "\n";
            });

            const blob = new Blob([text], { type: 'text/plain' });
            const anchor = document.createElement('a');
            anchor.download = 'groups_export.txt';
            anchor.href = window.URL.createObjectURL(blob);
            anchor.target = '_blank';
            anchor.style.display = 'none';
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
        }
    </script>
</body>
</html>
