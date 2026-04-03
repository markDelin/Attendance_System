<?php
// index.php - Student Attendance System
date_default_timezone_set('Asia/Manila');
require_once 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Tools by MCK</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar animate-fade-up">
        <div class="brand flex-center" style="gap: 12px;">
            <i class="bi bi-qr-code-scan" style="color: var(--primary); font-size: 1.5rem;"></i>
            <h3>QR Tools<span class="text-gradient"> by MCK</span></h3>
        </div>
        <div>
            <span class="badge" style="background: transparent; border: 1px solid var(--border); color: var(--text-muted);">
                <?php echo date('F j, Y'); ?>
            </span>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container">
        
        <!-- Hero Section -->
        <section class="hero animate-fade-up delay-1" style="text-align: center; margin-top: 5rem; margin-bottom: 4rem;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; color: var(--text-main);">
                Student Management <span style="color: var(--primary);">Suite</span>
            </h1>
            <p style="color: var(--secondary); font-size: 1.1rem; max-width: 600px; margin: 0 auto 2rem;">
                Efficiently manage attendance, track contributions (Ambagan), and maintain student records.
            </p>
            <div class="flex-center" style="gap: 1rem;">
                <a href="scan.php" class="btn btn-primary" style="padding: 0.8rem 2rem;">
                    <i class="bi bi-qr-code-scan"></i> Start Scanning
                </a>
                <a href="view_attendance.php" class="btn btn-ghost" style="padding: 0.8rem 2rem;">
                    <i class="bi bi-arrow-right"></i> View Records
                </a>
            </div>
        </section>

        <!-- Feature Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;" class="animate-fade-up delay-2">
            
            <!-- Card 1: Scan -->
            <a href="scan.php" class="card" style="padding: 2rem; transition: transform 0.2s; display: block;">
                <div style="background: #eff6ff; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <i class="bi bi-qr-code" style="font-size: 1.5rem; color: var(--primary);"></i>
                </div>
                <h3>Class Check-In</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 0.95rem;">
                    Instant attendance marking via QR code scanning.
                </p>
            </a>

            <!-- Card 2: Manual -->
            <a href="manual.php" class="card" style="padding: 2rem; transition: transform 0.2s; display: block;">
                <div style="background: #f0fdf4; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <i class="bi bi-pen" style="font-size: 1.5rem; color: var(--success);"></i>
                </div>
                <h3>Manual Entry</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 0.95rem;">
                    Manually search and record attendance.
                </p>
            </a>

            <!-- Card 3: Reports -->
            <a href="view_attendance.php" class="card" style="padding: 2rem; transition: transform 0.2s; display: block;">
                <div style="background: #fff7ed; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <i class="bi bi-bar-chart-fill" style="font-size: 1.5rem; color: var(--warning);"></i>
                </div>
                <h3>Records</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 0.95rem;">
                    View history & export data.
                </p>
            </a>

            <!-- Card 4: Manage Students -->
            <a href="manage_students.php" class="card" style="padding: 2rem; transition: transform 0.2s; display: block;">
                <div style="background: #ecfeff; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <i class="bi bi-people-fill" style="font-size: 1.5rem; color: var(--accent);"></i>
                </div>
                <h3>Students</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 0.95rem;">
                    Edit names or remove users.
                </p>
            </a>

            <!-- Card 5: Ambagan (Billing) -->
            <a href="billing.php" class="card" style="padding: 2rem; transition: transform 0.2s; display: block;">
                <div style="background: #f0fdf4; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <i class="bi bi-wallet2" style="font-size: 1.5rem; color: #16a34a;"></i>
                </div>
                <h3>Ambagan</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 0.95rem;">
                    Track contributions & payments.
                </p>
            </a>


            <!-- Card 6: Group Randomizer -->
            <a href="groups.php" class="card" style="padding: 2rem; transition: transform 0.2s; display: block;">
                <div style="background: #fdf4ff; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <i class="bi bi-shuffle" style="font-size: 1.5rem; color: #d946ef;"></i>
                </div>
                <h3>Groups</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 0.95rem;">
                    Randomize student groups.
                </p>
            </a>

            <!-- Card 7: Settings (Full Width on Mobile, Tile on Desktop) -->
            <a href="settings.php" class="card" style="padding: 2rem; transition: transform 0.2s; display: block;">
                <div style="background: #f1f5f9; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <i class="bi bi-gear-fill" style="font-size: 1.5rem; color: var(--secondary);"></i>
                </div>
                <h3>Settings</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 0.95rem;">
                    Configure app behavior.
                </p>
            </a>

        </div>


</main>

    <footer style="text-align: center; color: var(--text-muted); padding: 3rem; margin-top: auto;">
        <small>Made with ❤️ by MCK</small>
    </footer>

</body>
</html>