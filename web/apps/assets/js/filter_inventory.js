/**
 * filter_inventory.js
 * - client-side filters (kategori, judul, author, publisher)
 * - compact mode when viewport < 900 (shows only judul + icon buttons)
 * - simple browser/device support check (block small phone UA)
 */

document.addEventListener('DOMContentLoaded', function () {
    const rowsSelector = 'tr.main-row';
    const getMainRows = () => Array.from(document.querySelectorAll(rowsSelector));

    const elKategori = document.getElementById('filterKategori');
    const elTitle = document.getElementById('searchTitle');
    const elAuthor = document.getElementById('searchAuthor');
    const elPublisher = document.getElementById('searchPublisher');

    const btnApply = document.getElementById('btnApplyFilters');
    const btnClear = document.getElementById('btnClearFilters');

    const filtersBlock = document.getElementById('filtersBlock');
    const mainContainer = document.querySelector('.container-fluid');

    // Browser / device check: block small-phone (Mobi + small width)
    (function checkSupport() {
        const ua = navigator.userAgent || navigator.vendor || window.opera;
        const isMobileUA = /Mobi|Android|iPhone|iPod|Opera Mini/i.test(ua);
        const smallScreen = (window.innerWidth || screen.width) < 768;
        if (isMobileUA && smallScreen) {
            // Hide main content and show friendly message
            if (mainContainer) mainContainer.style.display = 'none';
            Swal.fire({
                icon: 'warning',
                title: 'Perangkat Tidak Didukung',
                html: 'Aplikasi ini dioptimalkan untuk tablet, laptop, dan desktop. Silakan buka dari perangkat yang lebih besar (Chrome, Edge, Safari).',
                allowOutsideClick: false,
                confirmButtonText: 'Tutup',
            }).then(() => {
                // keep user on page but hidden; optional redirect to help page
            });
            return;
        }

        // Notify if browser not one of Chrome / Edge / Safari (desktop/tablet)
        const allowedBrowsers = /(Chrome|Chromium|Edg|Safari)/i;
        if (!allowedBrowsers.test(ua)) {
            // non-blocking warning
            Swal.fire({
                icon: 'info',
                title: 'Browser Tidak Diuji',
                text: 'Disarankan menggunakan Chrome, Edge, atau Safari untuk pengalaman terbaik.',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
    })();

    function normalize(s) {
        return (s || '').toString().toLowerCase().trim();
    }

    function matchRow(row, filters) {
        const titleEl = row.querySelector('td .fw-bold');
        const metaSmall = row.querySelector('td small');
        const publisherEl = row.children[1];
        const categoryEl = row.children[2];

        const titleText = normalize(titleEl ? titleEl.textContent : row.textContent);
        const metaText = normalize(metaSmall ? metaSmall.textContent : '');
        const pubText = normalize(publisherEl ? publisherEl.textContent : '');
        const catText = normalize(categoryEl ? categoryEl.textContent : '');

        if (filters.title && !titleText.includes(filters.title)) return false;
        if (filters.author && !metaText.includes(filters.author)) return false;
        if (filters.publisher && !pubText.includes(filters.publisher)) return false;
        if (filters.kategori && !catText.includes(filters.kategori)) return false;

        return true;
    }

    function applyFilters(opts = null) {
        const filters = opts || {
            kategori: normalize(elKategori ? elKategori.value : ''),
            title: normalize(elTitle ? elTitle.value : ''),
            author: normalize(elAuthor ? elAuthor.value : ''),
            publisher: normalize(elPublisher ? elPublisher.value : '')
        };

        const rows = getMainRows();
        rows.forEach((row) => {
            const detail = row.nextElementSibling;
            if (matchRow(row, filters)) {
                row.classList.remove('d-none');
                if (detail && detail.dataset.expanded !== '1') detail.classList.add('d-none');
            } else {
                row.classList.add('d-none');
                if (detail) detail.classList.add('d-none');
            }
        });
    }

    function clearFilters() {
        if (elKategori) elKategori.value = '';
        if (elTitle) elTitle.value = '';
        if (elAuthor) elAuthor.value = '';
        if (elPublisher) elPublisher.value = '';
        applyFilters({ kategori: '', title: '', author: '', publisher: '' });
    }

    // Compact mode (only show title + icon buttons)
    function updateCompactMode() {
        if (!filtersBlock) return;
        const compact = window.innerWidth < 900; // adjust threshold if needed
        filtersBlock.classList.toggle('compact-filters', compact);
    }

    // Attach events
    if (btnApply) btnApply.addEventListener('click', function () { applyFilters(); });
    if (btnClear) btnClear.addEventListener('click', function () { clearFilters(); });

    if (elTitle) {
        elTitle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });
    }

    // live filter for author/publisher (debounced)
    function debounce(fn, delay = 250) { let t; return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), delay); }; }
    if (elAuthor) elAuthor.addEventListener('input', debounce(() => applyFilters(), 300));
    if (elPublisher) elPublisher.addEventListener('input', debounce(() => applyFilters(), 300));
    if (elKategori) elKategori.addEventListener('change', () => applyFilters());

    window.addEventListener('resize', updateCompactMode);
    updateCompactMode();

    // initial apply
    applyFilters();
});


/* PAGINATION SCRIPT (5 rows per page) */
document.addEventListener('DOMContentLoaded', function () {
        const rowsPerPage = 5;
        const mainRows = Array.from(document.querySelectorAll('tr.main-row'));
        const paginationEl = document.getElementById('inventoryPagination');
        if (!paginationEl) return;

        const totalItems = mainRows.length;
        const totalPages = Math.max(1, Math.ceil(totalItems / rowsPerPage));

        function showPage(page) {
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            mainRows.forEach((tr, idx) => {
                const detail = tr.nextElementSibling && tr.nextElementSibling.classList.contains('detail-row')
                    ? tr.nextElementSibling
                    : null;

                if (idx >= start && idx < end) {
                    tr.classList.remove('d-none');
                    // restore detail visibility only if previously expanded
                    if (detail) {
                        if (detail.dataset.expanded === '1') detail.classList.remove('d-none'); else detail.classList.add('d-none');
                    }
                } else {
                    tr.classList.add('d-none');
                    if (detail) detail.classList.add('d-none');
                }
            });

            // update active page button
            Array.from(paginationEl.querySelectorAll('li.page-item')).forEach(li => li.classList.remove('active'));
            const activeBtn = paginationEl.querySelector(`li[data-page="${page}"]`);
            if (activeBtn) activeBtn.classList.add('active');
        }

        function buildPagination() {
            paginationEl.innerHTML = '';

            // Prev
            const prevLi = document.createElement('li');
            prevLi.className = 'page-item';
            prevLi.innerHTML = `<a class="page-link" href="#" aria-label="Previous">&laquo;</a>`;
            prevLi.addEventListener('click', (e) => { e.preventDefault(); const cur = getCurrentPage(); if (cur > 1) goToPage(cur - 1); });
            paginationEl.appendChild(prevLi);

            // Pages
            for (let p = 1; p <= totalPages; p++) {
                const li = document.createElement('li');
                li.className = 'page-item';
                li.dataset.page = p;
                li.innerHTML = `<a class="page-link" href="#">${p}</a>`;
                li.addEventListener('click', function (e) {
                    e.preventDefault();
                    goToPage(p);
                });
                paginationEl.appendChild(li);
            }

            // Next
            const nextLi = document.createElement('li');
            nextLi.className = 'page-item';
            nextLi.innerHTML = `<a class="page-link" href="#" aria-label="Next">&raquo;</a>`;
            nextLi.addEventListener('click', (e) => { e.preventDefault(); const cur = getCurrentPage(); if (cur < totalPages) goToPage(cur + 1); });
            paginationEl.appendChild(nextLi);
        }

        function getCurrentPage() {
            const active = paginationEl.querySelector('li.page-item.active');
            return active ? parseInt(active.dataset.page) : 1;
        }

        function goToPage(p) {
            if (p < 1) p = 1;
            if (p > totalPages) p = totalPages;
            showPage(p);
        }

        // Expose toggleRow to keep expand/collapse behavior and mark detail state
        window.toggleRow = function(rowId) {
            const detail = document.getElementById(rowId);
            const icon = document.getElementById('icon-' + rowId.split('-').pop()) || null;
            if (!detail) return;
            const isHidden = detail.classList.contains('d-none');
            if (isHidden) {
                detail.classList.remove('d-none');
                detail.dataset.expanded = '1';
                if (icon) icon.classList.add('rotated');
            } else {
                detail.classList.add('d-none');
                detail.dataset.expanded = '0';
                if (icon) icon.classList.remove('rotated');
            }
        };

        // Initialize
        buildPagination();
        goToPage(1);
});


/* RESET UID SCRIPT */
document.addEventListener('DOMContentLoaded', function(){
        const btn = document.getElementById('btnResetUID');
        const statsEl = document.getElementById('stats_uid_available');
        if (!btn) return;

        btn.addEventListener('click', function(e){
            e.preventDefault();

            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            Swal.fire({
                title: 'Reset expired UID?',
                text: 'UID yang "expired" (pending > 5 menit) akan di-reset timestamp-nya. Lanjutkan?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Reset',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#dc3545',
                customClass: isDark ? { popup: 'swal-dark' } : {}
            }).then((result) => {
                if (!result.isConfirmed) return;

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Resetting...';

                fetch('../includes/api/reset_expired_uid.php?limit=50', {
                    method: 'GET',
                    cache: 'no-store'
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-undo"></i>';

                    if (data && data.success) {
                        // update stats if provided
                        if (statsEl && typeof data.summary !== 'undefined') {
                            // optimistic: subtract reset_count from displayed available if makes sense
                            // if API returns reset_count, decrement; otherwise attempt to use debug value
                            const resetCount = parseInt(data.reset_count || 0);
                            const current = parseInt(statsEl.textContent.replace(/,/g,'')) || 0;
                            statsEl.textContent = Intl.NumberFormat().format(Math.max(0, current - resetCount));
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Selesai',
                            text: data.message || 'UID expired berhasil di-reset',
                            confirmButtonColor: '#41644A',
                            customClass: isDark ? { popup: 'swal-dark' } : {}
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: data.message || 'Reset UID gagal',
                            confirmButtonColor: '#dc3545',
                            customClass: isDark ? { popup: 'swal-dark' } : {}
                        });
                    }
                })
                .catch(err => {
                    console.error('[RESET UID ERROR]', err);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-undo"></i>';
                    const isDarkInner = document.documentElement.getAttribute('data-theme') === 'dark';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi masalah saat menghubungi server',
                        confirmButtonColor: '#dc3545',
                        customClass: isDarkInner ? { popup: 'swal-dark' } : {}
                    });
                });
            });
        });
});