/**
 * Peminjaman App - Step 1: Member Validation
 * Path: web/apps/assets/js/peminjaman.js
 * 
 * @author ELIOT System
 * @version 2.4.0 - FIXED
 * @date 2026-01-04
 */

const PeminjamanApp = (function() {
    'use strict';

    // State Management
    const state = {
        scanning: false,
        rfidBuffer: '',
        rfidTimeout: null,
        autoRefreshInterval: null,
        memberVerified: null
    };

    // API Endpoints
    const API = {
        validateMember: '/eliot/apps/includes/api/validate_member.php',
        getStats: '/eliot/apps/includes/api/get_dashboard_stats.php',
        getMonitoring: '/eliot/apps/includes/api/get_peminjaman_aktif.php'
    };

    // ========================================
    // INITIALIZATION
    // ========================================
    
    function init() {
        console.log('[Peminjaman] Initializing...');
        
        checkMemberVerified();
        setupInputHandlers();
        setupRFIDListener();
        setupMonitoring();
        
        refreshMonitoringTable();
        updateStatistics();
        
        state.autoRefreshInterval = setInterval(() => {
            refreshMonitoringTable();
            updateStatistics();
        }, 30000);
        
        console.log('[Peminjaman] Initialized');
    }

    // ========================================
    // CHECK MEMBER VERIFIED
    // ========================================
    
    function checkMemberVerified() {
        const memberData = sessionStorage.getItem('member_verified');
        
        if (memberData) {
            try {
                const member = JSON.parse(memberData);
                const now = Date.now();
                const elapsed = (now - member.timestamp) / 1000 / 60;
                
                if (elapsed > 5) {
                    sessionStorage.removeItem('member_verified');
                    return;
                }
                
                state.memberVerified = member;
                enableStep2(member);
                showToast('success', 'Member ' + member.nama + ' berhasil diverifikasi!');
                
            } catch (error) {
                console.error('[Peminjaman] Error parsing member:', error);
                sessionStorage.removeItem('member_verified');
            }
        }
    }

    // ========================================
    // SETUP EVENT HANDLERS
    // ========================================
    
    function setupInputHandlers() {
        const btnValidate = document.getElementById('btn-validate-member');
        const inputUid = document.getElementById('input-member-uid');
        
        if (btnValidate) {
            btnValidate.addEventListener('click', handleValidateMember);
        }
        
        if (inputUid) {
            inputUid.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleValidateMember();
                }
            });
            
            inputUid.focus();
            
            inputUid.addEventListener('focus', function() {
                inputUid.select();
            });
        }
    }

    function setupRFIDListener() {
        const inputUid = document.getElementById('input-member-uid');
        
        document.addEventListener('keypress', function(e) {
            if (e.target === inputUid) return;
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            e.preventDefault();
            state.rfidBuffer += e.key;
            
            if (state.rfidTimeout) clearTimeout(state.rfidTimeout);
            
            state.rfidTimeout = setTimeout(function() {
                const uid = state.rfidBuffer.trim();
                state.rfidBuffer = '';
                
                if (uid) {
                    console.log('[Peminjaman] RFID scanned:', uid);
                    if (inputUid) inputUid.value = uid;
                    handleValidateMember();
                }
            }, 100);
        });
    }

    function setupMonitoring() {
        const filterStatus = document.getElementById('filter-status');
        if (filterStatus) {
            filterStatus.addEventListener('change', function() {
                refreshMonitoringTable();
            });
        }
    }

    // ========================================
    // MEMBER VALIDATION
    // ========================================
    
    async function handleValidateMember() {
        const inputUid = document.getElementById('input-member-uid');
        const uid = inputUid ? inputUid.value.trim() : '';
        
        if (!uid) {
            showToast('warning', 'Harap masukkan NIM/NIDN/NIK');
            if (inputUid) inputUid.focus();
            return;
        }
        
        await validateMember(uid);
    }

    async function validateMember(uid) {
        if (state.scanning) {
            console.log('[Peminjaman] Already validating');
            return;
        }
        
        console.log('[Peminjaman] Validating member:', uid);
        
        state.scanning = true;
        updateStatus('scanning', 'Memvalidasi member...');
        disableInput(true);
        
        try {
            const response = await fetch(API.validateMember, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_identitas: uid })
            });
            
            console.log('[Peminjaman] Response status:', response.status);
            
            const result = await response.json();
            console.log('[Peminjaman] Result:', result);
            
            if (result.success && result.code === 'SUCCESS') {
                handleValidationSuccess(result.data);
            } else {
                handleValidationError(result);
            }
            
        } catch (error) {
            console.error('[Peminjaman] Error:', error);
            handleValidationException(error);
        } finally {
            state.scanning = false;
            disableInput(false);
        }
    }

    function handleValidationSuccess(memberData) {
        updateStatus('success', 'Member ditemukan!');
        showToast('success', 'Member ditemukan: ' + memberData.nama);
        
        console.log('[Peminjaman] Redirect to biodata:', memberData);
        
        setTimeout(function() {
            window.location.href = 'biodata_peminjaman.php?user_id=' + memberData.id;
        }, 500);
    }

    function handleValidationError(result) {
        updateStatus('error', 'Validasi gagal');
        
        const code = result.code || 'UNKNOWN';
        const errors = result.validation && result.validation.errors 
            ? result.validation.errors 
            : [result.message || 'Terjadi kesalahan'];
        
        console.log('[Peminjaman] Error code:', code);
        console.log('[Peminjaman] Errors:', errors);
        
        switch (code) {
            case 'NOT_REGISTERED':
                handleNotRegistered(errors);
                break;
                
            case 'ADMIN_STAFF_NOT_ALLOWED':
                handleAdminStaffError(errors);
                break;
                
            case 'VALIDATION_FAILED':
                handleValidationFailed(errors);
                break;
                
            case 'EMPTY_NO_IDENTITAS':
            case 'EMPTY_INPUT':
                showToast('warning', 'No identitas tidak boleh kosong');
                break;
                
            case 'INVALID_JSON':
                showError('Error Format', 'Format data tidak valid');
                break;
                
            case 'DB_ERROR':
            case 'SERVER_ERROR':
                showError('Error Server', errors.join('<br>'));
                break;
                
            default:
                showError('Validasi Gagal', errors.join('<br>'));
                break;
        }
        
        setTimeout(function() {
            resetInput();
        }, 2000);
    }

    function handleNotRegistered(errors) {
        console.log('[Peminjaman] Not registered');
        
        const htmlContent = '<div class="text-center py-3">' +
            '<i class="fas fa-user-times fa-3x text-danger mb-3"></i>' +
            '<p class="mb-0">' + errors.join('<br>') + '</p>' +
            '<small class="text-muted mt-2 d-block">' +
            'Silakan mendaftar terlebih dahulu atau hubungi admin' +
            '</small>' +
            '</div>';
        
        if (window.DarkModeUtils && typeof window.DarkModeUtils.getSwalConfig === 'function') {
            const config = window.DarkModeUtils.getSwalConfig({
                title: 'No Identitas Belum Terdaftar',
                html: htmlContent,
                icon: 'error',
                confirmButtonText: '<i class="fas fa-times me-2"></i>Tutup',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-secondary'
                }
            });
            Swal.fire(config);
        } else {
            Swal.fire({
                title: 'No Identitas Belum Terdaftar',
                html: htmlContent,
                icon: 'error',
                confirmButtonText: 'Tutup'
            });
        }
    }

    function handleAdminStaffError(errors) {
        console.log('[Peminjaman] Admin/Staff not allowed');
        
        const htmlContent = '<div class="text-center py-3">' +
            '<i class="fas fa-user-shield fa-3x text-warning mb-3"></i>' +
            '<p class="mb-0">' + errors.join('<br>') + '</p>' +
            '<small class="text-muted mt-2 d-block">' +
            'Hanya akun dengan role <strong>Member</strong> yang dapat meminjam buku' +
            '</small>' +
            '</div>';
        
        if (window.DarkModeUtils && typeof window.DarkModeUtils.getSwalConfig === 'function') {
            const config = window.DarkModeUtils.getSwalConfig({
                title: 'Admin dan Staff Tidak Dapat Meminjam Buku',
                html: htmlContent,
                icon: 'warning',
                confirmButtonText: '<i class="fas fa-times me-2"></i>Tutup',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-secondary'
                }
            });
            Swal.fire(config);
        } else {
            Swal.fire({
                title: 'Admin dan Staff Tidak Dapat Meminjam Buku',
                html: htmlContent,
                icon: 'warning',
                confirmButtonText: 'Tutup'
            });
        }
    }

    function handleValidationFailed(errors) {
        console.log('[Peminjaman] Validation failed:', errors);
        
        const errorList = errors.map(function(err) {
            return '<li>' + err + '</li>';
        }).join('');
        
        const htmlContent = '<div class="text-start py-2">' +
            '<p class="mb-2"><strong>Member tidak dapat meminjam karena:</strong></p>' +
            '<ul class="text-danger mb-0">' + errorList + '</ul>' +
            '</div>';
        
        if (window.DarkModeUtils && typeof window.DarkModeUtils.getSwalConfig === 'function') {
            const config = window.DarkModeUtils.getSwalConfig({
                title: 'Member Tidak Memenuhi Syarat',
                html: htmlContent,
                icon: 'error',
                confirmButtonText: '<i class="fas fa-times me-2"></i>Tutup',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-secondary'
                }
            });
            Swal.fire(config);
        } else {
            Swal.fire({
                title: 'Member Tidak Memenuhi Syarat',
                html: htmlContent,
                icon: 'error',
                confirmButtonText: 'Tutup'
            });
        }
    }

    function handleValidationException(error) {
        console.error('[Peminjaman] Exception:', error);
        
        updateStatus('error', 'Error koneksi');
        
        showError('Error Koneksi', 
            'Gagal terhubung ke server.<br><small>Detail: ' + error.message + '</small>');
        
        setTimeout(function() {
            resetInput();
        }, 2000);
    }

    // ========================================
    // UI HELPERS
    // ========================================
    
    function updateStatus(status, text) {
        const indicator = document.getElementById('status-indicator');
        const statusText = document.getElementById('status-text');
        
        if (indicator) indicator.className = 'status-indicator ' + status;
        if (statusText) statusText.textContent = text;
    }

    function resetInput() {
        const inputUid = document.getElementById('input-member-uid');
        if (inputUid) {
            inputUid.value = '';
            inputUid.focus();
        }
        updateStatus('idle', 'Siap Menerima Input');
    }

    function disableInput(disabled) {
        const inputUid = document.getElementById('input-member-uid');
        const btnValidate = document.getElementById('btn-validate-member');
        
        if (inputUid) inputUid.disabled = disabled;
        if (btnValidate) btnValidate.disabled = disabled;
    }

    function enableStep2(memberData) {
        const step2Container = document.getElementById('step-2-container');
        const inputRfid = document.getElementById('input-rfid-card');
        const btnScan = document.getElementById('btn-scan-rfid');
        
        if (step2Container) {
            step2Container.classList.remove('step-disabled');
            step2Container.classList.add('step-enabled');
        }
        
        if (inputRfid) {
            inputRfid.disabled = false;
            inputRfid.placeholder = 'Scan kartu untuk ' + memberData.nama;
        }
        
        if (btnScan) {
            btnScan.disabled = false;
        }
        
        const helperText = step2Container ? step2Container.querySelector('.form-text') : null;
        if (helperText) {
            helperText.innerHTML = '<i class="fas fa-check-circle me-1 text-success"></i>' +
                'Step ini sudah aktif. Dekatkan kartu RFID ke scanner.';
        }
        
        console.log('[Peminjaman] Step 2 enabled:', memberData.nama);
    }

    // ========================================
    // MONITORING TABLE
    // ========================================
    
    async function refreshMonitoringTable() {
        console.log('[Peminjaman] Refreshing table...');
        
        const tableBody = document.getElementById('monitoring-table-body');
        const emptyState = document.getElementById('monitoring-empty');
        const loadingState = document.getElementById('monitoring-loading');
        
        if (!tableBody) return;
        
        try {
            if (loadingState) loadingState.classList.remove('d-none');
            if (emptyState) emptyState.classList.add('d-none');
            tableBody.innerHTML = '';
            
            const filterStatus = document.getElementById('filter-status');
            const status = filterStatus ? filterStatus.value : 'all';
            const apiUrl = API.getMonitoring + '?status=' + status + '&date=today';
            
            const response = await fetch(apiUrl);
            const result = await response.json();
            
            if (loadingState) loadingState.classList.add('d-none');
            
            if (result.success && result.data && result.data.length > 0) {
                renderMonitoringRows(result.data, tableBody);
            } else {
                if (emptyState) emptyState.classList.remove('d-none');
            }
            
        } catch (error) {
            console.error('[Peminjaman] Monitoring error:', error);
            if (loadingState) loadingState.classList.add('d-none');
            tableBody.innerHTML = '<tr>' +
                '<td colspan="7" class="text-center text-danger py-4">' +
                '<i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>' +
                'Error: ' + error.message +
                '</td>' +
                '</tr>';
        }
    }

    function renderMonitoringRows(data, tableBody) {
        const rows = data.map(function(row, index) {
            const urgencyMap = {
                high: 'table-danger',
                medium: 'table-warning',
                low: ''
            };
            const urgencyClass = urgencyMap[row.urgency] || '';
            
            const kategoriMap = {
                'mahasiswa': { badge: 'primary', icon: 'fa-user-graduate' },
                'dosen': { badge: 'success', icon: 'fa-chalkboard-teacher' },
                'umum': { badge: 'info', icon: 'fa-user' }
            };
            
            const kategoriLower = row.kategori_member ? row.kategori_member.toLowerCase() : '';
            const kategori = kategoriMap[kategoriLower] || { badge: 'secondary', icon: 'fa-user' };
            
            return '<tr class="' + urgencyClass + '">' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' +
                    '<strong>' + row.kode_peminjaman + '</strong><br>' +
                    '<small class="text-muted">' + (row.tanggal_pinjam_formatted || '-') + '</small>' +
                '</td>' +
                '<td>' +
                    '<strong>' + row.nama_peminjam + '</strong><br>' +
                    '<small class="text-muted">' + row.no_identitas + '</small>' +
                '</td>' +
                '<td>' +
                    '<span class="badge bg-' + kategori.badge + '">' +
                        '<i class="fas ' + kategori.icon + ' me-1"></i>' +
                        (row.kategori_member || 'Umum') +
                    '</span>' +
                '</td>' +
                '<td>' +
                    row.judul_buku + '<br>' +
                    '<small class="text-muted">Kode: ' + row.kode_eksemplar + '</small>' +
                '</td>' +
                '<td>' +
                    '<span class="badge bg-' + (row.waktu_badge || 'secondary') + '">' +
                        (row.status_waktu || 'N/A') +
                    '</span><br>' +
                    '<small>' + row.hari_tersisa + ' hari</small>' +
                '</td>' +
                '<td>' + (row.due_date_formatted || '-') + '</td>' +
                '</tr>';
        });
        
        tableBody.innerHTML = rows.join('');
    }

    // ========================================
    // STATISTICS
    // ========================================
    
    async function updateStatistics() {
        try {
            const response = await fetch(API.getStats);
            const result = await response.json();
            
            if (result.success && result.data) {
                const stats = result.data;
                updateStatCard('stat-today', stats.total_today || 0);
                updateStatCard('stat-will-overdue', stats.will_overdue || 0);
                updateStatCard('stat-overdue', stats.overdue_now || 0);
                updateStatCard('stat-fines', stats.member_with_fines || 0);
            }
        } catch (error) {
            console.error('[Peminjaman] Stats error:', error);
        }
    }

    function updateStatCard(elementId, value) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const currentText = element.textContent.replace(/\D/g, '');
        const currentValue = parseInt(currentText) || 0;
        
        if (currentValue !== value) {
            element.style.transition = 'all 0.3s ease';
            element.style.transform = 'scale(1.15)';
            
            setTimeout(function() {
                element.textContent = value.toLocaleString('id-ID');
                element.style.transform = 'scale(1)';
            }, 150);
        }
    }

    // ========================================
    // SWEETALERT HELPERS
    // ========================================
    
    function showToast(type, message, timer) {
        timer = timer || 3000;
        
        if (window.DarkModeUtils && typeof window.DarkModeUtils.showToast === 'function') {
            window.DarkModeUtils.showToast(type, message, timer);
        } else {
            console.log('[Toast] ' + type + ': ' + message);
        }
    }

    function showError(title, message) {
        if (window.DarkModeUtils && typeof window.DarkModeUtils.showError === 'function') {
            window.DarkModeUtils.showError(title, message);
        } else {
            alert(title + ': ' + message);
        }
    }

    // ========================================
    // CLEANUP
    // ========================================
    
    function cleanup() {
        if (state.autoRefreshInterval) {
            clearInterval(state.autoRefreshInterval);
        }
        if (state.rfidTimeout) {
            clearTimeout(state.rfidTimeout);
        }
    }

    window.addEventListener('beforeunload', cleanup);

    // ========================================
    // PUBLIC API
    // ========================================
    
    window.refreshMonitoringTable = refreshMonitoringTable;
    window.updateStatistics = updateStatistics;

    return {
        init: init,
        state: state,
        refreshMonitoringTable: refreshMonitoringTable,
        updateStatistics: updateStatistics
    };
})();

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[Peminjaman] DOM ready');
        PeminjamanApp.init();
    });
} else {
    console.log('[Peminjaman] DOM already ready');
    PeminjamanApp.init();
}