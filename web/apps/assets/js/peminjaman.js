/**
 * Peminjaman App - Console Clean Version
 * API monitoring & statistics dinonaktifkan sementara
 */

const PeminjamanApp = (function() {
    'use strict';

    const state = {
        currentMember: null,
        maxBooks: 0
    };

    function init() {
        console.log('[App] Initializing Peminjaman App...');
        console.log('[App] Monitoring & auto-refresh disabled (API belum ada) - Console bersih!');
        
        setupFilters();  // Filter manual tetap aktif
        
        window.PeminjamanApp = state;
        window.resetMember = resetMember;
        window.refreshMonitoringTable = () => console.log('[App] Manual refresh ignored (API belum ada)');
        window.updateStatistics = () => console.log('[App] Statistics update disabled');
        window.showHelp = showHelp;
        window.exportData = exportData;

        console.log('[App] Initialization complete');
    }

    function resetMember() {
        state.currentMember = null;
        state.maxBooks = 0;
        
        document.getElementById('member-info').classList.add('d-none');
        document.getElementById('member-info').innerHTML = '';
        document.getElementById('book-scan-section').classList.add('disabled-section');
        
        RFIDHandler.updateState('member', 'idle');
        RFIDHandler.updateState('book', 'disabled');
        CartManager.reset();
        
        showToast('info', 'Member direset, siap untuk scan baru');
    }

    function setupFilters() {
        const filter = document.getElementById('filter-status');
        if (filter) {
            filter.addEventListener('change', () => {
                console.log('[App] Filter changed, tapi monitoring disabled');
            });
        }
    }

    function showHelp() {
        Swal.fire({
            title: 'Bantuan Peminjaman',
            html: `
                <div class="text-start">
                    <h6 class="fw-bold mb-3">Cara Menggunakan Sistem:</h6>
                    <div class="mb-3"><strong>1. Scan Kartu Member</strong><p class="text-muted small mb-1">Tempelkan kartu RFID member ke reader</p></div>
                    <div class="mb-3"><strong>2. Scan Buku</strong><p class="text-muted small mb-1">Tempelkan buku yang akan dipinjam (bisa multiple)</p></div>
                    <div class="mb-3"><strong>3. Proses Peminjaman</strong><p class="text-muted small mb-1">Klik tombol "Proses Peminjaman"</p></div>
                    <hr>
                    <h6 class="fw-bold mb-2">Tips:</h6>
                    <ul class="small text-muted">
                        <li>Pastikan RFID reader terhubung</li>
                        <li>Periksa kondisi buku</li>
                        <li>Member dengan denda besar tidak bisa pinjam</li>
                        <li>Gunakan tombol Reset untuk batal</li>
                    </ul>
                </div>
            `,
            icon: 'question',
            confirmButtonText: 'Mengerti',
            width: 600
        });
    }

    function exportData() {
        console.log('[App] Export clicked - API belum ada');
        showToast('info', 'Fitur export akan aktif setelah API dibuat');
    }

    function showToast(type, message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        Toast.fire({ icon: type, title: message });
    }

    window.showToast = showToast;

    return { init, state };
})();

document.addEventListener('DOMContentLoaded', () => PeminjamanApp.init());