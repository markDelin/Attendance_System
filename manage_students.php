<?php
// manage_students.php - Manage Users
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
    <title>Manage Students | QR Tools by MCK</title>
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
        
        @media (max-width: 768px) {
            .search-area { top: auto; } /* Adjust because mobile header auto-height */
        }

        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.25rem;
            padding-bottom: 4rem;
        }

        .user-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            position: relative;
            box-shadow: var(--shadow-sm);
        }

        .avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: var(--text-muted);
            font-weight: 600;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            border: 1px solid var(--border);
            background: white;
            transition: all 0.2s;
        }
        .action-btn:hover { background: #f1f5f9; }
        .edit-btn { color: var(--primary); }
        .delete-btn { color: var(--danger); border-color: #fecaca; background: #fef2f2; }
        .delete-btn:hover { background: #fee2e2; }
    </style>
</head>
<body>

    <!-- Nav (Standardized) -->
    <nav class="navbar">
        <a href="index.php" class="btn btn-ghost" style="border: none; padding-left: 0;">
            <i class="bi bi-arrow-left"></i> <span class="d-none-mobile">Back</span>
        </a>
        <h3 class="text-gradient">Manage Students</h3>
        <div style="width: 40px;"></div> <!-- Spacer for balance -->
    </nav>

    <!-- Search -->
    <div class="search-area">
        <div class="container">
            <div style="position: relative;">
                <i class="bi bi-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search students..." autocomplete="off" style="padding-left: 3rem;">
            </div>
        </div>
    </div>

    <main class="container">
        <div id="userGrid" class="user-grid animate-fade-up">
            <?php foreach ($users as $user): ?>
                <div class="user-card" data-name="<?= htmlspecialchars($user['name']) ?>">
                    
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div class="avatar">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <div style="overflow: hidden;">
                            <h5 onclick="window.location.href='profile.php?qr=<?= urlencode($user['qr_code']) ?>'" style="cursor: pointer; text-decoration: underline; text-underline-offset: 4px; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($user['name']) ?></h5>
                            <small style="color: var(--text-muted); font-size: 0.75rem;">
                                ID: <?= substr($user['qr_code'], 0, 8) ?>...
                            </small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem; margin-top: auto;">
                        <button onclick="editUser('<?= htmlspecialchars($user['qr_code']) ?>', '<?= htmlspecialchars($user['name']) ?>')" 
                                class="action-btn edit-btn" style="flex: 1;">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="student_history.php?qr_code=<?= urlencode($user['qr_code']) ?>" 
                           class="action-btn" style="flex: 1; text-align: center; color: var(--text-main); text-decoration: none;">
                            <i class="bi bi-clock-history"></i>
                        </a>
                        <button onclick="deleteUser('<?= htmlspecialchars($user['qr_code']) ?>', '<?= htmlspecialchars($user['name']) ?>')" 
                                class="action-btn delete-btn" style="flex: 1;">
                            <i class="bi bi-trash"></i>
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
            document.querySelectorAll('.user-card').forEach(card => {
                const name = card.dataset.name.toLowerCase();
                card.style.display = name.includes(term) ? 'block' : 'none';
            });
        });

        function editUser(id, currentName) {
            Swal.fire({
                title: 'Edit Name',
                input: 'text',
                inputValue: currentName,
                showCancelButton: true,
                confirmButtonText: 'Save',
                confirmButtonColor: 'var(--primary)',
                preConfirm: (newName) => {
                    return fetch('api/manage_users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'update', qr_code: id, name: newName })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') {
                            Swal.showValidationMessage(data.message)
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error}`)
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Updated!', 'User name has been updated.', 'success')
                        .then(() => location.reload());
                }
            });
        }

        function deleteUser(id, name) {
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
    </script>
</body>
</html>
