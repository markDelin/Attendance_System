<?php
// index.php - Landing page with Settings option
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
  <style>
    .feature-card {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: none;
      border-radius: 15px;
    }
    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .gradient-bg {
      background: linear-gradient(135deg, #6B46C1 0%, #4299E1 100%);
    }
    .icon-wrapper {
      width: 80px;
      height: 80px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
  </style>
</head>
<body class="bg-light">
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark gradient-bg">
    <div class="container">
      <a class="navbar-brand" href="#">
        <i class="bi bi-calendar-check fs-4"></i>
        <span class="ms-2 fw-bold">Attendance System</span>
      </a>
    </div>
  </nav>

  <main class="container my-5">
    <div class="text-center mb-5">
      <h1 class="display-4 fw-bold text-primary mb-3">
        <i class="bi bi-fingerprint"></i> Attendance Portal
      </h1>
      <p class="lead text-muted">Manage attendance records with modern digital solutions</p>
    </div>

    <div class="row g-4 justify-content-center">
      <!-- Scan Card -->
      <div class="col-12 col-md-6 col-lg-4">
        <div class="feature-card card h-100 shadow-lg">
          <div class="card-body text-center p-4">
            <div class="icon-wrapper bg-primary rounded-circle p-3 d-inline-block mb-4 mx-auto">
              <i class="bi bi-qr-code-scan fs-1 text-white"></i>
            </div>
            <h3 class="card-title mb-3">Scan Attendance</h3>
            <p class="card-text text-muted mb-4">
              Quickly scan QR codes to record attendance in real-time
            </p>
            <a href="scan.php" class="btn btn-primary btn-lg w-100">
              <i class="bi bi-camera me-2"></i> Start Scanning
            </a>
          </div>
        </div>
      </div>

      <!-- View Card -->
      <div class="col-12 col-md-6 col-lg-4">
        <div class="feature-card card h-100 shadow-lg">
          <div class="card-body text-center p-4">
            <div class="icon-wrapper bg-success rounded-circle p-3 d-inline-block mb-4 mx-auto">
              <i class="bi bi-clipboard-data fs-1 text-white"></i>
            </div>
            <h3 class="card-title mb-3">View Records</h3>
            <p class="card-text text-muted mb-4">
              Access and manage historical attendance data with ease
            </p>
            <a href="view_attendance.php" class="btn btn-success btn-lg w-100">
              <i class="bi bi-table me-2"></i> View History
            </a>
          </div>
        </div>
      </div>

      <!-- Settings Card -->
      <div class="col-12 col-md-6 col-lg-4">
        <div class="feature-card card h-100 shadow-lg">
          <div class="card-body text-center p-4">
            <div class="icon-wrapper bg-info rounded-circle p-3 d-inline-block mb-4 mx-auto">
              <i class="bi bi-gear fs-1 text-white"></i>
            </div>
            <h3 class="card-title mb-3">System Settings</h3>
            <p class="card-text text-muted mb-4">
              Configure call time and attendance rules
            </p>
            <a href="settings.php" class="btn btn-info btn-lg w-100">
              <i class="bi bi-sliders me-2"></i> Adjust Settings
            </a>
          </div>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-dark text-white text-center py-3 mt-5">
    <div class="container">
      <p class="mb-0">&copy; <?php echo date('Y'); ?> Gmzn_Programming. All rights reserved.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>