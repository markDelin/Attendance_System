(function () {
  'use strict';

  const KEYS = {
    serverUrl: 'app_server_url',
    apiKey: 'app_api_key',
    students: 'app_students',
    subjects: 'app_subjects',
    pending: 'app_pending_records',
    lastSync: 'app_last_sync'
  };

  let students = [];
  let subjects = [];
  let currentView = 'home';
  let currentSubjectId = null;
  let currentRecords = {};

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

  function escapeHtml(s) { if (!s) return ''; return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

  function showOverlay(msg, sub) {
    var o = document.createElement('div'); o.className = 'sync-overlay';
    o.innerHTML = '<div class="spinner"></div><p>' + msg + '</p>' + (sub ? '<small>' + sub + '</small>' : '');
    document.body.appendChild(o); return o;
  }

  function updatePendingBadge() {
    var pending = getJson('pending') || [];
    var badge = $('pendingBadge');
    var info = $('pendingInfo');
    if (pending.length > 0) {
      badge.classList.remove('hidden');
      info.classList.remove('hidden');
      info.textContent = pending.length + ' pending';
    } else {
      badge.classList.add('hidden');
      info.classList.add('hidden');
    }
  }

  function setConnectionStatus(online) {
    $('appStatus').className = 'status-dot ' + (online ? 'status-online' : 'status-offline');
    $('appStatusText').textContent = online ? 'Online' : 'Offline';
  }

  function showHome() {
    currentView = 'home';
    currentSubjectId = null;
    $('homeView').classList.remove('hidden');
    $('recordView').classList.add('hidden');
    $('btnBack').classList.add('hidden');
    $('appTitle').textContent = 'Attendance';
    $('appFooter').classList.remove('hidden');
    renderSubjects();
    updatePendingBadge();
  }

  function goHome() { showHome(); }

  window.goHome = goHome;

  function showRecordView() {
    currentView = 'record';
    $('homeView').classList.add('hidden');
    $('recordView').classList.remove('hidden');
    $('btnBack').classList.remove('hidden');
    $('appFooter').classList.remove('hidden');
  }

  function openSubject(subjectId, subjectName) {
    currentSubjectId = subjectId;
    currentRecords = {};
    showRecordView();
    $('appTitle').textContent = subjectName;
    $('recordContextTitle').textContent = subjectName;
    $('studentList').innerHTML = '';
    renderStudentList();
  }

  window.openSubject = openSubject;

  function openDaily() {
    currentSubjectId = null;
    currentRecords = {};
    showRecordView();
    $('appTitle').textContent = 'Daily Attendance';
    $('recordContextTitle').textContent = 'General Attendance';
    $('studentList').innerHTML = '';
    renderStudentList();
  }

  window.openDaily = openDaily;

  function renderSubjects() {
    var container = $('subjectList');
    var data = getJson('subjects');
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

    var html = '';
    for (var i = 0; i < data.length; i++) {
      var s = data[i];
      var hasPending = false;
      for (var p = 0; p < pending.length; p++) {
        if (pending[p].subject_id == s.id) { hasPending = true; break; }
      }
      var meta = [];
      if (s.code) meta.push(s.code);
      if (s.semester) meta.push(s.semester);
      if (s.lecturer) meta.push(s.lecturer);
      html += '<div class="subject-card' + (hasPending ? ' has-records' : '') + '" onclick="openSubject(' + s.id + ',\'' + escapeHtml(s.name) + '\')">'
        + '<div class="subject-icon"><i class="bi bi-journal-text"></i></div>'
        + '<div class="subject-info"><div class="subject-name">' + escapeHtml(s.name) + '</div>'
        + '<div class="subject-meta">' + escapeHtml(meta.join(' \u00B7 ')) + '</div></div>'
        + (hasPending ? '<span class="subject-status"><i class="bi bi-clock"></i> Pending</span>' : '')
        + '<i class="bi bi-chevron-right subject-arrow"></i></div>';
    }
    container.innerHTML = html;
    updatePendingBadge();
  }

  function renderStudentList() {
    var list = $('studentList');
    var data = getJson('students');
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state"><i class="bi bi-people"></i><p>No students loaded. Sync with server first.</p></div>';
      return;
    }

    var searchVal = ($('searchInput').value || '').toLowerCase().trim();
    var filtered = data.filter(function (s) {
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
      var cls = '', ap = '', al = '', aa = '';
      if (currentRecords[qr]) {
        cls = ' ' + currentRecords[qr];
        if (currentRecords[qr] === 'present') ap = ' active-p';
        else if (currentRecords[qr] === 'late') al = ' active-l';
        else if (currentRecords[qr] === 'absent') aa = ' active-a';
      }
      html += '<div class="student-row' + cls + '" data-qr="' + escapeHtml(qr) + '">'
        + '<div class="student-info"><div class="student-name">' + name + '</div><div class="student-id">' + sid + '</div></div>'
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
    var data = getJson('students');
    var total = (data || []).length;
    $('miniRemaining').textContent = total - p - l - a;
  }

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
        if (!res.ok) throw new Error('HTTP ' + res.status);
        var data = await res.json();
        if (data.status === 'success') {
          synced += data.processed || 0;
        } else {
          failed.push(entry);
        }
      } catch (e) {
        failed.push(entry);
      }
    }

    if (failed.length > 0) {
      remaining = pending.filter(function (e) { return failed.indexOf(e) === -1; });
    }

    // Refresh data from server
    try {
      var res = await fetch(url, { method: 'GET', cache: 'no-store' });
      if (res.ok) {
        var data = await res.json();
        if (data.status === 'success') {
          if (data.students) { students = data.students; setJson('students', data.students); }
          if (data.subjects) { subjects = data.subjects; setJson('subjects', data.subjects); }
          setVal('lastSync', String(Date.now()));
          setConnectionStatus(true);
        }
      }
    } catch (e) {}

    setJson('pending', remaining);
    if (overlay.parentElement) overlay.remove();

    if (synced > 0) {
      showToast('Synced ' + synced + ' records successfully', 'success');
    }
    if (failed.length > 0) {
      showToast(failed.length + ' records failed - will retry next sync', 'warning');
    }

    renderSubjects();
    updatePendingBadge();
  }

  window.syncAll = syncAll;

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

  async function syncAndDownload() {
    var url = apiUrl('api/mobile_attendance.php');
    var token = getApiToken();
    if (!url || !token) return;

    var overlay = showOverlay('Connecting...', 'Downloading students & subjects');

    try {
      var res = await fetch(url, { method: 'GET', cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var data = await res.json();
      if (data.status === 'success') {
        if (data.students) { students = data.students; setJson('students', data.students); }
        if (data.subjects) { subjects = data.subjects; setJson('subjects', data.subjects); }
        setVal('lastSync', String(Date.now()));
        setConnectionStatus(true);
        renderSubjects();
        if (overlay.parentElement) overlay.remove();
        toggleSettings();
        showToast('Data downloaded - you can now work offline', 'success');
        return;
      }
      throw new Error('Invalid response');
    } catch (e) {
      if (overlay.parentElement) overlay.remove();
      showToast('Connection failed: ' + e.message, 'error');
    }
  }

  function init() {
    // Set default date
    var today = new Date();
    var y = today.getFullYear();
    var m = String(today.getMonth() + 1).padStart(2, '0');
    var d = String(today.getDate()).padStart(2, '0');
    $('attendanceDate').value = y + '-' + m + '-' + d;

    $('attendanceDate').addEventListener('change', function () {
      if (currentView === 'record') {
        currentRecords = {};
        renderStudentList();
      }
    });

    var searchTimer;
    $('searchInput').addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(renderStudentList, 150);
    });

    // Restore cached data
    var cachedStudents = getJson('students');
    var cachedSubjects = getJson('subjects');
    if (cachedStudents) students = cachedStudents;
    if (cachedSubjects) subjects = cachedSubjects;

    renderSubjects();
    updatePendingBadge();

    // Check if server is configured
    var hasServer = !!getServerUrl() && !!getApiToken();
    if (hasServer) {
      // Try to connect silently
      fetch(apiUrl('api/mobile_attendance.php'), { method: 'GET', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (data) {
          if (data.status === 'success') {
            if (data.students) { students = data.students; setJson('students', data.students); }
            if (data.subjects) { subjects = data.subjects; setJson('subjects', data.subjects); }
            setVal('lastSync', String(Date.now()));
            setConnectionStatus(true);
            renderSubjects();
          }
        }).catch(function () { setConnectionStatus(false); });
    } else {
      setConnectionStatus(false);
    }

    // After splash screen
    setTimeout(function () {
      $('splashScreen').classList.add('hidden');
      $('app').classList.remove('hidden');
    }, 2200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
