<?php
// settings.php - UI for changing call time settings
require 'db.php';

// Get current settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Use defaults if no settings exist
if (!$settings) {
    $settings = [
        'call_time' => '08:00',
        'grace_period' => 20,
        'absent_after' => 30
    ];
}

// Save new settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $call_time = $_POST['call_time'] ?? '08:00';
    $grace_period = intval($_POST['grace_period'] ?? 20);
    $absent_after = intval($_POST['absent_after'] ?? 30);

    // Validate inputs
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $call_time)) {
        $error = "Invalid time format. Use HH:MM (24-hour format)";
    } elseif ($grace_period < 0 || $grace_period > 120) {
        $error = "Grace period must be between 0-120 minutes";
    } elseif ($absent_after < 0 || $absent_after > 240) {
        $error = "Absent threshold must be between 0-240 minutes";
    } else {
        // Save to database
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM settings");
            $stmt = $pdo->prepare("INSERT INTO settings (call_time, grace_period, absent_after) VALUES (?, ?, ?)");
            $stmt->execute([$call_time, $grace_period, $absent_after]);
            $pdo->commit();
            $success = "Settings updated successfully!";
            $settings = compact('call_time', 'grace_period', 'absent_after');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
  <style>
    .settings-card { max-width: 600px; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .time-input { max-width: 100px; display: inline-block; }
    .range-value { min-width: 30px; display: inline-block; text-align: center; }
  </style>
</head>
<body class="bg-light">
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
      <a class="navbar-brand" href="#"><i class="bi bi-gear"></i> Attendance Settings</a>
      <div class="d-flex">
        <a href="index.php" class="btn btn-light"><i class="bi bi-arrow-left-circle"></i> Back to Home</a>
      </div>
    </div>
  </nav>

  <div class="container">
    <div class="card settings-card">
      <div class="card-header bg-primary text-white">
        <h4 class="mb-0"><i class="bi bi-clock"></i> Attendance Time Settings</h4>
      </div>
      <div class="card-body">
        <?php if (isset($error)): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
          <div class="mb-4">
            <h5 class="mb-3"><i class="bi bi-alarm"></i> Official Time</h5>
            <div class="d-flex align-items-center">
              <label class="me-3">Start time:</label>
              <input type="time" class="form-control time-input" name="call_time" 
                     value="<?php echo htmlspecialchars($settings['call_time']); ?>" required>
            </div>
          </div>

          <div class="mb-4">
            <h5 class="mb-3"><i class="bi bi-hourglass"></i> Grace Period</h5>
            <div class="d-flex align-items-center">
              <label class="me-3">Minutes before late:</label>
              <input type="range" class="form-range mx-3" name="grace_period" min="0" max="120" 
                     value="<?php echo htmlspecialchars($settings['grace_period']); ?>" 
                     oninput="document.getElementById('graceValue').textContent = this.value">
              <span class="range-value" id="graceValue"><?php echo htmlspecialchars($settings['grace_period']); ?></span>
            </div>
          </div>

          <div class="mb-4">
            <h5 class="mb-3"><i class="bi bi-exclamation-triangle"></i> Absent Threshold</h5>
            <div class="d-flex align-items-center">
              <label class="me-3">Minutes after grace period:</label>
              <input type="range" class="form-range mx-3" name="absent_after" min="0" max="240" 
                     value="<?php echo htmlspecialchars($settings['absent_after']); ?>" 
                     oninput="document.getElementById('absentValue').textContent = this.value">
              <span class="range-value" id="absentValue"><?php echo htmlspecialchars($settings['absent_after']); ?></span>
            </div>
            <small class="text-muted">Total: <?php echo ($settings['grace_period'] + $settings['absent_after']); ?> minutes after call time</small>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="bi bi-save"></i> Save Settings
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>