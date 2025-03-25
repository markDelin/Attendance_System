<?php
// view_attendance.php - Display attendance records with status
require 'db.php';


$stmt = $pdo->query("SELECT DISTINCT date FROM attendance ORDER BY date DESC");
$dates = $stmt->fetchAll(PDO::FETCH_ASSOC);


$settingsStmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'call_time' => '08:00',
    'grace_period' => 20,
    'absent_after' => 30
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Records</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
  <style>
    .attendance-card {
      transition: transform 0.2s;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .attendance-card:hover {
      transform: translateY(-2px);
    }
    .table-hover tbody tr:hover {
      background-color: rgba(13,110,253,0.05);
    }
    .status-badge {
      font-size: 0.8rem;
      padding: 0.35em 0.65em;
      min-width: 70px;
      display: inline-block;
      text-align: center;
    }
    .badge-present {
      background-color: #28a745;
    }
    .badge-late {
      background-color: #ffc107;
      color: #212529;
    }
    .badge-absent {
      background-color: #dc3545;
    }
    .time-info {
      font-size: 0.85rem;
      color: #6c757d;
    }
  </style>
</head>
<body class="bg-light">
  <!-- Navigation Bar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
      <a class="navbar-brand" href="#">
        <i class="bi bi-calendar-check"></i> Attendance System
      </a>
      <div class="d-flex">
        <a href="index.php" class="btn btn-light me-2">
          <i class="bi bi-arrow-left-circle"></i> Back to Home
        </a>
        <a href="settings.php" class="btn btn-info">
          <i class="bi bi-gear"></i> Settings
        </a>
      </div>
    </div>
  </nav>

  <div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="text-primary mb-0"><i class="bi bi-clock-history"></i> Attendance History</h2>
      <div class="time-info">
        <i class="bi bi-info-circle"></i> Call Time: <?php echo htmlspecialchars($settings['call_time']); ?> 
        (Grace: <?php echo htmlspecialchars($settings['grace_period']); ?> mins, 
        Absent after: <?php echo htmlspecialchars($settings['grace_period'] + $settings['absent_after']); ?> mins)
      </div>
    </div>
    
    <?php if(empty($dates)): ?>
      <div class="alert alert-info text-center">
        <i class="bi bi-info-circle"></i> No attendance records found.
      </div>
    <?php endif; ?>

    <?php foreach ($dates as $row): ?>
      <div class="card attendance-card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <div>
            <i class="bi bi-calendar-date"></i>
            <?php echo htmlspecialchars($row['date']); ?>
          </div>
          <div>
            <span class="badge bg-light text-dark me-2">
              <?php 
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ?");
                $stmtCount->execute([$row['date']]);
                echo $stmtCount->fetchColumn() . ' entries';
              ?>
            </span>
            <a href="delete.php?date=<?php echo urlencode($row['date']); ?>" 
               class="btn btn-danger btn-sm" 
               onclick="return confirm('Delete all records for <?php echo $row['date']; ?>?');">
              <i class="bi bi-trash"></i> Delete All
            </a>
          </div>
        </div>
        
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th><i class="bi bi-upc-scan"></i> QR Code</th>
                  <th><i class="bi bi-person"></i> Name</th>
                  <th><i class="bi bi-clock"></i> Time</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                  $stmtRecords = $pdo->prepare("SELECT a.id, a.qr_code, a.time, a.status, u.name FROM attendance a JOIN users u ON a.qr_code = u.qr_code WHERE a.date = :date ORDER BY a.time ASC");
                  $stmtRecords->execute([':date' => $row['date']]);
                  $records = $stmtRecords->fetchAll(PDO::FETCH_ASSOC);
                  $counter = 1; // Initialize counter for each day
                  foreach ($records as $record):
                ?>
                <tr>
                  <td class="fw-bold"><?php echo $counter; ?></td>
                  <td><code><?php echo htmlspecialchars($record['qr_code']); ?></code></td>
                  <td><?php echo htmlspecialchars($record['name']); ?></td>
                  <td><?php echo htmlspecialchars($record['time']); ?></td>
                  <td>
                    <span class="status-badge badge-<?php echo htmlspecialchars($record['status']); ?>">
                      <?php echo ucfirst(htmlspecialchars($record['status'])); ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex gap-2">
                      <a href="delete.php?id=<?php echo $record['id']; ?>" 
                         class="btn btn-sm btn-danger" 
                         onclick="return confirm('Delete this record?');">
                        <i class="bi bi-trash"></i> Delete
                      </a>
                    </div>
                  </td>
                </tr>
                <?php 
                  $counter++; // Increment counter for the next record
                  endforeach; 
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
