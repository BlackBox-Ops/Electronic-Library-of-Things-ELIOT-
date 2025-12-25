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