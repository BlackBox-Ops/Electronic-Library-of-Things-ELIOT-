/**
 * Peminjaman Form Handler (FIXED - COMPLETE VERSION)
 * Path: web/apps/assets/js/peminjaman_form.js
 * 
 * FIXES:
 * - Enhanced data element detection
 * - Better error handling
 * - Improved debugging
 * - Fixed redirect after save
 * - Complete implementation without truncation
 * 
 * @author ELIOT System
 * @version 1.1.0 - FIXED COMPLETE
 * @date 2026-01-07
 */

const PeminjamanForm = (function() {
    'use strict';

    // ========================================
    // STATE MANAGEMENT
    // ========================================
    
    const state = {
        data: null,
        processing: false
    };

    // ========================================
    // CONFIGURATION
    // ========================================
    
    const API = {
        processPeminjaman: '../../includes/api/scan_peminjaman.php'
    };

    // ========================================
    // INITIALIZATION
    // ========================================
    
    function init() {
        console.log('[Peminjaman Form] ===== INITIALIZING =====');
        console.log('[Peminjaman Form] Timestamp:', new Date().toISOString());
        console.log('[Peminjaman Form] Document readyState:', document.readyState);
        
        // ✅ WAIT FOR DOM
        if (document.readyState === 'loading') {
            console.log('[Peminjaman Form] DOM not ready, waiting...');
            document.addEventListener('DOMContentLoaded', function() {
                console.log('[Peminjaman Form] DOM ready event fired');
                loadData();
            });
        } else {
            console.log('[Peminjaman Form] DOM already ready');
            loadData();
        }
    }

    // ========================================
    // LOAD DATA FROM PAGE
    // ========================================
    
    function loadData() {
        console.log('[Peminjaman Form] ===== LOADING DATA =====');
        
        // ✅ Find data element
        const dataElement = document.getElementById('peminjaman-data');
        console.log('[Peminjaman Form] Data element found:', dataElement !== null);
        
        if (!dataElement) {
            console.error('[Peminjaman Form] ❌ Element #peminjaman-data NOT FOUND!');
            
            // Debug: List all script elements
            const allScripts = document.querySelectorAll('script');
            console.log('[Peminjaman Form] Total script elements in page:', allScripts.length);
            
            allScripts.forEach(function(script, index) {
                const info = {
                    index: index,
                    id: script.id || '(no id)',
                    type: script.type || '(no type)',
                    src: script.src || '(inline)',
                    hasContent: script.textContent ? 'yes' : 'no',
                    contentLength: script.textContent ? script.textContent.length : 0
                };
                console.log('[Peminjaman Form] Script', index, ':', info);
            });
            
            // Debug: Check if script exists with different selector
            const jsonScripts = document.querySelectorAll('script[type="application/json"]');
            console.log('[Peminjaman Form] JSON scripts found:', jsonScripts.length);
            
            jsonScripts.forEach(function(script, index) {
                console.log('[Peminjaman Form] JSON script', index, ':', {
                    id: script.id,
                    content: script.textContent ? script.textContent.substring(0, 100) : null
                });
            });
            
            showError('Data Tidak Tersedia', 
                'Element #peminjaman-data tidak ditemukan di halaman.<br><br>' +
                '<small>Kemungkinan penyebab:</small><ul class="text-start">' +
                '<li>Buffer output corrupt</li>' +
                '<li>Error PHP tersembunyi</li>' +
                '<li>Script load order salah</li></ul>' +
                '<small><strong>Solusi:</strong> Refresh halaman atau hubungi admin</small>');
            return;
        }
        
        console.log('[Peminjaman Form] ✅ Element found successfully');
        console.log('[Peminjaman Form] Element type:', dataElement.type);
        
        // ✅ Parse JSON content
        const jsonText = dataElement.textContent.trim();
        console.log('[Peminjaman Form] JSON text length:', jsonText.length);
        console.log('[Peminjaman Form] JSON text preview (first 200 chars):', jsonText.substring(0, 200));
        
        if (!jsonText) {
            console.error('[Peminjaman Form] ❌ JSON content is empty!');
            showError('Data Kosong', 
                'Konten JSON di element #peminjaman-data kosong.<br>' +
                'Silakan refresh halaman atau hubungi admin.');
            return;
        }
        
        try {
            state.data = JSON.parse(jsonText);
            console.log('[Peminjaman Form] ✅ JSON parsed successfully');
            console.log('[Peminjaman Form] Parsed data:', state.data);
            
            // ✅ Validate required fields
            const requiredFields = ['userId', 'bookId', 'uidBufferId', 'staffId'];
            const missingFields = [];
            
            requiredFields.forEach(function(field) {
                if (!state.data[field]) {
                    missingFields.push(field);
                }
            });
            
            if (missingFields.length > 0) {
                console.error('[Peminjaman Form] ❌ Missing required fields:', missingFields);
                showError('Data Tidak Lengkap', 
                    'Field yang hilang: <strong>' + missingFields.join(', ') + '</strong><br>' +
                    'Silakan refresh halaman atau hubungi admin.');
                return;
            }
            
            console.log('[Peminjaman Form] ✅ All required fields present:');
            console.log('[Peminjaman Form]   - userId:', state.data.userId);
            console.log('[Peminjaman Form]   - bookId:', state.data.bookId);
            console.log('[Peminjaman Form]   - uidBufferId:', state.data.uidBufferId);
            console.log('[Peminjaman Form]   - staffId:', state.data.staffId);
            
            // ✅ Setup event handlers
            setupEventHandlers();
            
            console.log('[Peminjaman Form] ===== INITIALIZATION COMPLETE =====');
            console.log('[Peminjaman Form] Status: READY');
            
        } catch (error) {
            console.error('[Peminjaman Form] ❌ JSON parse error:', error);
            console.error('[Peminjaman Form] Error name:', error.name);
            console.error('[Peminjaman Form] Error message:', error.message);
            console.error('[Peminjaman Form] JSON text that failed:', jsonText);
            
            showError('Error Parse Data', 
                'Gagal mem-parse JSON data.<br><br>' +
                '<small><strong>Error:</strong> ' + error.message + '</small><br>' +
                '<small>Silakan refresh halaman atau hubungi admin.</small>');
        }
    }

    // ========================================
    // SETUP EVENT HANDLERS
    // ========================================
    
    function setupEventHandlers() {
        console.log('[Peminjaman Form] Setting up event handlers...');
        
        const btnProcess = document.getElementById('btn-process-peminjaman');
        
        if (!btnProcess) {
            console.error('[Peminjaman Form] ❌ Button #btn-process-peminjaman not found!');
            console.error('[Peminjaman Form] Available buttons:', 
                document.querySelectorAll('button').length);
            return;
        }
        
        console.log('[Peminjaman Form] ✅ Button found:', btnProcess);
        console.log('[Peminjaman Form] Button text:', btnProcess.textContent);
        console.log('[Peminjaman Form] Button disabled:', btnProcess.disabled);
        
        // Remove any existing listeners (just in case)
        const newBtn = btnProcess.cloneNode(true);
        btnProcess.parentNode.replaceChild(newBtn, btnProcess);
        
        console.log('[Peminjaman Form] Attaching click handler...');
        
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('[Peminjaman Form] ===== BUTTON CLICKED =====');
            console.log('[Peminjaman Form] Event:', e);
            console.log('[Peminjaman Form] Current processing state:', state.processing);
            handleProcessPeminjaman();
        });
        
        console.log('[Peminjaman Form] ✅ Event handler attached successfully');
    }

    // ========================================
    // HANDLE PROCESS PEMINJAMAN
    // ========================================
    
    async function handleProcessPeminjaman() {
        console.log('[Peminjaman Form] ===== PROCESSING PEMINJAMAN =====');
        console.log('[Peminjaman Form] Timestamp:', new Date().toISOString());
        
        // Check if already processing
        if (state.processing) {
            console.log('[Peminjaman Form] ⚠️ Already processing, ignoring click');
            return;
        }
        
        // Check if data is available
        if (!state.data) {
            console.error('[Peminjaman Form] ❌ No data available!');
            showError('Error', 'Data tidak tersedia. Silakan refresh halaman.');
            return;
        }
        
        console.log('[Peminjaman Form] Current data:', state.data);
        
        // Get form values
        const durasiSelect = document.getElementById('durasi-peminjaman');
        const catatanTextarea = document.getElementById('catatan-peminjaman');
        
        const durasiHari = durasiSelect ? parseInt(durasiSelect.value) : 7;
        const catatan = catatanTextarea ? catatanTextarea.value.trim() : '';
        
        console.log('[Peminjaman Form] Form values:');
        console.log('[Peminjaman Form]   - Durasi:', durasiHari, 'hari');
        console.log('[Peminjaman Form]   - Catatan:', catatan ? '"' + catatan + '"' : '(empty)');
        
        // Show confirmation dialog
        console.log('[Peminjaman Form] Showing confirmation dialog...');
        const confirmResult = await showConfirmation(durasiHari);
        
        if (!confirmResult.isConfirmed) {
            console.log('[Peminjaman Form] ❌ User cancelled confirmation');
            return;
        }
        
        console.log('[Peminjaman Form] ✅ User confirmed, proceeding...');
        
        // Set processing state
        state.processing = true;
        disableButton(true);
        
        try {
            // ✅ Build request payload
            const payload = {
                uid_buffer_id: state.data.uidBufferId,
                book_id: state.data.bookId,
                user_id: state.data.userId,
                staff_id: state.data.staffId,
                durasi_hari: durasiHari,
                catatan: catatan
            };
            
            console.log('[Peminjaman Form] Request payload:', payload);
            console.log('[Peminjaman Form] API URL:', API.processPeminjaman);
            console.log('[Peminjaman Form] Sending request...');
            
            // ✅ Call API
            const response = await fetch(API.processPeminjaman, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            
            console.log('[Peminjaman Form] Response received');
            console.log('[Peminjaman Form] Response status:', response.status);
            console.log('[Peminjaman Form] Response ok:', response.ok);
            console.log('[Peminjaman Form] Response headers:', {
                contentType: response.headers.get('content-type')
            });
            
            // Parse response
            const result = await response.json();
            console.log('[Peminjaman Form] Response data:', result);
            
            // Check result
            if (result.success) {
                console.log('[Peminjaman Form] ✅ SUCCESS!');
                console.log('[Peminjaman Form] Result code:', result.code);
                console.log('[Peminjaman Form] Result message:', result.message);
                handleSuccess(result);
            } else {
                console.error('[Peminjaman Form] ❌ API returned error');
                console.error('[Peminjaman Form] Error code:', result.code);
                console.error('[Peminjaman Form] Error message:', result.message);
                handleError(result);
            }
            
        } catch (error) {
            console.error('[Peminjaman Form] ❌ Exception caught!');
            console.error('[Peminjaman Form] Error type:', error.name);
            console.error('[Peminjaman Form] Error message:', error.message);
            console.error('[Peminjaman Form] Error stack:', error.stack);
            
            showError('Error Koneksi', 
                'Gagal menghubungi server.<br><br>' +
                '<small><strong>Detail:</strong> ' + error.message + '</small><br>' +
                '<small>Silakan cek koneksi internet atau hubungi admin.</small>');
            
        } finally {
            console.log('[Peminjaman Form] Cleaning up...');
            state.processing = false;
            disableButton(false);
            console.log('[Peminjaman Form] Processing state reset');
        }
    }

    // ========================================
    // HANDLE SUCCESS RESPONSE
    // ========================================
    
    function handleSuccess(result) {
        console.log('[Peminjaman Form] ===== HANDLING SUCCESS =====');
        console.log('[Peminjaman Form] Full result:', result);
        
        const data = result.data || {};
        const peminjaman = data.peminjaman || {};
        const buku = data.buku || {};
        const peminjam = data.peminjam || {};
        
        console.log('[Peminjaman Form] Peminjaman data:', peminjaman);
        console.log('[Peminjaman Form] Buku data:', buku);
        console.log('[Peminjaman Form] Peminjam data:', peminjam);
        
        // Build success message HTML
        const htmlContent = `
            <div class="text-center py-3">
                <i class="fas fa-check-circle fa-4x text-success mb-3 animate__animated animate__bounceIn"></i>
                <h4 class="mb-3 fw-bold">Peminjaman Berhasil Diproses!</h4>
                
                <div class="card mb-3 shadow-sm">
                    <div class="card-body text-start">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <th style="width: 40%; color: #666;">
                                        <i class="fas fa-barcode me-2 text-primary"></i>Kode Peminjaman
                                    </th>
                                    <td>
                                        <strong class="text-primary">${peminjaman.kode_peminjaman || 'N/A'}</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-user me-2 text-info"></i>Peminjam
                                    </th>
                                    <td>${peminjam.nama || state.data.memberName || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-book me-2 text-success"></i>Buku
                                    </th>
                                    <td>${buku.judul || state.data.bookTitle || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-qrcode me-2 text-warning"></i>Kode Eksemplar
                                    </th>
                                    <td><code>${buku.eksemplar?.kode || state.data.kodeEksemplar || 'N/A'}</code></td>
                                </tr>
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-calendar-check me-2 text-primary"></i>Tanggal Pinjam
                                    </th>
                                    <td>${peminjaman.tanggal_pinjam_formatted || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-calendar-times me-2 text-danger"></i>Jatuh Tempo
                                    </th>
                                    <td>
                                        <strong class="text-danger">${peminjaman.due_date_formatted || 'N/A'}</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-clock me-2 text-info"></i>Durasi
                                    </th>
                                    <td>
                                        <span class="badge bg-info">${peminjaman.durasi_hari_kerja || 7} hari kerja</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Penting:</strong> Harap kembalikan buku tepat waktu untuk menghindari denda keterlambatan
                </div>
            </div>
        `;
        
        console.log('[Peminjaman Form] Showing success dialog...');
        
        const config = window.DarkModeUtils ? window.DarkModeUtils.getSwalConfig({
            title: 'Peminjaman Berhasil!',
            html: htmlContent,
            icon: 'success',
            confirmButtonText: '<i class="fas fa-home me-2"></i>Kembali ke Dashboard',
            allowOutsideClick: false,
            allowEscapeKey: false,
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-success btn-lg px-5'
            }
        }) : {
            title: 'Peminjaman Berhasil!',
            html: htmlContent,
            icon: 'success',
            confirmButtonText: 'Kembali ke Dashboard',
            allowOutsideClick: false,
            allowEscapeKey: false
        };
        
        Swal.fire(config).then(function(result) {
            console.log('[Peminjaman Form] User clicked confirm button');
            console.log('[Peminjaman Form] Redirecting to index.php...');
            
            // Clear session storage if needed
            sessionStorage.removeItem('member_verified');
            
            // Redirect
            window.location.href = 'index.php?success=peminjaman';
        });
    }

    // ========================================
    // HANDLE ERROR RESPONSE
    // ========================================
    
    function handleError(result) {
        console.log('[Peminjaman Form] ===== HANDLING ERROR =====');
        console.error('[Peminjaman Form] Error code:', result.code);
        console.error('[Peminjaman Form] Error message:', result.message);
        console.error('[Peminjaman Form] Full result:', result);
        
        // Map error codes to user-friendly messages
        const errorMessages = {
            'NO_COPIES_AVAILABLE': {
                title: 'Buku Tidak Tersedia',
                message: 'Semua eksemplar buku ini sedang dipinjam.<br>Silakan coba lagi nanti atau pilih buku lain.'
            },
            'COPY_ALREADY_BORROWED': {
                title: 'Eksemplar Sudah Dipinjam',
                message: 'Eksemplar buku ini sudah dipinjam oleh member lain.<br>Silakan scan eksemplar lain.'
            },
            'BOOK_CONDITION_BAD': {
                title: 'Kondisi Buku Buruk',
                message: 'Kondisi buku tidak memungkinkan untuk dipinjam.<br>Silakan hubungi admin untuk informasi lebih lanjut.'
            },
            'SP_ERROR': {
                title: 'Error Database',
                message: 'Gagal memproses peminjaman di database.<br>Silakan coba lagi atau hubungi admin.'
            },
            'BOOK_NOT_FOUND': {
                title: 'Buku Tidak Ditemukan',
                message: 'Data buku tidak ditemukan dalam sistem.<br>Silakan scan ulang atau hubungi admin.'
            },
            'RFID_NOT_FOUND': {
                title: 'RFID Tidak Terdaftar',
                message: 'RFID tidak terdaftar dalam sistem.<br>Silakan hubungi admin untuk registrasi.'
            },
            'INVALID_USER_ID': {
                title: 'User Tidak Valid',
                message: 'Data member tidak valid.<br>Silakan scan ulang member.'
            },
            'INVALID_STAFF_ID': {
                title: 'Staff Tidak Valid',
                message: 'Data staff tidak valid.<br>Silakan login ulang.'
            }
        };
        
        const errorInfo = errorMessages[result.code] || {
            title: 'Peminjaman Gagal',
            message: result.message || 'Terjadi kesalahan yang tidak diketahui.<br>Silakan coba lagi atau hubungi admin.'
        };
        
        console.log('[Peminjaman Form] Showing error dialog:', errorInfo);
        
        showError(errorInfo.title, errorInfo.message);
    }

    // ========================================
    // SHOW CONFIRMATION DIALOG
    // ========================================
    
    function showConfirmation(durasiHari) {
        console.log('[Peminjaman Form] Showing confirmation dialog...');
        console.log('[Peminjaman Form] Durasi:', durasiHari, 'hari');
        
        const catatanValue = document.getElementById('catatan-peminjaman')?.value?.trim() || '';
        
        const htmlContent = `
            <div class="text-start py-2">
                <p class="mb-3">
                    <i class="fas fa-info-circle me-2 text-info"></i>
                    Pastikan detail peminjaman berikut sudah benar:
                </p>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <th style="width: 40%; color: #666;">
                                        <i class="fas fa-user me-2 text-primary"></i>Member
                                    </th>
                                    <td><strong>${state.data.memberName || 'N/A'}</strong></td>
                                </tr>
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-book me-2 text-success"></i>Buku
                                    </th>
                                    <td>${state.data.bookTitle || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-qrcode me-2 text-warning"></i>Kode Eksemplar
                                    </th>
                                    <td><code>${state.data.kodeEksemplar || 'N/A'}</code></td>
                                </tr>
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-clock me-2 text-info"></i>Durasi
                                    </th>
                                    <td>
                                        <span class="badge bg-info">${durasiHari} hari kerja</span>
                                    </td>
                                </tr>
                                ${catatanValue ? `
                                <tr>
                                    <th style="color: #666;">
                                        <i class="fas fa-comment me-2 text-secondary"></i>Catatan
                                    </th>
                                    <td><em>"${catatanValue}"</em></td>
                                </tr>
                                ` : ''}
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <p class="text-muted small mb-0">
                    <i class="fas fa-question-circle me-1"></i>
                    Lanjutkan proses peminjaman?
                </p>
            </div>
        `;
        
        const config = window.DarkModeUtils ? window.DarkModeUtils.getSwalConfig({
            title: 'Konfirmasi Peminjaman',
            html: htmlContent,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check me-2"></i>Ya, Proses Sekarang',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
            reverseButtons: true,
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-success me-2 px-4',
                cancelButton: 'btn btn-secondary px-4'
            }
        }) : {
            title: 'Konfirmasi Peminjaman',
            html: htmlContent,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Proses Sekarang',
            cancelButtonText: 'Batal',
            reverseButtons: true
        };
        
        return Swal.fire(config);
    }

    // ========================================
    // UI HELPER FUNCTIONS
    // ========================================
    
    function disableButton(disabled) {
        const btn = document.getElementById('btn-process-peminjaman');
        if (!btn) {
            console.warn('[Peminjaman Form] Button not found for disable/enable');
            return;
        }
        
        console.log('[Peminjaman Form] Setting button disabled:', disabled);
        
        btn.disabled = disabled;
        
        if (disabled) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            btn.classList.add('disabled');
        } else {
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Proses Peminjaman';
            btn.classList.remove('disabled');
        }
    }

    function showError(title, message) {
        console.log('[Peminjaman Form] Showing error dialog');
        console.log('[Peminjaman Form] Title:', title);
        console.log('[Peminjaman Form] Message:', message);
        
        if (window.DarkModeUtils && typeof window.DarkModeUtils.showError === 'function') {
            window.DarkModeUtils.showError(title, message);
        } else {
            Swal.fire({
                title: title,
                html: message,
                icon: 'error',
                confirmButtonText: '<i class="fas fa-times me-2"></i>Tutup',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-secondary px-4'
                }
            });
        }
    }

    // ========================================
    // PUBLIC API
    // ========================================
    
    return {
        init: init,
        state: state
    };
})();

// ========================================
// AUTO-INITIALIZE
// ========================================

console.log('[Peminjaman Form] ===== SCRIPT LOADED =====');
console.log('[Peminjaman Form] Load timestamp:', new Date().toISOString());
console.log('[Peminjaman Form] Document readyState at load:', document.readyState);

// Initialize immediately
PeminjamanForm.init();