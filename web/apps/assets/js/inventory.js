/**
 * inventory.js - Enhanced & Optimized Version
 * Path: web/apps/assets/js/inventory.js
 * 
 * FEATURES:
 * - UC-3: Fast & Responsive RFID Scanning with proper alert colors (Now Multi-UID Support)
 * - UC-4: Auto-generate unique codes from backend (ROB-001, ROB-002, ...)
 * - UC-5: Condition input per book unit + individual scan
 * - UC-6: Multi-author toggle with conditional role field
 * - Bug fixes: proper error handling, timeout management, duplicate prevention
 */

/**
 * inventory.js - Enhanced & Optimized Version
 * Path: web/apps/assets/js/inventory.js
 * 
 * FEATURES:
 * - UC-3: Fast & Responsive RFID Scanning with proper alert colors (Now Multi-UID Support)
 * - UC-4: Auto-generate unique codes from backend (ROB-001, ROB-002, ...)
 * - UC-5: Condition input per book unit + individual scan
 * - UC-6: Multi-author toggle with conditional role field
 * - Bug fixes: proper error handling, timeout management, duplicate prevention
 */

// ========================================
// GLOBAL STATE MANAGEMENT
// ========================================
let scannedRFIDs = [];
let isScanning = false; // Prevent double-click scanning

/**
 * UC-3: TRIGGER RFID SCAN (Multi-UID Support)
 * Fast & Responsive dengan Alert Hijau/Merah
 */
function triggerScan(isIndividual = false, index = null) {
    // Prevent multiple simultaneous scans
    if (isScanning) {
        console.warn('[SCAN BLOCKED] Already scanning...');
        return;
    }
    
    const container = document.getElementById('unit-rfid-container');
    const btnScan = isIndividual ? document.getElementById(`btn-scan-individual-${index}`) : document.getElementById('btnScanTrigger');
    const btnSimpan = document.getElementById('btnSimpan');
    const inputStok = document.getElementById('input_stok');
    const inputJudul = document.getElementById('input_judul');
    
    if (!btnScan || !inputJudul) return;

    // UC-3: Validasi - Cek limit stok
    const jumlahStok = parseInt(inputStok?.value || 1);
    const sisaScan = jumlahStok - scannedRFIDs.length;
    
    if (sisaScan <= 0 && !isIndividual) {
        Swal.fire({
            icon: 'warning',
            title: 'Limit Tercapai',
            text: `Jumlah scan sudah sesuai dengan stok (${jumlahStok} unit). Tidak perlu scan lagi.`,
            confirmButtonColor: '#ffc107',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Set scanning state
    isScanning = true;
    
    // API Path dengan limit untuk multi
    let apiPath = '../includes/api/check_latest_uid.php';
    if (!isIndividual) {
        apiPath += `?limit=${sisaScan}`;
    }

    // Tambahkan parameter unik untuk no-cache
    const uniqueApiPath = apiPath + (apiPath.includes('?') ? '&' : '?') + '_=' + Date.now();

    // UC-3: UI Feedback - Loading State
    btnScan.disabled = true;
    const originalHTML = btnScan.innerHTML;
    btnScan.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Scanning...';
    btnScan.classList.remove('btn-primary');
    btnScan.classList.add('btn-warning');

    // UC-3: API Call dengan Timeout Protection (5 detik)
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);

    fetch(uniqueApiPath, { 
        signal: controller.signal
    })
    .then(res => {
        clearTimeout(timeoutId);
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        return res.json();
    })
    .then(async data => {
        if (data.success) {
            // UC-3: SUCCESS SCENARIO
            // ✅ UID BERHASIL DITEMUKAN - ALERT HIJAU
            
            let uids = isIndividual ? [data] : data.uids; // Single atau multi
            
            // UC-4: Generate Kode Eksemplar Unik dari Backend (single call untuk array kode)
            const judul = inputJudul.value || 'BOOK';
            const kodePrefix = judul
                .substring(0, 3)
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, ''); // Remove special chars
            
            // Fetch array kode sekaligus dengan count = uids.length
            const codeRes = await fetch(`../includes/api/get_next_code.php?prefix=${kodePrefix}&count=${uids.length}`);
            const codeData = await codeRes.json();
            
            if (!codeData.success) {
                throw new Error(codeData.message);
            }
            
            const nextCodes = codeData.next_codes; // array kode GAD-001 dst
            
            for (let i = 0; i < uids.length; i++) {
                const uidData = uids[i];
                // Cek duplikat UID
                const isDuplicate = scannedRFIDs.some(item => item.uid_buffer_id === uidData.id);
                
                if (isDuplicate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'UID Duplikat',
                        text: `UID ${uidData.uid} sudah di-scan sebelumnya!`,
                        confirmButtonColor: '#ffc107'
                    });
                    continue;
                }
                
                const kodeEksemplar = nextCodes[i];  // assign kode dari array
                
                const rfidData = {
                    uid_buffer_id: uidData.id,
                    uid: uidData.uid,
                    kode_eksemplar: kodeEksemplar,
                    kondisi: 'baik' // Default condition
                };
                
                if (isIndividual && index !== null) {
                    // Replace existing di index
                    scannedRFIDs[index] = rfidData;
                } else {
                    scannedRFIDs.push(rfidData);
                }
            }
            
            // Update UI Components
            renderScannedRFIDs();
            updateScanBadge();
            updateHiddenInput();
            
            // Enable save button jika cukup
            if (btnSimpan && scannedRFIDs.length === jumlahStok) {
                btnSimpan.disabled = false;
            }
            
            // UC-3: ALERT HIJAU - Success Notification
            const count = uids.length;
            Swal.fire({
                icon: 'success',
                title: `✓ ${count > 1 ? 'Multiple' : ''} UID Berhasil Ditemukan!`,
                html: `
                    <div class="text-start">
                        <p><strong>Jumlah:</strong> ${count} UID</p>
                        <p class="text-muted small mb-0">Scan berhasil pada ${new Date().toLocaleTimeString('id-ID')}</p>
                    </div>
                `,
                confirmButtonColor: '#28a745',
                confirmButtonText: 'OK',
                timer: 2500,
                timerProgressBar: true
            });
            
            console.log('[SCAN SUCCESS]', scannedRFIDs);
            
        } else {
            // UC-3: FAILED SCENARIO
            // UID TIDAK DITEMUKAN - ALERT MERAH
            
            Swal.fire({
                icon: 'error',
                title: '✗ UID Tidak Ditemukan',
                html: `
                    <p>${data.message || 'Tidak ada tag RFID yang terdeteksi.'}</p>
                    <hr>
                    <div class="text-start small text-muted">
                        <strong>Checklist:</strong>
                        <ul class="mb-0 ps-3">
                            <li>Pastikan tag RFID sudah ditempelkan pada reader</li>
                            <li>Periksa koneksi hardware ESP32</li>
                            <li>Tag belum pernah digunakan sebelumnya</li>
                        </ul>
                    </div>
                `,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Coba Lagi'
            });
            
            console.warn('[SCAN FAILED]', data.message);
        }
        
        resetScanUI(btnScan, originalHTML, isIndividual);
        isScanning = false;
    })
    .catch(err => {
        clearTimeout(timeoutId);
        
        // UC-3: ALTERNATIVE FLOW - Error Handling
        let errorMessage = 'Gagal terhubung ke API scanner.';
        
        if (err.name === 'AbortError') {
            errorMessage = 'Request timeout (>5 detik). Periksa koneksi hardware.';
        } else if (err.message.includes('Failed to fetch')) {
            errorMessage = 'Koneksi jaringan terputus.';
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Error Sistem',
            text: errorMessage,
            confirmButtonColor: '#dc3545',
            footer: '<small>Periksa console untuk detail error</small>'
        });
        
        console.error('[SCAN ERROR]', err);
        resetScanUI(btnScan, originalHTML, isIndividual);
        isScanning = false;
    });
}

/**
 * Reset Scan UI to Normal State (Support Individual Button)
 */
function resetScanUI(btn, originalHTML, isIndividual = false) {
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        btn.classList.remove('btn-warning');
        btn.classList.add(isIndividual ? 'btn-mini btn-primary' : 'btn-primary');
    }
}

/**
 * UC-4 & UC-5: Render Scanned RFIDs dengan Kondisi (Tambah Tombol Scan Individu)
 */
function renderScannedRFIDs() {
    const container = document.getElementById('unit-rfid-container');
    
    if (!container) return;
    
    if (scannedRFIDs.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-barcode fa-4x mb-3 d-block opacity-50"></i>
                <p class="mb-0 fw-bold">Belum ada RFID yang di-scan</p>
                <small>Klik tombol di atas untuk mulai scan</small>
            </div>
        `;
        return;
    }
    
    let html = '';
    scannedRFIDs.forEach((item, index) => {
        // UC-5: Badge Kondisi dengan Warna
        const kondisiConfig = {
            'baik': { badge: 'bg-success', icon: 'check-circle', label: '✓ Baik' },
            'rusak_ringan': { badge: 'bg-warning', icon: 'exclamation-triangle', label: '⚠ Rusak Ringan' },
            'rusak_berat': { badge: 'bg-danger', icon: 'times-circle', label: '✗ Rusak Berat' },
            'hilang': { badge: 'bg-dark', icon: 'question-circle', label: '? Hilang' }
        };
        
        const kondisiInfo = kondisiConfig[item.kondisi] || kondisiConfig['baik'];
        
        html += `
            <div class="rfid-scan-card shadow-sm" id="rfid-card-${index}">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="flex-grow-1">
                        <!-- Header Row -->
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-info me-2">#${index + 1}</span>
                            <strong class="text-primary me-auto">${item.kode_eksemplar}</strong>
                            <span class="badge ${kondisiInfo.badge}">
                                <i class="fas fa-${kondisiInfo.icon} me-1"></i>${kondisiInfo.label}
                            </span>
                        </div>
                        
                        <!-- UID Display -->
                        <div class="small text-muted mb-3">
                            <i class="fas fa-qrcode me-1"></i>
                            UID: <code class="bg-light px-2 py-1 rounded">${item.uid}</code>
                        </div>
                        
                        <!-- Input Fields -->
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small mb-1 fw-bold">Kode Eksemplar</label>
                                <input type="text" 
                                    class="form-control form-control-sm" 
                                    value="${item.kode_eksemplar}" 
                                    onchange="updateRFIDData(${index}, 'kode_eksemplar', this.value)"
                                    placeholder="Contoh: ROB-001">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1 fw-bold">Kondisi Buku</label>
                                <select class="form-select form-select-sm" 
                                        onchange="updateRFIDData(${index}, 'kondisi', this.value); renderScannedRFIDs();">
                                    <option value="baik" ${item.kondisi === 'baik' ? 'selected' : ''}> Baik</option>
                                    <option value="rusak_ringan" ${item.kondisi === 'rusak_ringan' ? 'selected' : ''}> Rusak Ringan</option>
                                    <option value="rusak_berat" ${item.kondisi === 'rusak_berat' ? 'selected' : ''}> Rusak Berat</option>
                                    <option value="hilang" ${item.kondisi === 'hilang' ? 'selected' : ''}> Hilang</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-2 ms-3">
                        <button class="btn btn-mini btn-danger" onclick="removeScan(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button id="btn-scan-individual-${index}" class="btn btn-mini btn-primary" onclick="triggerScan(true, ${index})">
                            <i class="fas fa-qrcode"></i> Re-Scan
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

/**
 * Update RFID Data
 */
function updateRFIDData(index, field, value) {
    if (scannedRFIDs[index]) {
        scannedRFIDs[index][field] = value;
        updateHiddenInput();
    }
}

/**
 * Remove Single Scan
 */
function removeScan(index) {
    scannedRFIDs.splice(index, 1);
    renderScannedRFIDs();
    updateScanBadge();
    updateHiddenInput();
    
    const btnSimpan = document.getElementById('btnSimpan');
    if (btnSimpan) {
        btnSimpan.disabled = scannedRFIDs.length !== parseInt(document.getElementById('input_stok')?.value || 1);
    }
}

/**
 * Clear All Scans
 */
function clearAllScans() {
    if (scannedRFIDs.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Tidak Ada Data',
            text: 'Belum ada RFID yang di-scan.',
            timer: 1500,
            showConfirmButton: false
        });
        return;
    }
    
    Swal.fire({
        title: 'Reset Semua Scan?',
        text: `Anda akan menghapus ${scannedRFIDs.length} unit RFID yang sudah di-scan!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-redo me-1"></i>Ya, Reset Semua',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            scannedRFIDs = [];
            renderScannedRFIDs();
            updateScanBadge();
            updateHiddenInput();
            
            const btnSimpan = document.getElementById('btnSimpan');
            if (btnSimpan) {
                btnSimpan.disabled = true;
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Reset Berhasil!',
                text: 'Semua scan telah dihapus.',
                timer: 1500,
                showConfirmButton: false
            });
            
            console.log('[ALL SCANS CLEARED]');
        }
    });
}

/**
 * Update Scan Count Badge
 */
function updateScanBadge() {
    const badge = document.getElementById('badge-scan-count');
    if (badge) {
        const count = scannedRFIDs.length;
        badge.textContent = `${count} Unit${count !== 1 ? 's' : ''}`;
        
        // Dynamic badge color
        if (count === 0) {
            badge.className = 'badge bg-secondary me-2';
        } else if (count < 5) {
            badge.className = 'badge bg-info me-2';
        } else {
            badge.className = 'badge bg-success me-2';
        }
    }
}

/**
 * Update Hidden Input (JSON Data)
 */
function updateHiddenInput() {
    const input = document.getElementById('input_rfid_units');
    if (input) {
        input.value = JSON.stringify(scannedRFIDs);
    }
}

/**
 * Switch Tab Helper
 */
function switchTab(tabId) {
    const tabButton = document.getElementById(tabId);
    if (tabButton) {
        tabButton.click();
    }
}

/**
 * Toggle Row Detail (Main Table)
 */
function toggleRow(rowId) {
    const row = document.getElementById(rowId);
    const icon = document.getElementById('icon-' + rowId);
    
    if (row) {
        row.classList.toggle('d-none');
        if (icon) {
            icon.classList.toggle('active');
        }
    }
}

// ========================================
// FORM SUBMISSION HANDLER
// ========================================
const formRegistrasi = document.getElementById('formRegistrasiAset');
if (formRegistrasi) {
    formRegistrasi.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validasi: scannedRFIDs.length == jumlah_eksemplar
        const jumlahStok = parseInt(document.getElementById('input_stok')?.value || 1);
        if (scannedRFIDs.length !== jumlahStok) {
            Swal.fire({
                icon: 'warning',
                title: 'Scan RFID Required',
                text: `Anda harus scan tepat ${jumlahStok} RFID sebelum menyimpan!`,
                confirmButtonColor: '#ffc107'
            });
            switchTab('rfid-tab-btn');
            return;
        }
        
        const btn = document.getElementById('btnSimpan');
        const originalContent = btn.innerHTML;

        // Loading state
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

        fetch('../controllers/InventoryController.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    html: data.message,
                    confirmButtonColor: '#28a745'
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Menyimpan!',
                    html: data.message,
                    confirmButtonColor: '#dc3545'
                });
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        })
        .catch(err => {
            console.error('[SUBMIT ERROR]', err);
            Swal.fire({
                icon: 'error',
                title: 'Error Sistem',
                text: 'Terjadi kesalahan saat menyimpan data.',
                confirmButtonColor: '#dc3545'
            });
            btn.disabled = false;
            btn.innerHTML = originalContent;
        });
    });
}

// ========================================
// UC-6: MULTI-AUTHOR TOGGLE LOGIC
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('[INVENTORY JS] Loaded & Ready');
    
    // UC-6: Switch Multi-Author
    const switchMulti = document.getElementById('switchMultiAuthor');
    const peranField = document.getElementById('peran_field');

    if (switchMulti && peranField) {
        switchMulti.addEventListener('change', function() {
            if (this.checked) {
                // Multi-author mode: show role field
                peranField.classList.remove('d-none');
                console.log('[MULTI-AUTHOR] Enabled');
            } else {
                // Single author mode: hide role field
                peranField.classList.add('d-none');
                console.log('[MULTI-AUTHOR] Disabled');
            }
        });
    }

    // Dropdown Author Baru
    const authorSelect = document.getElementById('author_select');
    const newAuthorFields = document.getElementById('new_author_fields');

    if (authorSelect && newAuthorFields) {
        authorSelect.addEventListener('change', function() {
            newAuthorFields.classList.toggle('d-none', this.value !== 'new');
        });
    }

    // Dropdown Publisher Baru
    const publisherSelect = document.getElementById('publisher_select');
    const newPubFields = document.getElementById('new_pub_fields');

    if (publisherSelect && newPubFields) {
        publisherSelect.addEventListener('change', function() {
            newPubFields.classList.toggle('d-none', this.value !== 'new');
        });
    }

    // UC-2: Counter Biografi (Max 200 Karakter)
    const bioText = document.getElementById('author_biografi_new');
    const bioCount = document.getElementById('char-count-new');

    if (bioText && bioCount) {
        bioText.addEventListener('input', function() {
            const currentLen = this.value.length;
            bioCount.textContent = `${currentLen}/200`;
            
            if (currentLen >= 200) {
                bioCount.classList.add('text-danger', 'fw-bold');
            } else {
                bioCount.classList.remove('text-danger', 'fw-bold');
            }
        });
    }
    
    // Cover Image Preview
    const inputCover = document.getElementById('input_cover');
    if (inputCover) {
        inputCover.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('cover-preview');
            const previewImg = document.getElementById('cover-preview-img');
            
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Terlalu Besar',
                        text: 'Ukuran maksimal 2MB!',
                        confirmButtonColor: '#dc3545'
                    });
                    this.value = '';
                    preview.classList.add('d-none');
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format Tidak Valid',
                        text: 'Gunakan: JPG, PNG, GIF, WEBP',
                        confirmButtonColor: '#dc3545'
                    });
                    this.value = '';
                    preview.classList.add('d-none');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            } else {
                preview.classList.add('d-none');
            }
        });
    }
    
    // Initialize
    updateScanBadge();
});

// ========================================
// MODAL RESET ON CLOSE
// ========================================
const modalTambah = document.getElementById('modalTambahAset');
if (modalTambah) {
    modalTambah.addEventListener('hidden.bs.modal', function() {
        // Reset form
        const form = this.querySelector('form');
        if (form) form.reset();
        
        // Reset scans
        scannedRFIDs = [];
        renderScannedRFIDs();
        updateScanBadge();
        updateHiddenInput();
        
        // Reset preview
        const preview = document.getElementById('cover-preview');
        if (preview) preview.classList.add('d-none');
        
        // Reset counter
        const charCount = document.getElementById('char-count-new');
        if (charCount) {
            charCount.textContent = '0/200';
            charCount.classList.remove('text-danger', 'fw-bold');
        }
        
        // Hide new fields
        const newAuthorFields = document.getElementById('new_author_fields');
        const newPubFields = document.getElementById('new_pub_fields');
        const peranField = document.getElementById('peran_field');
        if (newAuthorFields) newAuthorFields.classList.add('d-none');
        if (newPubFields) newPubFields.classList.add('d-none');
        if (peranField) peranField.classList.add('d-none');
        
        // Reset buttons
        const btnSimpan = document.getElementById('btnSimpan');
        if (btnSimpan) btnSimpan.disabled = true;
        
        resetScanUI();
        isScanning = false;
        
        // Back to first tab
        switchTab('asset-tab-btn');
        
        console.log('[MODAL RESET] All data cleared');
    });
}

// Filter Kategori Table
const filterKategori = document.getElementById('filterKategori');
if (filterKategori) {
    filterKategori.addEventListener('change', function() {
        const val = this.value.toLowerCase();
        const rows = document.querySelectorAll('.table tbody tr');
        rows.forEach(row => {
            const kategori = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase(); // Kolom Kategori
            row.style.display = (val === '' || kategori.includes(val)) ? '' : 'none';
        });
    });
}

/**
 * Function: Reset Expired UID
 * Dipanggil dari button Reset UID di inventory.php
 * Menggunakan SweetAlert2 untuk konfirmasi & notifikasi
 */

function resetExpiredUID() {
    Swal.fire({
        title: 'Reset UID Expired?',
        html: `
            <div class="text-start">
                <p class="mb-2">Fungsi ini akan:</p>
                <ul class="text-muted small">
                    <li>Mereset timestamp UID yang sudah expired (> 5 menit)</li>
                    <li>Menggunakan sistem FIFO (First In First Out)</li>
                    <li>UID yang paling lama akan di-reset terlebih dahulu</li>
                    <li>Status tetap <code>pending</code> dan <code>unlabeled</code></li>
                </ul>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-undo-alt me-2"></i>Ya, Reset Sekarang',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: () => {
            return fetch('../includes/api/reset_expired_uid.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response error');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Reset gagal');
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(
                    `Request failed: ${error.message}`
                );
            });
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const data = result.value;
            
            // Format detail UID yang di-reset
            let detailHTML = '';
            if (data.details && data.details.length > 0) {
                detailHTML = '<div class="mt-3 text-start"><strong>Detail UID:</strong><ul class="small text-muted mt-2">';
                data.details.forEach(uid => {
                    detailHTML += `<li><code>${uid.uid}</code> - Expired ${uid.minutes_old} menit lalu</li>`;
                });
                detailHTML += '</ul></div>';
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Reset Berhasil!',
                html: `
                    <div class="text-center">
                        <h4 class="text-success mb-3">
                            <i class="fas fa-check-circle me-2"></i>
                            ${data.reset_count} UID Berhasil Di-reset
                        </h4>
                        <p class="text-muted mb-2">Timestamp telah diperbarui</p>
                        ${detailHTML}
                        <div class="alert alert-info mt-3 text-start">
                            <small>
                                <strong>Catatan:</strong><br>
                                UID yang di-reset dapat langsung digunakan untuk scan baru dalam interval 5 menit ke depan.
                            </small>
                        </div>
                    </div>
                `,
                confirmButtonColor: '#28a745',
                confirmButtonText: '<i class="fas fa-check me-2"></i>OK'
            });
            
            // Log ke console
            console.log('[RESET UID SUCCESS]', data);
            
            // Optional: Refresh statistik jika ada
            if (typeof updateStatistics === 'function') {
                updateStatistics();
            }
        }
    });
}

/**
 * Alternative: Reset dengan limit tertentu
 * Usage: resetExpiredUID(10) -> reset 10 UID tertua
 */
function resetExpiredUIDWithLimit(limit = 10) {
    fetch(`../includes/api/reset_expired_uid.php?limit=${limit}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Reset Berhasil',
                    text: `${data.reset_count} UID berhasil di-reset`,
                    timer: 3000,
                    showConfirmButton: false
                });
                console.log('[RESET UID LIMITED]', data);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Reset Gagal',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('[RESET UID ERROR]', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan saat reset UID'
            });
        });
}

/**
 * ========================================
 * SWITCH HANDLER: Enable/Disable Keterangan Field
 * ========================================
 */
document.addEventListener('DOMContentLoaded', function() {
    const switchKeterangan = document.getElementById('switchKeterangan');
    const inputKeterangan = document.getElementById('input_keterangan');
    const wrapperField = document.getElementById('keterangan_field_wrapper');
    
    if (switchKeterangan && inputKeterangan) {
        // Initial state: disabled
        inputKeterangan.disabled = true;
        inputKeterangan.value = '';
        
        // Toggle handler
        switchKeterangan.addEventListener('change', function() {
            if (this.checked) {
                // Enable field
                inputKeterangan.disabled = false;
                inputKeterangan.focus();
                wrapperField.classList.add('field-enabled');
                
                // Animation
                inputKeterangan.style.transition = 'all 0.3s ease';
                inputKeterangan.style.opacity = '1';
                
                console.log('[KETERANGAN] Field enabled');
            } else {
                // Disable field
                inputKeterangan.disabled = true;
                inputKeterangan.value = '';
                wrapperField.classList.remove('field-enabled');
                
                // Animation
                inputKeterangan.style.opacity = '0.6';
                
                console.log('[KETERANGAN] Field disabled & cleared');
            }
        });
    }
});

console.log('[INVENTORY JS] Version 2.0 - Enhanced & Optimized');