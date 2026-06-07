(function () {
  'use strict';

  const STORAGE_KEY_SERVER = 'attendance_server_url';
  const STORAGE_KEY_TOKEN = 'attendance_api_token';
  const STORAGE_KEY_CACHE = 'attendance_students_cache';
  const STORAGE_KEY_CACHE_TIME = 'attendance_cache_time';
  const CACHE_TTL = 30 * 60 * 1000; // 30 min

  let students = [];
  let records = {};
  let filteredStudents = [];

  function getServerUrl() {
    return localStorage.getItem(STORAGE_KEY_SERVER) || '';
  }

  function getApiToken() {
    return localStorage.getItem(STORAGE_KEY_TOKEN) || '';
  }

  function saveSettings() {
    const url = document.getElementById('serverUrl').value.replace(/\/+$/, '');
    const token = document.getElementById('apiKey').value.trim();
    localStorage.setItem(STORAGE_KEY_SERVER, url);
    localStorage.setItem(STORAGE_KEY_TOKEN, token);
    toggleSettings();
    checkConnection();
    loadStudents();
    showToast('Settings saved', 'success');
  }

  function toggleSettings() {
    const panel = document.getElementById('settingsPanel');
    const isOpen = panel.classList.contains('open');
    if (!isOpen) {
      document.getElementById('serverUrl').value = getServerUrl();
      document.getElementById('apiKey').value = getApiToken();
      panel.classList.remove('hidden');
      requestAnimationFrame(function () { panel.classList.add('open'); });
    } else {
      panel.classList.remove('open');
      setTimeout(function () { panel.classList.add('hidden'); }, 300);
    }
  }

  function getApiUrl(path) {
    const base = getServerUrl();
    if (!base) return '';
    return base.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
  }

  function showToast(message, type) {
    if (!type) type = 'info';
    var container = document.getElementById('toastContainer');
    var icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<i class="bi ' + (icons[type] || icons.info) + ' toast-icon"></i>'
      + '<span class="toast-content">' + message + '</span>'
      + '<button class="toast-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>';
    container.appendChild(toast);
    setTimeout(function () {
      if (toast.parentElement) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.2s';
        setTimeout(function () { if (toast.parentElement) toast.remove(); }, 200);
      }
    }, 3000);
  }

  function setConnectionStatus(online) {
    var dot = document.getElementById('appStatus');
    var text = document.getElementById('appStatusText');
    dot.className = 'status-dot ' + (online ? 'status-online' : 'status-offline');
    text.textContent = online ? 'Connected' : 'Disconnected';
  }

  async function checkConnection() {
    var url = getApiUrl('api/mobile_attendance.php');
    if (!url) {
      setConnectionStatus(false);
      return false;
    }
    try {
      var res = await fetch(url, { method: 'GET', cache: 'no-store' });
      if (res.ok) {
        setConnectionStatus(true);
        return true;
      }
    } catch (e) {}
    setConnectionStatus(false);
    return false;
  }

  function loadCachedStudents() {
    var cached = localStorage.getItem(STORAGE_KEY_CACHE);
    var cacheTime = localStorage.getItem(STORAGE_KEY_CACHE_TIME);
    if (cached && cacheTime && (Date.now() - parseInt(cacheTime) < CACHE_TTL)) {
      try {
        var data = JSON.parse(cached);
        if (data && data.length) {
          students = data;
          return true;
        }
      } catch (e) {}
    }
    return false;
  }

  function cacheStudents(data) {
    localStorage.setItem(STORAGE_KEY_CACHE, JSON.stringify(data));
    localStorage.setItem(STORAGE_KEY_CACHE_TIME, String(Date.now()));
  }

  async function loadStudents() {
    var list = document.getElementById('studentList');
    var url = getApiUrl('api/mobile_attendance.php');
    if (!url) {
      list.innerHTML = '<div class="error-state"><i class="bi bi-gear"></i><p>Configure server URL in Settings</p></div>';
      return;
    }

    if (loadCachedStudents()) {
      renderStudentList();
    }

    try {
      var res = await fetch(url, { method: 'GET', cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var data = await res.json();
      if (data.status === 'success' && data.students) {
        students = data.students;
        cacheStudents(students);
        renderStudentList();
        setConnectionStatus(true);
      } else {
        throw new Error('Invalid response');
      }
    } catch (e) {
      if (students.length === 0) {
        list.innerHTML = '<div class="error-state"><i class="bi bi-wifi-off"></i><p>Cannot connect to server</p><small>Check your connection and settings</small></div>';
      }
      setConnectionStatus(false);
    }
  }

  function renderStudentList() {
    var list = document.getElementById('studentList');
    var searchVal = (document.getElementById('searchInput').value || '').toLowerCase().trim();

    filteredStudents = students.filter(function (s) {
      if (!searchVal) return true;
      return (s.name || '').toLowerCase().indexOf(searchVal) !== -1
        || (s.qr_code || '').toLowerCase().indexOf(searchVal) !== -1;
    });

    if (filteredStudents.length === 0) {
      list.innerHTML = '<div class="empty-state"><i class="bi bi-search"></i><p>No students found</p></div>';
      updateStats();
      return;
    }

    var html = '';
    for (var i = 0; i < filteredStudents.length; i++) {
      var s = filteredStudents[i];
      var qr = s.qr_code;
      var name = escapeHtml(s.name || '');
      var sid = escapeHtml(qr || '');
      var cls = '';
      var activeP = '', activeL = '', activeA = '';
      if (records[qr]) {
        cls = ' ' + records[qr];
        if (records[qr] === 'present') activeP = ' active-p';
        else if (records[qr] === 'late') activeL = ' active-l';
        else if (records[qr] === 'absent') activeA = ' active-a';
      }
      html += '<div class="student-row' + cls + '" data-qr="' + escapeHtml(qr) + '">'
        + '<div class="student-info">'
        + '<div class="student-name">' + name + '</div>'
        + '<div class="student-id">' + sid + '</div>'
        + '</div>'
        + '<div class="student-actions">'
        + '<button class="btn-stat' + activeP + '" onclick="window.setStudentStatus(\'' + escapeHtml(qr) + '\',\'present\')" title="Present">P</button>'
        + '<button class="btn-stat' + activeL + '" onclick="window.setStudentStatus(\'' + escapeHtml(qr) + '\',\'late\')" title="Late">L</button>'
        + '<button class="btn-stat' + activeA + '" onclick="window.setStudentStatus(\'' + escapeHtml(qr) + '\',\'absent\')" title="Absent">A</button>'
        + '<button class="btn-stat clear" onclick="window.setStudentStatus(\'' + escapeHtml(qr) + '\',\'clear\')" title="Clear"><i class="bi bi-x"></i></button>'
        + '</div>'
        + '</div>';
    }
    list.innerHTML = html;
    updateFooterInfo();
    updateStats();
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  window.setStudentStatus = function (qr, status) {
    var row = document.querySelector('.student-row[data-qr="' + CSS.escape(qr) + '"]');
    if (status === 'clear') {
      delete records[qr];
      if (row) {
        row.classList.remove('present', 'late', 'absent');
        row.querySelectorAll('.btn-stat')[0].className = 'btn-stat';
        row.querySelectorAll('.btn-stat')[1].className = 'btn-stat';
        row.querySelectorAll('.btn-stat')[2].className = 'btn-stat';
      }
    } else {
      var prevStatus = records[qr];
      if (prevStatus === status) {
        delete records[qr];
        if (row) {
          row.classList.remove(status);
          row.querySelectorAll('.btn-stat')[0].className = 'btn-stat';
          row.querySelectorAll('.btn-stat')[1].className = 'btn-stat';
          row.querySelectorAll('.btn-stat')[2].className = 'btn-stat';
        }
      } else {
        records[qr] = status;
        if (row) {
          row.classList.remove('present', 'late', 'absent');
          row.classList.add(status);
          var btnP = row.querySelectorAll('.btn-stat')[0];
          var btnL = row.querySelectorAll('.btn-stat')[1];
          var btnA = row.querySelectorAll('.btn-stat')[2];
          btnP.className = 'btn-stat' + (status === 'present' ? ' active-p' : '');
          btnL.className = 'btn-stat' + (status === 'late' ? ' active-l' : '');
          btnA.className = 'btn-stat' + (status === 'absent' ? ' active-a' : '');
        }
      }
    }
    updateFooterInfo();
    updateStats();
  };

  function updateFooterInfo() {
    var count = Object.keys(records).length;
    document.getElementById('recordCount').innerHTML = '<i class="bi bi-journal"></i> ' + count + ' record' + (count !== 1 ? 's' : '');
    document.getElementById('btnUpload').disabled = count === 0;
  }

  function updateStats() {
    var present = 0, late = 0, absent = 0;
    for (var qr in records) {
      if (records[qr] === 'present') present++;
      else if (records[qr] === 'late') late++;
      else if (records[qr] === 'absent') absent++;
    }
    document.getElementById('statPresent').textContent = present;
    document.getElementById('statLate').textContent = late;
    document.getElementById('statAbsent').textContent = absent;
    document.getElementById('statRemaining').textContent = filteredStudents.length - present - late - absent;
  }

  async function uploadAttendance() {
    var count = Object.keys(records).length;
    if (count === 0) return;

    var apiUrl = getApiUrl('api/mobile_attendance.php');
    if (!apiUrl) {
      showToast('Configure server URL in Settings first', 'error');
      return;
    }

    var token = getApiToken();
    if (!token) {
      showToast('Enter your API key in Settings', 'error');
      return;
    }

    var date = document.getElementById('attendanceDate').value;
    if (!date) {
      showToast('Please select a date', 'warning');
      return;
    }

    var recordsArray = [];
    for (var qr in records) {
      recordsArray.push({ qr_code: qr, status: records[qr] });
    }

    var overlay = document.createElement('div');
    overlay.className = 'upload-overlay';
    overlay.innerHTML = '<div class="spinner"></div><p>Uploading...</p><small>' + count + ' records</small>';
    document.body.appendChild(overlay);

    document.getElementById('btnUpload').disabled = true;

    try {
      var res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ api_key: token, date: date, records: recordsArray })
      });

      if (!res.ok) throw new Error('Server returned ' + res.status);

      var data = await res.json();

      if (data.status === 'success') {
        var summary = data.summary || {};
        var msg = 'Uploaded ' + data.processed + ' records';
        if (summary.present) msg += ' | [P] ' + summary.present;
        if (summary.late) msg += ' | [L] ' + summary.late;
        if (summary.absent) msg += ' | [A] ' + summary.absent;
        showToast(msg, 'success');
        records = {};
        renderStudentList();
        setConnectionStatus(true);
      } else {
        showToast(data.message || 'Upload failed', 'error');
      }
    } catch (e) {
      showToast('Connection error: ' + e.message, 'error');
      document.getElementById('btnUpload').disabled = false;
    }

    if (overlay.parentElement) overlay.remove();
    updateFooterInfo();
  }

  window.uploadAttendance = uploadAttendance;
  window.toggleSettings = toggleSettings;
  window.saveSettings = saveSettings;

  function init() {
    var today = new Date();
    var y = today.getFullYear();
    var m = String(today.getMonth() + 1).padStart(2, '0');
    var d = String(today.getDate()).padStart(2, '0');
    document.getElementById('attendanceDate').value = y + '-' + m + '-' + d;

    document.getElementById('attendanceDate').addEventListener('change', function () {
      records = {};
      renderStudentList();
    });

    var searchTimer;
    document.getElementById('searchInput').addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(renderStudentList, 150);
    });

    checkConnection();
    loadStudents();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
