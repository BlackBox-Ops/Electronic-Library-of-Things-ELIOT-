/**
 * Peminjaman App - Step 1: Member Validation
 * Path: web/apps/assets/js/peminjaman.js
 * 
 * Features:
 * - Member input (manual typing atau RFID scan)
 * - Validasi member via API
 * - Redirect ke biodata_peminjaman.php jika valid
 * - Monitoring table untuk peminjaman aktif
 * - Statistics update dengan auto-refresh
 * - Dark mode support untuk SweetAlert2
 * 
 * @author ELIOT System
 * @version 2.2.1 - FIXED
 * @date 2026-01-02
 */

const PeminjamanApp = (function() {
    'use strict';

    // ============================================
    // STATE MANAGEMENT
    // ============================================
    const state = {
        scanning: false,              // Status sedang memvalidasi
        rfidBuffer: '',               // Buffer untuk RFID scanner
        rfidTimeout: null,            // Timeout untuk RFID scanner
        autoRefreshInterval: null     // Interval untuk auto-refresh
    };

    // ============================================
    // API ENDPOINTS - FIXED PATH
    const API = {
        validateMember: '/eliot/apps/includes/api/validate_member.php',
        getStats: '/eliot/apps/includes/api/get_dashboard_stats.php',
        getMonitoring: '/eliot/apps/includes/api/get_peminjaman_aktif.php'
    };

    // ============================================
    // INITIALIZATION
    // ============================================
    
    /**
     * Initialize aplikasi
     * Setup semua event listeners dan load data awal
     */
    function init() {
        console.log('[Peminjaman] Initializing application...');
        console.log('[Peminjaman] API Endpoints:', API);
        
        // Setup event handlers
        setupInputHandlers();
        setupRFIDListener();
        setupMonitoring();
        
        // Load data awal
        refreshMonitoringTable();
        updateStatistics();
        
        // Auto-refresh setiap 30 detik
        state.autoRefreshInterval = setInterval(() => {
            refreshMonitoringTable();
            updateStatistics();
        }, 30000);
        
        console.log('[Peminjaman] Application initialized successfully');
    }

    // ============================================
    // EVENT HANDLERS SETUP
    // ============================================
    
    /**
     * Setup input handlers untuk button dan Enter key
     */
    function setupInputHandlers() {
        const btnValidate = document.getElementById('btn-validate-member');
        const inputUid = document.getElementById('input-member-uid');
        
        // Button click handler
        if (btnValidate) {
            btnValidate.addEventListener('click', handleValidateMember);
            console.log('[Peminjaman] Validate button handler attached');
        }
        
        if (inputUid) {
            // Enter key handler
            inputUid.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleValidateMember();
                }
            });
            
            // Auto-focus on page load
            inputUid.focus();
            
            // Select all text on focus
            inputUid.addEventListener('focus', () => {
                inputUid.select();
            });
            
            console.log('[Peminjaman] Input field handlers attached');
        }
    }

    /**
     * Setup RFID listener (deteksi keyboard input dari RFID scanner)
     * RFID scanner biasanya mensimulasikan keyboard input yang sangat cepat
     */
    function setupRFIDListener() {
        const inputUid = document.getElementById('input-member-uid');
        
        document.addEventListener('keypress', (e) => {
            // Abaikan jika user sedang mengetik di input field
            if (e.target === inputUid) {
                return;
            }
            
            // Abaikan jika user mengetik di input/textarea lain
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }
            
            e.preventDefault();
            
            // Tambahkan karakter ke buffer
            state.rfidBuffer += e.key;
            
            // Clear timeout sebelumnya
            if (state.rfidTimeout) {
                clearTimeout(state.rfidTimeout);
            }
            
            // Set timeout baru untuk memproses RFID input
            // RFID scanner biasanya mengirim semua karakter dalam < 100ms
            state.rfidTimeout = setTimeout(() => {
                const uid = state.rfidBuffer.trim();
                state.rfidBuffer = ''; // Reset buffer
                
                if (uid) {
                    console.log('[Peminjaman] RFID scanned:', uid);
                    
                    // Isi input field dengan UID yang di-scan
                    if (inputUid) {
                        inputUid.value = uid;
                    }
                    
                    // Auto-validasi
                    handleValidateMember();
                }
            }, 100);
        });
        
        console.log('[Peminjaman] RFID listener attached');
    }

    /**
     * Setup monitoring table
     * Tambahkan event listener untuk filter
     */
    function setupMonitoring() {
        const filterStatus = document.getElementById('filter-status');
        
        if (filterStatus) {
            filterStatus.addEventListener('change', () => {
                console.log('[Peminjaman] Filter changed:', filterStatus.value);
                refreshMonitoringTable();
            });
        }
    }

    // ============================================
    // MEMBER VALIDATION
    // ============================================
    
    /**
     * Handle validasi member button click
     */
    async function handleValidateMember() {
        const inputUid = document.getElementById('input-member-uid');
        const uid = inputUid?.value.trim();
        
        // Validasi input tidak kosong
        if (!uid) {
            showToast('warning', 'Harap masukkan NIM/NIDN/NIK');
            inputUid?.focus();
            return;
        }
        
        // Proses validasi
        await validateMember(uid);
    }

    /**
     * Validasi member via API
     * @param {string} uid - NIM/NIDN/NIK member
     */
    async function validateMember(uid) {
        // Prevent double submission
        if (state.scanning) {
            console.log('[Peminjaman] Already validating, skipping...');
            return;
        }
        
        console.log('[Peminjaman] Validating member:', uid);
        console.log('[Peminjaman] API URL:', API.validateMember);
        
        state.scanning = true;
        updateStatus('scanning', 'Memvalidasi member...');
        disableInput(true);
        
        try {
            // Call API
            const response = await fetch(API.validateMember, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json' 
                },
                body: JSON.stringify({ 
                    no_identitas: uid 
                })
            });
            
            console.log('[Peminjaman] Response status:', response.status);
            
            // Check HTTP status
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            // Parse JSON response
            const result = await response.json();
            console.log('[Peminjaman] Validation result:', result);
            
            // Handle response
            if (result.success && result.data) {
                // ✅ SUCCESS - Member valid
                handleValidationSuccess(result.data);
            } else {
                // ❌ FAILED - Member tidak valid
                handleValidationError(result);
            }
            
        } catch (error) {
            // ⚠️ ERROR - Connection/Server error
            console.error('[Peminjaman] Validation error:', error);
            handleValidationException(error);
            
        } finally {
            state.scanning = false;
            disableInput(false);
        }
    }

    /**
     * Handle validasi sukses - redirect ke biodata
     * @param {object} memberData - Data member dari API
     */
    function handleValidationSuccess(memberData) {
        updateStatus('success', 'Member ditemukan!');
        
        showToast('success', `Member ditemukan: ${memberData.nama}`);
        
        // Enable Step 2 (RFID Scan)
        enableStep2();
        
        // Wait 500ms untuk user membaca toast, lalu redirect
        setTimeout(() => {
            const userId = memberData.id;
            window.location.href = `biodata_peminjaman.php?user_id=${userId}`;
        }, 500);
    }

    /**
     * Handle validasi gagal - tampilkan error
     * @param {object} result - Response dari API
     */
    function handleValidationError(result) {
        updateStatus('error', 'Member tidak valid');
        
        // Ambil pesan error dari API
        const errors = result.validation?.errors || ['Member tidak ditemukan atau tidak valid'];
        
        // Tampilkan error dengan SweetAlert2 (dark mode support)
        showError('Validasi Gagal', errors.join('<br>'));
        
        // Reset input setelah 2 detik
        setTimeout(() => {
            resetInput();
        }, 2000);
    }

    /**
     * Handle exception (network/server error)
     * @param {Error} error - Exception object
     */
    function handleValidationException(error) {
        updateStatus('error', 'Error koneksi');
        
        showError('Error Koneksi', `Gagal memvalidasi member: ${error.message}`);
        
        // Reset input setelah 2 detik
        setTimeout(() => {
            resetInput();
        }, 2000);
    }

    // ============================================
    // UI HELPER FUNCTIONS
    // ============================================
    
    /**
     * Update status indicator
     * @param {string} status - idle, scanning, success, error
     * @param {string} text - Status text
     */
    function updateStatus(status, text) {
        const indicator = document.getElementById('status-indicator');
        const statusText = document.getElementById('status-text');
        
        if (indicator) {
            indicator.className = `status-indicator ${status}`;
        }
        
        if (statusText) {
            statusText.textContent = text;
        }
    }

    /**
     * Reset input field ke kondisi awal
     */
    function resetInput() {
        const inputUid = document.getElementById('input-member-uid');
        
        if (inputUid) {
            inputUid.value = '';
            inputUid.focus();
        }
        
        updateStatus('idle', 'Siap Menerima Input');
    }

    /**
     * Disable/enable input dan button saat validasi
     * @param {boolean} disabled - True untuk disable
     */
    function disableInput(disabled) {
        const inputUid = document.getElementById('input-member-uid');
        const btnValidate = document.getElementById('btn-validate-member');
        
        if (inputUid) {
            inputUid.disabled = disabled;
        }
        
        if (btnValidate) {
            btnValidate.disabled = disabled;
        }
    }

    /**
     * Enable Step 2 (RFID Scan) setelah validasi member berhasil
     */
    function enableStep2() {
        const step2Container = document.getElementById('step-2-container');
        const inputRfid = document.getElementById('input-rfid-card');
        const btnScan = document.getElementById('btn-scan-rfid');
        
        // Remove disabled class dari container
        if (step2Container) {
            step2Container.classList.remove('step-disabled');
        }
        
        // Enable input dan button
        if (inputRfid) {
            inputRfid.disabled = false;
        }
        
        if (btnScan) {
            btnScan.disabled = false;
        }
        
        console.log('[Peminjaman] Step 2 enabled');
    }

    // ============================================
    // MONITORING TABLE
    // ============================================
    
    /**
     * Refresh monitoring table
     * Load peminjaman aktif dari API
     */
    async function refreshMonitoringTable() {
        console.log('[Peminjaman] Refreshing monitoring table...');
        
        const tableBody = document.getElementById('monitoring-table-body');
        const emptyState = document.getElementById('monitoring-empty');
        const loadingState = document.getElementById('monitoring-loading');
        
        if (!tableBody) return;
        
        try {
            // Show loading state
            if (loadingState) loadingState.classList.remove('d-none');
            if (emptyState) emptyState.classList.add('d-none');
            tableBody.innerHTML = '';
            
            // Get filter value
            const status = document.getElementById('filter-status')?.value || 'all';
            
            // FIXED: URL API yang benar
            const apiUrl = `${API.getMonitoring}?status=${status}&date=today`;
            console.log('[Peminjaman] Fetching from:', apiUrl);
            
            // Fetch data dari API
            const response = await fetch(apiUrl);
            
            console.log('[Peminjaman] Monitoring response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            console.log('[Peminjaman] Monitoring data:', result);
            
            // Hide loading state
            if (loadingState) loadingState.classList.add('d-none');
            
            // Render data
            if (result.success && result.data && result.data.length > 0) {
                renderMonitoringRows(result.data, tableBody);
            } else {
                // Show empty state
                if (emptyState) emptyState.classList.remove('d-none');
            }
            
        } catch (error) {
            console.error('[Peminjaman] Monitoring error:', error);
            
            if (loadingState) loadingState.classList.add('d-none');
            
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger py-4">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                        Error: ${error.message}
                    </td>
                </tr>
            `;
        }
    }

    /**
     * Render monitoring table rows
     * @param {array} data - Array of peminjaman data
     * @param {HTMLElement} tableBody - Table body element
     */
    function renderMonitoringRows(data, tableBody) {
        const html = data.map((row, index) => {
            // Tentukan class untuk urgency (high = danger, medium = warning)
            const urgencyClass = {
                high: 'table-danger',
                medium: 'table-warning',
                low: ''
            }[row.urgency] || '';
            
            // Mapping kategori member untuk badge
            const kategoriMap = {
                'mahasiswa': { badge: 'primary', icon: 'fa-user-graduate' },
                'dosen': { badge: 'success', icon: 'fa-chalkboard-teacher' },
                'umum': { badge: 'info', icon: 'fa-user' }
            };
            
            const kategori = kategoriMap[row.kategori_member?.toLowerCase()] || 
                           { badge: 'secondary', icon: 'fa-user' };
            
            return `
                <tr class="${urgencyClass}">
                    <td>${index + 1}</td>
                    <td>
                        <strong>${row.kode_peminjaman}</strong><br>
                        <small class="text-muted">${row.tanggal_pinjam_formatted || '-'}</small>
                    </td>
                    <td>
                        <strong>${row.nama_peminjam}</strong><br>
                        <small class="text-muted">${row.no_identitas}</small>
                    </td>
                    <td>
                        <span class="badge bg-${kategori.badge}">
                            <i class="fas ${kategori.icon} me-1"></i>
                            ${row.kategori_member || 'Umum'}
                        </span>
                    </td>
                    <td>
                        ${row.judul_buku}<br>
                        <small class="text-muted">Kode: ${row.kode_eksemplar}</small>
                    </td>
                    <td>
                        <span class="badge bg-${row.waktu_badge || 'secondary'}">
                            ${row.status_waktu || 'N/A'}
                        </span><br>
                        <small>${row.hari_tersisa} hari</small>
                    </td>
                    <td>${row.due_date_formatted || '-'}</td>
                </tr>
            `;
        }).join('');
        
        tableBody.innerHTML = html;
    }

    // ============================================
    // STATISTICS UPDATE
    // ============================================
    
    /**
     * Update statistics cards dari API
     */
    async function updateStatistics() {
        console.log('[Peminjaman] Updating statistics...');
        console.log('[Peminjaman] Stats API URL:', API.getStats);
        
        try {
            const response = await fetch(API.getStats);
            
            console.log('[Peminjaman] Stats response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            console.log('[Peminjaman] Stats data:', result);
            
            if (result.success && result.data) {
                const stats = result.data;
                
                // Update each stat card dengan animasi
                updateStatCard('stat-today', stats.total_today || 0);
                updateStatCard('stat-will-overdue', stats.will_overdue || 0);
                updateStatCard('stat-overdue', stats.overdue_now || 0);
                updateStatCard('stat-fines', stats.member_with_fines || 0);
            }
            
        } catch (error) {
            console.error('[Peminjaman] Stats error:', error);
        }
    }

    /**
     * Update individual stat card dengan animasi
     * @param {string} elementId - ID element stat card
     * @param {number} value - Nilai baru
     */
    function updateStatCard(elementId, value) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const currentValue = parseInt(element.textContent.replace(/\D/g, '')) || 0;
        
        // Hanya update jika nilai berubah
        if (currentValue !== value) {
            // Animasi scale up
            element.style.transition = 'all 0.3s ease';
            element.style.transform = 'scale(1.15)';
            
            // Update value dan scale down
            setTimeout(() => {
                element.textContent = value.toLocaleString('id-ID');
                element.style.transform = 'scale(1)';
            }, 150);
        }
    }

    // ============================================
    // SWEETALERT2 HELPERS (Dark Mode Support)
    // ============================================
    
    /**
     * Show toast notification
     * @param {string} type - success, error, warning, info
     * @param {string} message - Toast message
     * @param {number} timer - Auto-close timer (ms)
     */
    function showToast(type, message, timer = 3000) {
        // Gunakan DarkModeUtils jika tersedia (dari dark-mode-utils.js)
        if (window.DarkModeUtils && typeof window.DarkModeUtils.showToast === 'function') {
            window.DarkModeUtils.showToast(type, message, timer);
        } else {
            // Fallback ke console jika DarkModeUtils tidak tersedia
            console.log(`[Toast] ${type}: ${message}`);
        }
    }

    /**
     * Show error alert dengan SweetAlert2
     * @param {string} title - Alert title
     * @param {string} message - Alert message (support HTML)
     */
    function showError(title, message) {
        // Gunakan DarkModeUtils jika tersedia
        if (window.DarkModeUtils && typeof window.DarkModeUtils.showError === 'function') {
            window.DarkModeUtils.showError(title, message);
        } else {
            // Fallback ke alert browser
            alert(`${title}: ${message}`);
        }
    }

    // ============================================
    // CLEANUP
    // ============================================
    
    /**
     * Cleanup saat page unload
     * Clear semua intervals dan timeouts
     */
    function cleanup() {
        if (state.autoRefreshInterval) {
            clearInterval(state.autoRefreshInterval);
            console.log('[Peminjaman] Auto-refresh stopped');
        }
        
        if (state.rfidTimeout) {
            clearTimeout(state.rfidTimeout);
        }
    }

    // Attach cleanup ke window unload
    window.addEventListener('beforeunload', cleanup);

    // ============================================
    // EXPOSE PUBLIC API
    // ============================================
    
    // Expose functions ke global scope untuk dipanggil dari HTML
    window.refreshMonitoringTable = refreshMonitoringTable;
    window.updateStatistics = updateStatistics;

    // Return public API
    return {
        init,
        state,
        refreshMonitoringTable,
        updateStatistics
    };
})();

// ============================================
// AUTO-INITIALIZE ON DOM READY
// ============================================

if (document.readyState === 'loading') {
    // DOM belum ready, tunggu DOMContentLoaded event
    document.addEventListener('DOMContentLoaded', () => {
        console.log('[Peminjaman] DOM ready, initializing...');
        PeminjamanApp.init();
    });
} else {
    // DOM sudah ready, langsung initialize
    console.log('[Peminjaman] DOM already ready, initializing...');
    PeminjamanApp.init();
}