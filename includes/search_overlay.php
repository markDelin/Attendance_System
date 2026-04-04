<!-- includes/search_overlay.php -->
<div id="globalSearchOverlay" class="search-overlay" onclick="if(event.target === this) closeGlobalSearch()">
    <div class="search-modal">
        <div class="search-box-container">
            <i class="bi bi-search"></i>
            <input type="text" id="globalSearchInput" class="search-box-input" placeholder="Search students by name or QR..." autocomplete="off">
        </div>
        <div class="search-hint">Press <kbd>Esc</kbd> to close</div>
        <div id="globalSearchResults" class="search-results-list"></div>
    </div>
</div>

<script>
    const searchOverlay = document.getElementById('globalSearchOverlay');
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('globalSearchResults');
    let searchTimeout = null;

    function openGlobalSearch() {
        searchOverlay.style.display = 'flex';
        searchInput.value = '';
        searchResults.innerHTML = '';
        setTimeout(() => searchInput.focus(), 50);
    }

    function closeGlobalSearch() {
        searchOverlay.style.display = 'none';
    }

    // Keyboard Shortcuts
    document.addEventListener('keydown', (e) => {
        // Ctrl+K or Cmd+K to open
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openGlobalSearch();
        }
        // Esc to close
        if (e.key === 'Escape' && searchOverlay.style.display === 'flex') {
            closeGlobalSearch();
        }
    });

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        clearTimeout(searchTimeout);

        if (query.length < 2) {
            searchResults.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`api/search_students.php?q=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    renderResults(res.data);
                }
            });
        }, 300);
    });

    function renderResults(data) {
        if (data.length === 0) {
            searchResults.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted);">No students found.</div>';
            return;
        }

        searchResults.innerHTML = data.map(s => `
            <a href="profile.php?qr=${encodeURIComponent(s.qr_code)}" class="search-result-item">
                <div class="avatar">${s.name.charAt(0).toUpperCase()}</div>
                <div class="info">
                    <b>${s.name}</b>
                    <small>${s.qr_code}</small>
                </div>
            </a>
        `).join('');
    }
</script>
