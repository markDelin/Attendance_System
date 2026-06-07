(function () {
  'use strict';

  const KEYS = {
    serverUrl: 'app_server_url',
    apiKey: 'app_api_key',
    students: 'app_students',
    subjects: 'app_subjects',
    schedules: 'app_schedules',
    pending: 'app_pending_records',
    lastSync: 'app_last_sync',
    theme: 'app_theme'
  };

  let students = [];
  let subjects = [];
  let schedules = [];
  let currentView = 'home';
  let currentSubjectId = null;
  let currentRecords = {};
  let html5QrCode = null;
  let isScanning = false;
  let autoSyncTimer = null;
  let searchTimer = null;

  // SQLite — loaded from actual database file via sql.js
  let SQL = null;
  let sqlDb = null;
  let dbReady = false;

  function dbQuery(sql, params) {
    if (!sqlDb) return null;
    try {
      var stmt = sqlDb.prepare(sql);
      if (params) for (var i = 0; i < params.length; i++) stmt.bind(params);
      var rows = [];
      while (stmt.step()) rows.push(stmt.getAsObject());
      stmt.free();
      return rows;
    } catch (e) { return null; }
  }

  function dbGet(sql, params) {
    var rows = dbQuery(sql, params);
    return rows && rows.length > 0 ? rows[0] : null;
  }

  function getStudents() {
    if (dbReady) { var r = dbQuery("SELECT qr_code, name, course, year_level, student_type FROM users WHERE deleted_at IS NULL ORDER BY name ASC"); if (r) return r; }
    var cached = getJson('students') || [];
    // Ensure student_type field exists (for old cached data)
    for (var i = 0; i < cached.length; i++) { if (!cached[i].student_type) cached[i].student_type = 'regular'; }
    return cached;
  }

  function getStudentSubjects() {
    if (dbReady) { var r = dbQuery("SELECT qr_code, subject_id FROM student_subjects"); if (r) return r; }
    return getJson('student_subjects') || [];
  }

  function getSubjects() {
    if (dbReady) { var r = dbQuery("SELECT id, name, code, room, lecturer, semester, category FROM subjects WHERE is_active = 1 ORDER BY name ASC"); if (r) return r; }
    return getJson('subjects') || [];
  }

  function getSchedules() {
    if (dbReady) { var r = dbQuery("SELECT sc.id, sc.subject_id, s.name as subject_name, s.code, s.room, s.lecturer, sc.day_of_week, sc.start_time, sc.end_time FROM schedules sc JOIN subjects s ON s.id = sc.subject_id WHERE s.is_active = 1 ORDER BY sc.start_time ASC"); if (r) return r; }
    return getJson('schedules') || [];
  }

  function dayName(dateStr) {
    var d = new Date(dateStr + 'T12:00:00');
    return ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][d.getDay()];
  }

  async function downloadDatabase() {
    var url = apiUrl('api/serve_db.php');
    if (!url) return false;
    try {
      var res = await fetch(url, { method: 'GET', cache: 'no-store' });
      if (!res.ok) return false;
      var buf = await res.arrayBuffer();
      if (typeof initSqlJs !== 'undefined') {
        SQL = await initSqlJs();
        sqlDb = new SQL.Database(new Uint8Array(buf));
        dbReady = true;
        renderSchedule();
        renderSubjects();
        return true;
      }
    } catch (e) {}
    return false;
  }

  function getVal(k) { return localStorage.getItem(KEYS[k]) || ''; }
  function setVal(k, v) { localStorage.setItem(KEYS[k], v); }

  function getJson(k) {
    try { return JSON.parse(localStorage.getItem(KEYS[k])) || null; }
    catch (e) { return null; }
  }
  function setJson(k, v) { localStorage.setItem(KEYS[k], JSON.stringify(v)); }

  function getServerUrl() { return getVal('serverUrl').replace(/\/+$/, ''); }
  function getApiToken() { return getVal('apiKey').trim(); }
  function apiUrl(path) { var b = getServerUrl(); return b ? b + '/' + path.replace(/^\//, '') : ''; }

  function $(id) { return document.getElementById(id); }

  function escapeHtml(s) { if (!s) return ''; return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

  function showToast(msg, type) {
    if (!type) type = 'info';
    var icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    var c = $('toastContainer');
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = '<i class="bi ' + (icons[type] || icons.info) + ' toast-icon"></i><span class="toast-content">' + msg + '</span><button class="toast-close" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>';
    c.appendChild(t);
    setTimeout(function () {
      if (t.parentElement) { t.style.opacity = '0'; t.style.transform = 'translateY(10px)'; t.style.transition = 'all 0.2s'; setTimeout(function () { if (t.parentElement) t.remove(); }, 200); }
    }, 3000);
  }

  function showOverlay(msg, sub) {
    var o = document.createElement('div'); o.className = 'sync-overlay';
    o.innerHTML = '<div class="spinner"></div><p>' + msg + '</p>' + (sub ? '<small>' + sub + '</small>' : '');
    document.body.appendChild(o); return o;
  }

  function updatePendingBadge() {
    var pending = getJson('pending') || [];
    var badge = $('pendingBadge');
    var info = $('pendingInfo');
    var navBadge = $('navPendingBadge');
    var count = pending.length;
    if (count > 0) {
      badge.classList.remove('hidden');
      info.classList.remove('hidden');
      info.textContent = count + ' pending';
      navBadge.textContent = count;
      navBadge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
      info.classList.add('hidden');
      navBadge.classList.add('hidden');
    }
  }

  function setConnectionStatus(online) {
    $('appStatus').className = 'status-dot ' + (online ? 'status-online' : 'status-offline');
    $('appStatusText').textContent = online ? 'Online' : 'Offline';
  }

  function hideAllViews() {
    var views = ['homeView', 'recordView', 'queueView', 'historyView'];
    for (var i = 0; i < views.length; i++) {
      var el = $(views[i]);
      el.classList.add('hidden');
      el.classList.remove('view-enter');
    }
  }

  function showView(el) {
    el.classList.remove('hidden');
    el.classList.add('view-enter');
  }

  function setActiveNav(tab) {
    var tabs = document.querySelectorAll('.bottom-nav-tab');
    for (var i = 0; i < tabs.length; i++) {
      tabs[i].classList.toggle('active', tabs[i].dataset.tab === tab);
    }
  }

  window.switchTab = function (tab) {
    $('bottomNav').classList.remove('hidden');
    $('appFooter').classList.add('hidden');
    setActiveNav(tab);

    if (tab === 'home') {
      currentView = 'home';
      currentSubjectId = null;
      hideAllViews();
      showView($('homeView'));
      $('btnBack').classList.add('hidden');
      $('appTitle').textContent = 'Attendance';
      renderSchedule();
      renderSubjects();
    } else if (tab === 'pending') {
      currentView = 'queue';
      hideAllViews();
      showView($('queueView'));
      $('btnBack').classList.add('hidden');
      $('appTitle').textContent = 'Pending Records';
      renderQueueList();
    } else if (tab === 'history') {
      currentView = 'history';
      hideAllViews();
      showView($('historyView'));
      $('btnBack').classList.add('hidden');
      $('appTitle').textContent = 'History';
      switchHistoryTab('synced');
    }
    updatePendingBadge();
  };

  function showHome() { switchTab('home'); }

  window.goHome = function () { showHome(); };

  function showRecordView() {
    currentView = 'record';
    hideAllViews();
    showView($('recordView'));
    $('bottomNav').classList.add('hidden');
    $('btnBack').classList.remove('hidden');
    $('appFooter').classList.remove('hidden');
  }

  function openSubject(subjectId, subjectName) {
    currentSubjectId = subjectId;
    currentRecords = {};
    showRecordView();
    $('appTitle').textContent = subjectName;
    $('recordContextTitle').textContent = subjectName;
    $('studentList').innerHTML = '<div class="loading-state"><i class="bi bi-arrow-repeat spin"></i><p>Loading...</p></div>';
    renderStudentList();
  }

  window.openSubject = openSubject;

  function openDaily() {
    currentSubjectId = null;
    currentRecords = {};
    showRecordView();
    $('appTitle').textContent = 'Daily Attendance';
    $('recordContextTitle').textContent = 'General Attendance';
    $('studentList').innerHTML = '<div class="loading-state"><i class="bi bi-arrow-repeat spin"></i><p>Loading...</p></div>';
    renderStudentList();
  }

  window.openDaily = openDaily;

  function renderSchedule() {
    var container = $('scheduleList');
    var section = $('scheduleSection');
    var data = getSchedules();
    var today = $('attendanceDate').value;
    var todayName = dayName(today);

    if (!data || data.length === 0) { section.classList.add('hidden'); return; }

    var filtered = data.filter(function (s) {
      var dow = (s.day_of_week || '').toLowerCase();
      return dow === todayName.toLowerCase();
    });
    if (filtered.length === 0) { section.classList.add('hidden'); return; }

    section.classList.remove('hidden');
    var now = new Date();
    var currentMinutes = now.getHours() * 60 + now.getMinutes();
    var html = '';
    for (var i = 0; i < filtered.length; i++) {
      var s = filtered[i];
      var timeStr = s.start_time || '';
      var parts = timeStr.split(':');
      var startMins = parts.length >= 2 ? parseInt(parts[0]) * 60 + parseInt(parts[1]) : -1;
      var isNow = startMins >= 0 && Math.abs(currentMinutes - startMins) < 60;
      var endStr = s.end_time ? ' - ' + s.end_time : '';
      var meta = (s.room ? s.room : '');
      html += '<div class="schedule-card' + (isNow ? ' now' : '') + '"' + (s.subject_id ? ' onclick="openSubject(' + s.subject_id + ',\'' + escapeHtml(s.subject_name || s.name || '') + '\')"' : '') + '>'
        + '<div class="schedule-time">' + escapeHtml(timeStr + endStr) + '</div>'
        + '<div class="schedule-info"><div class="schedule-name">' + escapeHtml(s.subject_name || s.name || '') + '</div>'
        + (meta ? '<div class="schedule-meta">' + escapeHtml(meta) + '</div>' : '') + '</div>'
        + (s.subject_id ? '<span class="schedule-tag">Attend</span>' : '') + '</div>';
    }
    container.innerHTML = html;
  }

  function renderSubjects() {
    var container = $('subjectList');
    var data = getSubjects();
    var pending = getJson('pending') || [];

    if (!data || data.length === 0) {
      var hasServer = !!getServerUrl();
      if (hasServer) {
        container.innerHTML = '<div class="loading-state"><i class="bi bi-arrow-repeat spin"></i><p>Tap Sync to download data</p></div>';
      } else {
        container.innerHTML = '<div class="no-data-state"><i class="bi bi-cloud-off"></i><h3>No Data Yet</h3><p>Set up your server connection to download students and subjects.</p><button onclick="toggleSettings()" class="btn btn-primary" style="margin-top:16px"><i class="bi bi-gear"></i> Open Settings</button></div>';
      }
      return;
    }

    // Group by semester/school year
    var groups = {};
    for (var i = 0; i < data.length; i++) {
      var s = data[i];
      var key = s.semester || 'Other';
      if (!groups[key]) groups[key] = [];
      groups[key].push(s);
    }

    var html = '';
    var keys = Object.keys(groups).sort();
    for (var g = 0; g < keys.length; g++) {
      var semester = keys[g];
      var items = groups[semester];
      html += '<h3 class="section-title semester-title"><i class="bi bi-bookmark"></i> ' + escapeHtml(semester) + '</h3>';
      for (var i = 0; i < items.length; i++) {
        var s = items[i];
        var hasPending = false;
        for (var p = 0; p < pending.length; p++) {
          if (pending[p].subject_id == s.id) { hasPending = true; break; }
        }
        var meta = [];
        if (s.code) meta.push(s.code);
        if (s.lecturer) meta.push(s.lecturer);
        html += '<div class="subject-card' + (hasPending ? ' has-records' : '') + '" onclick="openSubject(' + s.id + ',\'' + escapeHtml(s.name) + '\')">'
          + '<div class="subject-icon"><i class="bi bi-journal-text"></i></div>'
          + '<div class="subject-info"><div class="subject-name">' + escapeHtml(s.name) + '</div>'
          + '<div class="subject-meta">' + escapeHtml(meta.join(' \u00B7 ')) + '</div></div>'
          + (hasPending ? '<span class="subject-status"><i class="bi bi-clock"></i> Pending</span>' : '')
          + '<i class="bi bi-chevron-right subject-arrow"></i></div>';
      }
    }
    container.innerHTML = html;
    updatePendingBadge();
  }

  function renderStudentList() {
    var list = $('studentList');
    var data = getStudents();
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state"><i class="bi bi-people"></i><p>No students loaded. Sync with server first.</p></div>';
      return;
    }

    // Filter by subject enrollment for irregular students
    var subjectEnrollments = null;
    if (currentSubjectId) {
      var ss = getStudentSubjects();
      if (ss && ss.length > 0) {
        subjectEnrollments = {};
        for (var ei = 0; ei < ss.length; ei++) {
          var e = ss[ei];
          if (!subjectEnrollments[e.qr_code]) subjectEnrollments[e.qr_code] = [];
          subjectEnrollments[e.qr_code].push(Number(e.subject_id));
        }
      }
    }

    var searchVal = ($('searchInput').value || '').toLowerCase().trim();
    var filtered = data.filter(function (s) {
      // Subject enrollment filter for irregular students
      if (currentSubjectId && (s.student_type || '').toLowerCase() === 'irregular') {
        var enrolled = subjectEnrollments ? (subjectEnrollments[s.qr_code] || []) : [];
        if (enrolled.indexOf(Number(currentSubjectId)) === -1) return false;
      }
      if (!searchVal) return true;
      return (s.name || '').toLowerCase().indexOf(searchVal) !== -1
        || (s.qr_code || '').toLowerCase().indexOf(searchVal) !== -1;
    });

    if (filtered.length === 0) {
      list.innerHTML = '<div class="empty-state"><i class="bi bi-search"></i><p>No students found</p></div>';
      updateStats(); return;
    }

    var html = '';
    for (var i = 0; i < filtered.length; i++) {
      var s = filtered[i];
      var qr = s.qr_code;
      var name = escapeHtml(s.name || '');
      var sid = escapeHtml(qr || '');
      var isIrregular = (s.student_type || '').toLowerCase() === 'irregular';
      var cls = '', ap = '', al = '', aa = '';
      if (currentRecords[qr]) {
        cls = ' ' + currentRecords[qr];
        if (currentRecords[qr] === 'present') ap = ' active-p';
        else if (currentRecords[qr] === 'late') al = ' active-l';
        else if (currentRecords[qr] === 'absent') aa = ' active-a';
      }
      html += '<div class="student-row' + cls + '" data-qr="' + escapeHtml(qr) + '">'
        + '<div class="student-line" onclick="showStudentPopup(\'' + escapeHtml(qr) + '\')"><span class="student-name">' + name + '</span>'
        + (isIrregular ? '<span class="student-type-badge">IRR</span>' : '')
        + '<span class="student-id">' + sid + '</span></div>'
        + '<div class="student-actions">'
        + '<button class="btn-stat' + ap + '" onclick="setStatus(\'' + escapeHtml(qr) + '\',\'present\')">P</button>'
        + '<button class="btn-stat' + al + '" onclick="setStatus(\'' + escapeHtml(qr) + '\',\'late\')">L</button>'
        + '<button class="btn-stat' + aa + '" onclick="setStatus(\'' + escapeHtml(qr) + '\',\'absent\')">A</button>'
        + '<button class="btn-stat clear" onclick="setStatus(\'' + escapeHtml(qr) + '\',\'clear\')"><i class="bi bi-x"></i></button>'
        + '</div></div>';
    }
    list.innerHTML = html;
    updateFooterInfo();
    updateStats();
  }

  window.setStatus = function (qr, status) {
    var row = document.querySelector('.student-row[data-qr="' + CSS.escape(qr) + '"]');
    var btns = row ? row.querySelectorAll('.btn-stat') : [];

    if (status === 'clear') {
      delete currentRecords[qr];
      if (row) {
        row.classList.remove('present', 'late', 'absent');
        for (var b = 0; b < 3; b++) btns[b].className = 'btn-stat';
      }
    } else {
      var prev = currentRecords[qr];
      if (prev === status) {
        delete currentRecords[qr];
        if (row) {
          row.classList.remove(status);
          for (var b = 0; b < 3; b++) btns[b].className = 'btn-stat';
        }
      } else {
        currentRecords[qr] = status;
        if (row) {
          row.classList.remove('present', 'late', 'absent');
          row.classList.add(status);
          btns[0].className = 'btn-stat' + (status === 'present' ? ' active-p' : '');
          btns[1].className = 'btn-stat' + (status === 'late' ? ' active-l' : '');
          btns[2].className = 'btn-stat' + (status === 'absent' ? ' active-a' : '');
        }
      }
    }
    updateFooterInfo();
    updateStats();
  };

  window.bulkSet = function (status) {
    var data = getStudents();
    if (!data) return;
    var searchVal = ($('searchInput').value || '').toLowerCase().trim();
    for (var i = 0; i < data.length; i++) {
      var s = data[i];
      if (searchVal) {
        var match = (s.name || '').toLowerCase().indexOf(searchVal) !== -1
          || (s.qr_code || '').toLowerCase().indexOf(searchVal) !== -1;
        if (!match) continue;
      }
      if (status === 'clear') {
        delete currentRecords[s.qr_code];
      } else {
        currentRecords[s.qr_code] = status;
      }
    }
    renderStudentList();
    showToast((status === 'clear' ? 'Cleared' : 'Set all to ' + status), 'info');
  };

  function updateFooterInfo() {
    var count = Object.keys(currentRecords).length;
    $('recordCount').innerHTML = '<i class="bi bi-journal"></i> ' + count + ' record' + (count !== 1 ? 's' : '');
    $('btnSave').disabled = count === 0;
  }

  function updateStats() {
    var p = 0, l = 0, a = 0;
    for (var q in currentRecords) {
      if (currentRecords[q] === 'present') p++;
      else if (currentRecords[q] === 'late') l++;
      else if (currentRecords[q] === 'absent') a++;
    }
    $('miniPresent').textContent = p;
    $('miniLate').textContent = l;
    $('miniAbsent').textContent = a;
    var data = getStudents();
    var total = (data || []).length;
    $('miniRemaining').textContent = total - p - l - a;
  }

  function showSavePreview() {
    var count = Object.keys(currentRecords).length;
    if (count === 0) return;
    var date = $('attendanceDate').value;
    if (!date) { showToast('Select a date first', 'warning'); return; }

    var p = 0, l = 0, a = 0;
    for (var q in currentRecords) {
      if (currentRecords[q] === 'present') p++;
      else if (currentRecords[q] === 'late') l++;
      else if (currentRecords[q] === 'absent') a++;
    }
    var total = p + l + a;
    var pctP = total > 0 ? Math.round(p / total * 100) : 0;
    var pctL = total > 0 ? Math.round(l / total * 100) : 0;
    var pctA = total > 0 ? Math.round(a / total * 100) : 0;

    $('previewBody').innerHTML = '<div class="preview-stats">'
      + '<div class="preview-stat"><span class="preview-stat-num" style="color:var(--success)">' + p + '</span><span class="preview-stat-label">Present</span></div>'
      + '<div class="preview-stat"><span class="preview-stat-num" style="color:var(--warning)">' + l + '</span><span class="preview-stat-label">Late</span></div>'
      + '<div class="preview-stat"><span class="preview-stat-num" style="color:var(--danger)">' + a + '</span><span class="preview-stat-label">Absent</span></div>'
      + '</div>'
      + '<div class="preview-bar">'
      + '<div class="preview-bar-fill present" style="width:' + pctP + '%"></div>'
      + '<div class="preview-bar-fill late" style="width:' + pctL + '%"></div>'
      + '<div class="preview-bar-fill absent" style="width:' + pctA + '%"></div>'
      + '</div>'
      + '<p style="font-size:0.78rem;color:var(--text-muted);margin-top:8px">' + total + ' student' + (total !== 1 ? 's' : '') + ' on ' + date + '</p>';

    $('savePreviewModal').classList.remove('hidden');
  }

  window.showSavePreview = showSavePreview;

  function closeSavePreview(e) {
    if (e && e.target !== $('savePreviewModal')) return;
    $('savePreviewModal').classList.add('hidden');
  }

  window.closeSavePreview = closeSavePreview;

  function confirmSave() {
    closeSavePreview({ target: $('savePreviewModal') });
    saveLocalRecords();
  }

  window.confirmSave = confirmSave;

  function saveLocalRecords() {
    var count = Object.keys(currentRecords).length;
    if (count === 0) return;

    var date = $('attendanceDate').value;
    if (!date) { showToast('Select a date first', 'warning'); return; }

    var pending = getJson('pending') || [];
    var recordsArray = [];
    for (var q in currentRecords) {
      recordsArray.push({ qr_code: q, status: currentRecords[q], time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) });
    }

    var entry = {
      id: Date.now() + '_' + Math.random().toString(36).substr(2, 4),
      subject_id: currentSubjectId,
      subject_name: $('recordContextTitle').textContent,
      date: date,
      records: recordsArray,
      created_at: new Date().toISOString()
    };

    pending.push(entry);
    setJson('pending', pending);

    currentRecords = {};
    renderStudentList();
    renderSubjects();
    showToast('Saved ' + count + ' records locally', 'success');
  }

  window.saveLocalRecords = saveLocalRecords;

  // Student Popup
  window.showStudentPopup = function (qr) {
    var data = getStudents();
    if (!data) return;
    var student = null;
    for (var i = 0; i < data.length; i++) {
      if (data[i].qr_code == qr) { student = data[i]; break; }
    }
    if (!student) return;

    $('popupQrCode').textContent = student.qr_code || '---';
    $('popupName').textContent = student.name || '---';
    $('popupCourse').textContent = student.course ? 'Course: ' + student.course : '';
    $('popupYear').textContent = student.year ? 'Year: ' + student.year : '';

    // Highlight current status
    var btns = document.querySelectorAll('#studentPopup .popup-actions .btn-stat');
    for (var b = 0; b < btns.length; b++) {
      btns[b].className = 'btn-stat';
    }
    var cur = currentRecords[qr];
    if (cur === 'present') btns[0].className = 'btn-stat active-p';
    else if (cur === 'late') btns[1].className = 'btn-stat active-l';
    else if (cur === 'absent') btns[2].className = 'btn-stat active-a';

    $('studentPopup').dataset.qr = qr;
    $('studentPopup').classList.remove('hidden');
  };

  window.closeStudentPopup = function (e) {
    if (e && e.target !== $('studentPopup')) return;
    $('studentPopup').classList.add('hidden');
  };

  window.popupSetStatus = function (status) {
    var qr = $('studentPopup').dataset.qr;
    if (!qr) return;

    var cur = currentRecords[qr];
    if (cur === status) {
      delete currentRecords[qr];
    } else {
      currentRecords[qr] = status;
    }

    renderStudentList();
    closeStudentPopup({ target: $('studentPopup') });
    showToast('Set ' + status + ' for ' + qr, status === 'present' ? 'success' : status === 'late' ? 'warning' : status === 'absent' ? 'error' : 'info');
  };

  // QR Scanner
  window.openScanner = function () {
    $('scannerOverlay').classList.remove('hidden');
    $('scanFeedback').classList.add('hidden');
    $('manualQrInput').value = '';
    $('manualQrInput').focus();
    $('manualQrInput').onkeydown = function (e) { if (e.key === 'Enter') submitManualQr(); };

    if (typeof Html5Qrcode === 'undefined') {
      showToast('QR scanner library not loaded', 'error');
      return;
    }

    if (html5QrCode) {
      html5QrCode.stop().catch(function () {});
      html5QrCode = null;
    }

    try {
      html5QrCode = new Html5Qrcode('qrScannerContainer');
      isScanning = true;

      html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 15, qrbox: { width: 220, height: 220 } },
        function (decodedText) {
          if (isScanning) {
            isScanning = false;
            onScanSuccess(decodedText);
          }
        },
        function () {}
      ).catch(function () {
        showToast('Camera unavailable - use manual entry', 'warning');
      });
    } catch (e) {
      showToast('Camera error - use manual entry', 'warning');
    }
  };

  window.closeScanner = function () {
    if (html5QrCode) {
      html5QrCode.stop().catch(function () {});
      html5QrCode = null;
    }
    isScanning = false;
    $('scannerOverlay').classList.add('hidden');
  };

  window.submitManualQr = function () {
    var val = $('manualQrInput').value.trim();
    if (!val) { showToast('Enter a QR code', 'warning'); return; }
    onScanSuccess(val);
    $('manualQrInput').value = '';
  };

  function onScanSuccess(qrCode) {
    var feedback = $('scanFeedback');
    var data = getStudents();
    var found = false;

    if (data) {
      for (var i = 0; i < data.length; i++) {
        if (data[i].qr_code == qrCode) { found = true; break; }
      }
    }

    if (!found) {
      feedback.className = 'scan-feedback error';
      feedback.textContent = 'NOT FOUND';
      feedback.classList.remove('hidden');
      if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
      setTimeout(function () { feedback.classList.add('hidden'); if (isScanning === false) isScanning = true; }, 1200);
      return;
    }

    // Toggle status
    var cur = currentRecords[qrCode];
    if (!cur || cur === 'clear') {
      currentRecords[qrCode] = 'present';
      feedback.textContent = 'PRESENT';
      feedback.className = 'scan-feedback success';
    } else if (cur === 'present') {
      currentRecords[qrCode] = 'late';
      feedback.textContent = 'LATE';
      feedback.className = 'scan-feedback success';
    } else if (cur === 'late') {
      currentRecords[qrCode] = 'absent';
      feedback.textContent = 'ABSENT';
      feedback.className = 'scan-feedback success';
    } else {
      delete currentRecords[qrCode];
      feedback.textContent = 'CLEARED';
      feedback.className = 'scan-feedback success';
    }

    feedback.classList.remove('hidden');
    if (navigator.vibrate) navigator.vibrate(50);
    renderStudentList();
    updateFooterInfo();
    updateStats();

    setTimeout(function () {
      feedback.classList.add('hidden');
      if (currentView === 'record') {
        if (isScanning === false) isScanning = true;
      }
    }, 800);
  }

  // Queue View
  window.showQueueView = function () { switchTab('pending'); };

  function renderQueueList() {
    var container = $('queueList');
    var pending = getJson('pending') || [];
    $('queueCount').textContent = pending.length;

    if (pending.length === 0) {
      container.innerHTML = '<div class="empty-state"><i class="bi bi-check-circle"></i><p>All records synced!</p></div>';
      return;
    }

    var html = '';
    for (var i = 0; i < pending.length; i++) {
      var e = pending[i];
      var records = e.records || [];
      var p = 0, l = 0, a = 0;
      for (var r = 0; r < records.length; r++) {
        if (records[r].status === 'present') p++;
        else if (records[r].status === 'late') l++;
        else if (records[r].status === 'absent') a++;
      }
      html += '<div class="queue-item">'
        + '<div class="queue-item-info">'
        + '<div class="queue-item-subject">' + escapeHtml(e.subject_name || 'Daily') + '</div>'
        + '<div class="queue-item-date">' + escapeHtml(e.date) + '</div>'
        + '<div class="queue-item-stats"><span style="color:var(--success)">P:' + p + '</span> <span style="color:var(--warning)">L:' + l + '</span> <span style="color:var(--danger)">A:' + a + '</span></div>'
        + '</div>'
        + '<button class="btn-icon" onclick="deleteQueueItem(\'' + e.id + '\')" title="Delete"><i class="bi bi-trash"></i></button>'
        + '</div>';
    }
    container.innerHTML = html;
    updatePendingBadge();
  }

  window.deleteQueueItem = function (id) {
    var pending = getJson('pending') || [];
    var filtered = pending.filter(function (e) { return e.id != id; });
    setJson('pending', filtered);
    renderQueueList();
    renderSubjects();
    showToast('Record removed from queue', 'info');
  };

  window.retryAllFailed = function () {
    syncAll();
  };

  window.clearAllPending = function () {
    if (!confirm('Clear all pending records?')) return;
    setJson('pending', []);
    renderQueueList();
    renderSubjects();
    showToast('All pending records cleared', 'info');
  };

  // History View
  window.showHistoryView = function () { switchTab('history'); };

  window.switchHistoryTab = function (tab) {
    var tabs = document.querySelectorAll('.history-tab');
    for (var i = 0; i < tabs.length; i++) {
      tabs[i].classList.toggle('active', tabs[i].dataset.tab === tab);
    }
    if (tab === 'synced') {
      renderSyncedHistory();
    } else {
      renderPendingHistory();
    }
  };

  function renderSyncedHistory() {
    var container = $('historyContent');
    container.innerHTML = '<div class="loading-state"><i class="bi bi-arrow-repeat spin"></i><p>Loading history...</p></div>';

    var url = apiUrl('api/mobile_attendance.php');
    var token = getApiToken();

    if (!url || !token) {
      container.innerHTML = '<div class="empty-state"><i class="bi bi-cloud-off"></i><p>Connect to server to view history</p></div>';
      return;
    }

    var fetchUrl = url + '?action=history&api_key=' + encodeURIComponent(token);
    fetch(fetchUrl, { method: 'GET', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (data) {
        if (data.status !== 'success' || !data.history || data.history.length === 0) {
          container.innerHTML = '<div class="empty-state"><i class="bi bi-archive"></i><p>No history yet</p></div>';
          return;
        }
        var html = '';
        for (var i = 0; i < data.history.length; i++) {
          var h = data.history[i];
          html += '<div class="history-item" onclick="this.classList.toggle(\'open\')">'
            + '<div class="history-item-top">'
            + '<div class="history-item-title">' + escapeHtml(h.subject_name || 'Daily') + '</div>'
            + '<span class="history-item-type">' + escapeHtml(h.type || 'daily') + '</span>'
            + '</div>'
            + '<div class="history-item-date">' + escapeHtml(h.date) + '</div>'
            + '<div class="history-item-stats">'
            + '<span style="color:var(--success)">P:' + (h.present || 0) + '</span>'
            + '<span style="color:var(--warning)">L:' + (h.late || 0) + '</span>'
            + '<span style="color:var(--danger)">A:' + (h.absent || 0) + '</span>'
            + '</div>'
            + '<div class="history-item-expanded"></div>'
            + '</div>';
        }
        container.innerHTML = html;
      })
      .catch(function () {
        container.innerHTML = '<div class="error-state"><i class="bi bi-exclamation-triangle"></i><p>Failed to load history</p></div>';
      });
  }

  function renderPendingHistory() {
    var container = $('historyContent');
    var pending = getJson('pending') || [];
    if (pending.length === 0) {
      container.innerHTML = '<div class="empty-state"><i class="bi bi-check-circle"></i><p>No pending records</p></div>';
      return;
    }
    var html = '';
    for (var i = 0; i < pending.length; i++) {
      var e = pending[i];
      var records = e.records || [];
      var p = 0, l = 0, a = 0;
      for (var r = 0; r < records.length; r++) {
        if (records[r].status === 'present') p++;
        else if (records[r].status === 'late') l++;
        else if (records[r].status === 'absent') a++;
      }
      html += '<div class="history-item">'
        + '<div class="history-item-top">'
        + '<div class="history-item-title">' + escapeHtml(e.subject_name || 'Daily') + '</div>'
        + '<span class="history-item-type" style="background:rgba(234,179,8,0.15);color:var(--warning)">Pending</span>'
        + '</div>'
        + '<div class="history-item-date">' + escapeHtml(e.date) + '</div>'
        + '<div class="history-item-stats">'
        + '<span style="color:var(--success)">P:' + p + '</span>'
        + '<span style="color:var(--warning)">L:' + l + '</span>'
        + '<span style="color:var(--danger)">A:' + a + '</span>'
        + '</div>'
        + '</div>';
    }
    container.innerHTML = html;
  }

  // Theme
  window.toggleTheme = function () {
    var enabled = $('themeToggle').checked;
    document.documentElement.setAttribute('data-theme', enabled ? 'light' : '');
    setVal('theme', enabled ? 'light' : '');
  };

  function loadTheme() {
    var theme = getVal('theme');
    var isLight = theme === 'light';
    $('themeToggle').checked = isLight;
    document.documentElement.setAttribute('data-theme', isLight ? 'light' : '');
  }

  // Sync
  async function syncAll() {
    var pending = getJson('pending') || [];
    var url = apiUrl('api/mobile_attendance.php');
    var token = getApiToken();

    if (!url || !token) {
      showToast('Configure server URL and API key in Settings', 'error');
      toggleSettings();
      return;
    }

    var overlay = showOverlay('Syncing...', pending.length + ' pending records');

    var synced = 0;
    var failed = [];
    var remaining = [];

    for (var i = 0; i < pending.length; i++) {
      var entry = pending[i];
      var payload = {
        api_key: token,
        date: entry.date,
        records: entry.records.map(function (r) { return { qr_code: r.qr_code, status: r.status }; })
      };
      if (entry.subject_id !== null && entry.subject_id !== undefined) {
        payload.subject_id = entry.subject_id;
      }

      try {
        var res = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        var data = await res.json();
        if (data.status === 'success') {
          synced += data.processed || 0;
        } else {
          failed.push(entry);
          if (data.message) showToast('Sync error: ' + data.message, 'error');
        }
      } catch (e) {
        failed.push(entry);
        showToast('Sync failed: ' + e.message, 'error');
      }
    }

    if (failed.length > 0) {
      remaining = pending.filter(function (e) { return failed.indexOf(e) === -1; });
    }

    // Refresh data from server
    try {
      var res = await fetch(url + '?api_key=' + encodeURIComponent(token), { method: 'GET', cache: 'no-store' });
      if (res.ok) {
        var data = await res.json();
        if (data.status === 'success') {
          if (data.students) { students = data.students; setJson('students', data.students); }
          if (data.subjects) { subjects = data.subjects; setJson('subjects', data.subjects); }
          if (data.schedules) { schedules = data.schedules; setJson('schedules', data.schedules); renderSchedule(); }
          if (data.student_subjects) { setJson('student_subjects', data.student_subjects); }
          setVal('lastSync', String(Date.now()));
          setConnectionStatus(true);
        }
      }
    } catch (e) {}

    if (failed.length > 0) {
      setJson('pending', remaining);
    } else {
      setJson('pending', []);
    }

    if (overlay.parentElement) overlay.remove();

    if (synced > 0) {
      showToast('Synced ' + synced + ' records successfully', 'success');
    }
    if (failed.length > 0) {
      showToast(failed.length + ' records failed - will retry next sync', 'warning');
    }

    renderSubjects();
    updatePendingBadge();
    if (currentView === 'queue') renderQueueList();
  }

  window.syncAll = syncAll;

  async function testConnection() {
    var url = apiUrl('api/mobile_attendance.php');
    var token = getApiToken();
    if (!url || !token) { showToast('Set URL and API key first', 'warning'); return; }

    var overlay = showOverlay('Testing...', url);
    try {
      var res = await fetch(url + '?api_key=' + encodeURIComponent(token), { method: 'GET', cache: 'no-store' });
      if (overlay.parentElement) overlay.remove();
      var text = await res.text();
      // Try to parse as JSON
      try {
        var data = JSON.parse(text);
        if (data.status === 'success') {
          showToast('Connected! ' + (data.subjects ? data.subjects.length : 0) + ' subjects, ' + (data.students ? data.students.length : 0) + ' students', 'success');
        } else {
          showToast('Server error: ' + (data.message || 'unknown'), 'error');
        }
      } catch (e) {
        // Show raw response preview
        var preview = text.substring(0, 300);
        showToast('Not JSON. Server returned (status ' + res.status + '): ' + preview, 'error');
      }
    } catch (e) {
      if (overlay.parentElement) overlay.remove();
      showToast('Cannot reach server: ' + e.message, 'error');
    }
  }

  window.testConnection = testConnection;

  async function syncAndDownload() {
    var url = apiUrl('api/mobile_attendance.php');
    var token = getApiToken();
    if (!url || !token) return;

    var overlay = showOverlay('Connecting...', 'Downloading students & subjects');

    try {
      var res = await fetch(url + '?api_key=' + encodeURIComponent(token), { method: 'GET', cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status + ' - server may be unreachable');
      var text = await res.text();
      var data;
      try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON response: ' + text.substring(0, 200)); }
      if (data.status === 'success') {
        if (data.students) { students = data.students; setJson('students', data.students); }
        if (data.subjects) { subjects = data.subjects; setJson('subjects', data.subjects); }
        if (data.schedules) { schedules = data.schedules; setJson('schedules', data.schedules); }
        if (data.student_subjects) { setJson('student_subjects', data.student_subjects); }
        setVal('lastSync', String(Date.now()));
        setConnectionStatus(true);
        renderSchedule();
        renderSubjects();
        if (overlay.parentElement) overlay.remove();
        toggleSettings();
        showToast('Data downloaded - you can now work offline', 'success');
        // Also download the SQLite database for direct queries
        downloadDatabase();
        return;
      }
      throw new Error('Server returned: ' + (data.message || 'invalid response'));
    } catch (e) {
      if (overlay.parentElement) overlay.remove();
      showToast('Connection failed: ' + e.message, 'error');
    }
  }

  function toggleSettings() {
    var panel = $('settingsPanel');
    var isOpen = panel.classList.contains('open');
    if (!isOpen) {
      $('serverUrl').value = getServerUrl();
      $('apiKey').value = getApiToken();
      panel.classList.remove('hidden');
      requestAnimationFrame(function () { panel.classList.add('open'); });
    } else {
      panel.classList.remove('open');
      setTimeout(function () { panel.classList.add('hidden'); }, 300);
    }
  }

  window.toggleSettings = toggleSettings;

  function saveSettings() {
    var url = $('serverUrl').value.replace(/\/+$/, '');
    var token = $('apiKey').value.trim();
    setVal('serverUrl', url);
    setVal('apiKey', token);

    if (url && token) {
      syncAndDownload();
    } else {
      toggleSettings();
      showToast('Settings saved', 'success');
    }
  }

  window.saveSettings = saveSettings;

  function updateDataStatus() {
    var s = getStudents().length;
    var j = getSubjects().length;
    var el = $('dataStatus');
    if (el) el.textContent = s + ' students, ' + j + ' subjects';
  }

  function doImport(data) {
    if (!data.students && !data.subjects) {
      showToast('JSON must contain "students" or "subjects" array', 'error');
      return false;
    }
    if (data.students) { students = data.students; setJson('students', data.students); }
    if (data.subjects) { subjects = data.subjects; setJson('subjects', data.subjects); }
    if (data.schedules) { schedules = data.schedules; setJson('schedules', data.schedules); }
    if (data.student_subjects) { setJson('student_subjects', data.student_subjects); }
    setVal('lastSync', String(Date.now()));
    setConnectionStatus(true);
    dbReady = false; // imported JSON overrides SQLite
    sqlDb = null;
    renderSchedule();
    renderSubjects();
    updateDataStatus();
    return true;
  }

  window.showPasteImport = function () {
    var area = $('pasteArea');
    area.classList.toggle('hidden');
    if (!area.classList.contains('hidden')) $('pasteInput').focus();
  };

  window.importFromPaste = function () {
    var raw = $('pasteInput').value.trim();
    if (!raw) { showToast('Paste JSON data first', 'warning'); return; }
    try {
      var data = JSON.parse(raw);
      if (doImport(data)) {
        $('pasteArea').classList.add('hidden');
        $('pasteInput').value = '';
        toggleSettings();
        showToast(dataPreviewCount(data), 'success');
      }
    } catch (err) {
      showToast('Invalid JSON: ' + err.message, 'error');
    }
  };

  window.clearLocalData = function () {
    if (!confirm('Remove all local data (students, subjects, schedules)?')) return;
    students = []; subjects = []; schedules = [];
    setJson('students', []); setJson('subjects', []); setJson('schedules', []);
    setVal('lastSync', '');
    setConnectionStatus(false);
    dbReady = false; sqlDb = null;
    renderSchedule(); renderSubjects(); updateDataStatus();
    showToast('Local data cleared', 'info');
  };

  function dataPreviewCount(data) {
    var s = (data.students || []).length;
    var j = (data.subjects || []).length;
    var c = (data.schedules || []).length;
    return 'Imported: ' + s + ' students, ' + j + ' subjects' + (c ? ', ' + c + ' schedules' : '');
  }

  window.importFromFile = function (e) {
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (ev) {
      try {
        var data = JSON.parse(ev.target.result);
        if (!data.students && !data.subjects) {
          showToast('File must contain "students" or "subjects" array', 'error');
          return;
        }
        if (data.students) { students = data.students; setJson('students', data.students); }
        if (data.subjects) { subjects = data.subjects; setJson('subjects', data.subjects); }
        if (data.schedules) { schedules = data.schedules; setJson('schedules', data.schedules); }
        setVal('lastSync', String(Date.now()));
        setConnectionStatus(true);
        renderSchedule();
        renderSubjects();
        toggleSettings();
        showToast(dataPreviewCount(data), 'success');
      } catch (err) {
        showToast('Invalid JSON: ' + err.message, 'error');
      }
    };
    reader.readAsText(file);
    e.target.value = '';
  };

  window.exportData = function () {
    var data = {
      students: getStudents(),
      subjects: getSubjects(),
      schedules: getSchedules(),
      student_subjects: getStudentSubjects(),
      exported_at: new Date().toISOString()
    };
    var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'attendance-backup-' + new Date().toISOString().slice(0, 10) + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast(dataPreviewCount(data) + ' — file saved', 'success');
  };

  function init() {
    // Set default date
    var today = new Date();
    var y = today.getFullYear();
    var m = String(today.getMonth() + 1).padStart(2, '0');
    var d = String(today.getDate()).padStart(2, '0');
    $('attendanceDate').value = y + '-' + m + '-' + d;

    // Auto-detect server URL if served from HTTP origin
    var origin = window.location.origin || '';
    if (origin && origin.indexOf('http') === 0 && !getServerUrl()) {
      setVal('serverUrl', origin);
      setConnectionStatus(true);
      $('appStatusText').textContent = 'Auto';
      // Try to load the actual SQLite database
      downloadDatabase();
    }

    $('attendanceDate').addEventListener('change', function () {
      if (currentView === 'home') {
        renderSchedule();
      }
      if (currentView === 'record') {
        currentRecords = {};
        renderStudentList();
      }
    });

    $('searchInput').addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(renderStudentList, 150);
    });

    // Online/offline auto-sync
    window.addEventListener('online', function () {
      setConnectionStatus(true);
      if (autoSyncTimer) clearTimeout(autoSyncTimer);
      autoSyncTimer = setTimeout(function () {
        var pending = getJson('pending') || [];
        if (pending.length > 0) {
          syncAll();
        }
      }, 5000);
    });

    window.addEventListener('offline', function () {
      setConnectionStatus(false);
    });

    // Restore cached data
    var cachedStudents = getJson('students');
    var cachedSubjects = getJson('subjects');
    var cachedSchedules = getJson('schedules');
    if (cachedStudents) students = cachedStudents;
    if (cachedSubjects) subjects = cachedSubjects;
    if (cachedSchedules) schedules = cachedSchedules;

    loadTheme();
    setActiveNav('home');
    renderSchedule();
    renderSubjects();
    updatePendingBadge();

    // Check if server is configured
    var hasServer = !!getServerUrl() && !!getApiToken();
    if (hasServer) {
      fetch(apiUrl('api/mobile_attendance.php') + '?api_key=' + encodeURIComponent(getApiToken()), { method: 'GET', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (data) {
          if (data.status === 'success') {
            if (data.students) { students = data.students; setJson('students', data.students); }
            if (data.subjects) { subjects = data.subjects; setJson('subjects', data.subjects); }
            if (data.schedules) { schedules = data.schedules; setJson('schedules', data.schedules); renderSchedule(); }
            if (data.student_subjects) { setJson('student_subjects', data.student_subjects); }
            setVal('lastSync', String(Date.now()));
            setConnectionStatus(true);
            renderSubjects();
            downloadDatabase();
          }
        }).catch(function () { setConnectionStatus(false); });
    } else {
      setConnectionStatus(false);
    }

    // After splash screen
    setTimeout(function () {
      $('splashScreen').classList.add('hidden');
      $('app').classList.remove('hidden');
    }, 1600);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
