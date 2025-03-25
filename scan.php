<?php
// scan.php - QR Code scanning page with unified success feedback
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>QR Scanner | Attendance System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
  <style>
    .scanner-container {
      max-width: 500px;
      margin: 2rem auto;
      position: relative;
    }
    #reader {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    }
    .scan-overlay {
      position: absolute;
      width: 100%;
      height: 100%;
      pointer-events: none;
      border: 2px solid #0d6efd;
      border-radius: 12px;
    }
    .pulse-animation {
      animation: pulse 2s infinite;
    }
    
    /* Combined Success Feedback */
    .success-feedback {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.7);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s;
    }
    .success-feedback.show {
      opacity: 1;
    }
    .checkmark {
      width: 100px;
      height: 100px;
      margin-bottom: 20px;
    }
    .checkmark-circle {
      stroke-dasharray: 166;
      stroke-dashoffset: 166;
      stroke-width: 5;
      stroke-miterlimit: 10;
      stroke: #4bb71b;
      fill: none;
      animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
    }
    .checkmark-check {
      transform-origin: 50% 50%;
      stroke-dasharray: 48;
      stroke-dashoffset: 48;
      stroke: #4bb71b;
      animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.4s forwards;
    }
    .success-message {
      color: white;
      font-size: 1.5rem;
      text-align: center;
      padding: 0 20px;
      animation: fadeIn 0.5s ease-in-out 0.5s both;
    }
    
    @keyframes pulse {
      0% { opacity: 0.8; }
      50% { opacity: 0.4; }
      100% { opacity: 0.8; }
    }
    @keyframes stroke {
      100% { stroke-dashoffset: 0; }
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
  <script src="html5-qrcode.min.js" type="text/javascript"></script>
</head>
<body class="bg-light">
  <!-- Navigation Bar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
      <a class="navbar-brand" href="#">
        <i class="bi bi-qr-code-scan"></i> Attendance Scanner
      </a>
      <div class="d-flex">
        <a href="index.php" class="btn btn-light">
          <i class="bi bi-arrow-left-circle"></i> Back to Home
        </a>
      </div>
    </div>
  </nav>

  <div class="container">
    <div class="scanner-container">
      <div class="card shadow-lg">
        <div class="card-header bg-primary text-white">
          <h4 class="mb-0"><i class="bi bi-camera"></i> Scanner Ready</h4>
        </div>
        <div class="card-body position-relative p-0">
          <div class="scan-overlay pulse-animation"></div>
          <div id="reader"></div>
        </div>
        <div class="card-footer bg-light">
          <p class="text-muted mb-0 small">
            <i class="bi bi-info-circle"></i> Position QR code within frame to scan
          </p>
        </div>
      </div>
      
      <div id="statusMessage" class="alert alert-info mt-3 text-center"></div>
    </div>
  </div>

  <!-- Combined Success Feedback -->
  <div class="success-feedback" id="successFeedback">
    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
      <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
    </svg>
    <div class="success-message" id="successMessage"></div>
  </div>

  <!-- Name Input Modal -->
  <div class="modal fade" id="nameModal" tabindex="-1" aria-labelledby="nameModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="nameModalLabel">
            <i class="bi bi-person-plus"></i> New User Detected
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="userName" class="form-label">Please enter your full name:</label>
            <input type="text" class="form-control form-control-lg" id="userName" placeholder="John Doe" autofocus>
            <div class="form-text">This name will be associated with the QR code</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary btn-lg w-100" id="submitName">
            <i class="bi bi-check2"></i> Register Name
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Audio Element for Scan Sound -->
  <audio id="scanSound" preload="auto">
    <source src="https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3" type="audio/mpeg">
  </audio>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let lastScannedCode = "";
    let pendingQRCode = "";
    const scanSound = document.getElementById('scanSound');
    const successFeedback = document.getElementById('successFeedback');
    const successMessage = document.getElementById('successMessage');

    function showSuccessFeedback(message) {
      successMessage.textContent = message;
      successFeedback.classList.add('show');
      playScanSound();
      
      setTimeout(() => {
        successFeedback.classList.remove('show');
      }, 3000);
    }

    function playScanSound() {
      scanSound.currentTime = 0;
      scanSound.play().catch(e => console.log("Audio play failed:", e));
    }

    function processQRCode(qrCode, name = "") {
      if (qrCode === lastScannedCode) return;
      lastScannedCode = qrCode;

      fetch('process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ qr_code: qrCode, name: name })
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'new') {
          pendingQRCode = qrCode;
          var nameModal = new bootstrap.Modal(document.getElementById('nameModal'));
          nameModal.show();
        } else if (data.status === 'success') {
          document.getElementById('statusMessage').innerText = data.message;
          showSuccessFeedback(data.message);
        } else {
          document.getElementById('statusMessage').innerText = data.message;
        }
      })
      .catch(err => {
        console.error(err);
        document.getElementById('statusMessage').innerText = "Error processing QR code.";
      });

      setTimeout(() => {
        lastScannedCode = "";
      }, 3000);
    }

    document.getElementById('submitName').addEventListener('click', function() {
      let userName = document.getElementById('userName').value.trim();
      if (userName === "") {
        alert("Please enter your name.");
        return;
      }
      var nameModal = bootstrap.Modal.getInstance(document.getElementById('nameModal'));
      nameModal.hide();
      processQRCode(pendingQRCode, userName);
      document.getElementById('userName').value = "";
    });

    function onScanSuccess(decodedText, decodedResult) {
      processQRCode(decodedText);
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
      "reader", { fps: 10, qrbox: 250 }
    );
    html5QrcodeScanner.render(onScanSuccess);
  </script>
</body>
</html>