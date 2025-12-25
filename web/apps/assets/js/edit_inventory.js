/**
 * edit_inventory.js - FIXED & ENHANCED VERSION
 * Path: web/apps/assets/js/edit_inventory.js
 * 
 * NEW FEATURES:
 * ✅ Fixed delete eksemplar functionality
 * ✅ Added "Tambah Eksemplar Baru" with RFID scan
 * ✅ Integration with AddEksemplarController.php
 * ✅ Real-time UID checking via check_latest_uid.php
 * ✅ Auto-generate kode eksemplar
 * ✅ Enhanced error handling
 */

// ========================================
// GLOBAL STATE
// ========================================
let currentAuthorData = null;
let currentPublisherData = null;
let deletedEksemplarIds = []; // Track deleted IDs
let scannedNewUIDs = []; // Track new scanned UIDs for add

// ========================================
// DELETE EKSEMPLAR FUNCTIONS (FIXED)
// ========================================

/**
 * Delete Single Eksemplar
 */
function deleteEksemplar(eksId) {
    Swal.fire({
        title: 'Hapus Eksemplar?',
        text: 'Unit RFID ini akan dihapus dari sistem',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Add to deleted list
            if (!deletedEksemplarIds.includes(eksId)) {
                deletedEksemplarIds.push(eksId);
            }
            
            // Update hidden input
            updateDeletedInput();
            
            // Remove row from table
            const row = document.getElementById(`row-eks-${eksId}`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    updateEksemplarCount();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Eksemplar berhasil dihapus',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }, 300);
            }
        }
    });
}

/**
 * Delete Selected Eksemplars (Bulk Delete)
 */
function deleteSelected() {
    const checkboxes = document.querySelectorAll('.check-item:checked');
    
    if (checkboxes.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Tidak Ada Pilihan',
            text: 'Silakan pilih minimal 1 unit untuk dihapus',
            confirmButtonColor: '#ffc107'
        });
        return;
    }
    
    Swal.fire({
        title: `Hapus ${checkboxes.length} Eksemplar?`,
        text: 'Unit RFID yang dipilih akan dihapus dari sistem',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus Semua!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            checkboxes.forEach(cb => {
                const eksId = parseInt(cb.value);
                
                // Add to deleted list
                if (!deletedEksemplarIds.includes(eksId)) {
                    deletedEksemplarIds.push(eksId);
                }
                
                // Remove row
                const row = document.getElementById(`row-eks-${eksId}`);
                if (row) {
                    row.remove();
                }
            });
            
            // Update hidden input
            updateDeletedInput();
            updateEksemplarCount();
            
            // Uncheck "check all"
            const checkAll = document.getElementById('checkAll');
            if (checkAll) checkAll.checked = false;
            
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: `${checkboxes.length} eksemplar berhasil dihapus`,
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

/**
 * Update Hidden Input for Deleted IDs
 */
function updateDeletedInput() {
    const form = document.getElementById('formEditInventory');
    let hiddenInput = document.getElementById('deleted_eksemplar');
    
    if (!hiddenInput && form) {
        // Create if doesn't exist
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'deleted_eksemplar';
        hiddenInput.name = 'delete_eksemplar';
        form.appendChild(hiddenInput);
    }
    
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(deletedEksemplarIds);
    }
    console.log('[DELETE] Updated deleted IDs:', deletedEksemplarIds);
}

/**
 * Update Eksemplar Count Display
 */
function updateEksemplarCount() {
    const tbody = document.getElementById('eksemplar-container');
    if (tbody) {
        const visibleRows = tbody.querySelectorAll('tr:not(.d-none)').length;
        console.log('[COUNT] Visible eksemplar:', visibleRows);
    }
}

// ========================================
// CHECK ALL CHECKBOX HANDLER
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.check-item');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }
});

// ========================================
// ADD NEW EKSEMPLAR FUNCTIONS (NEW)
// ========================================

/**
 * Open Modal to Add New Eksemplar with RFID Scan
 */
function openAddEksemplarModal(reset = true) {
    const bookId = document.querySelector('input[name="book_id"]').value;
    
    // reset only when requested (initial open)
    if (reset) scannedNewUIDs = [];
    
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    Swal.fire({
        title: '<i class="fas fa-plus-circle me-2"></i>Tambah Eksemplar Baru',
        html: `
            <div class="text-start">
                <div class="alert alert-info border-0 mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Panduan:</strong> Scan RFID untuk unit baru yang akan ditambahkan
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Jumlah Unit Baru</label>
                    <input type="number" id="swal_jumlah_unit" class="form-control" value="1" min="1" max="50">
                    <small class="text-muted">Maksimal 50 unit per sekali tambah</small>
                </div>
                
                <div class="text-center mb-3">
                    <button type="button" class="btn btn-primary btn-lg px-4" onclick="startScanNewEksemplar()">
                        <i class="fas fa-qrcode me-2"></i>MULAI SCAN RFID
                    </button>
                </div>
                
                <div id="scan_results_container" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto; min-height: 100px;">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-barcode fa-3x opacity-50 mb-2"></i>
                        <p class="mb-0">Belum ada scan</p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <span class="badge bg-info" id="scan_count_badge">0 UID Terscan</span>
                </div>
            </div>
        `,
        width: 700,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-2"></i>Simpan Unit Baru',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
        showLoaderOnConfirm: true,
        // removed resetting scannedNewUIDs from didOpen
        didOpen: () => {
            // keep existing scannedNewUIDs if re-opening after scan
            updateScanResultsUI();
        },
        preConfirm: () => {
            return submitNewEksemplar(bookId);
        },
        allowOutsideClick: () => !Swal.isLoading(),
        customClass: isDark ? { popup: 'swal-dark' } : {}
    });
}

/**
 * Start Scanning New Eksemplar UIDs
 */
function startScanNewEksemplar() {
    const jumlahUnit = parseInt(document.getElementById('swal_jumlah_unit').value) || 1;
    
    if (scannedNewUIDs.length >= jumlahUnit) {
        Swal.fire({
            icon: 'warning',
            title: 'Scan Sudah Cukup',
            text: `Anda sudah scan ${scannedNewUIDs.length} UID. Klik Simpan untuk melanjutkan.`,
            confirmButtonColor: '#ffc107'
        }).then(() => {
            openAddEksemplarModal();
        });
        return;
    }
    
    // Show loading
    Swal.fire({
        title: 'Scanning RFID...',
        html: '<i class="fas fa-spinner fa-spin fa-3x mb-3"></i><br>Dekatkan RFID tag ke reader',
        allowOutsideClick: false,
        showConfirmButton: false
    });
    
    // Call API to check latest UID
    fetch('../includes/api/check_latest_uid.php?limit=1')
        .then(res => res.json())
        .then(data => {
            Swal.close();
            
            if (data.success && data.uids && data.uids.length > 0) {
                const uid = data.uids[0];
                
                // Check duplicate
                const isDuplicate = scannedNewUIDs.some(u => u.id === uid.id);
                
                if (isDuplicate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'UID Sudah Di-scan',
                        text: 'UID ini sudah ada dalam daftar',
                        confirmButtonColor: '#ffc107'
                    }).then(() => {
                        openAddEksemplarModal(false);
                    });
                    return;
                }
                
                // Add to scanned list
                scannedNewUIDs.push(uid);
                
                // Update UI
                updateScanResultsUI();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Scan Berhasil!',
                    text: `UID: ${uid.uid}`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    openAddEksemplarModal(false);
                });
                
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Scan Gagal',
                    text: data.message || 'Tidak ada UID terdeteksi',
                    confirmButtonColor: '#dc3545'
                }).then(() => {
                    openAddEksemplarModal(false);
                });
            }
        })
        .catch(err => {
            Swal.close();
            console.error('[SCAN ERROR]', err);
            
            Swal.fire({
                icon: 'error',
                title: 'Error Sistem',
                text: 'Gagal menghubungi server scanner',
                confirmButtonColor: '#dc3545'
            }).then(() => {
                openAddEksemplarModal(false);
            });
        });
}

/**
 * Update Scan Results UI in Modal
 */
function updateScanResultsUI() {
    const container = document.getElementById('scan_results_container');
    const badge = document.getElementById('scan_count_badge');
    if (!container) return;

    // determine prefix: prefer explicit kode_buku, else derive from judul (first 3 alnum uppercase)
    let prefix = '';
    const kodeInput = document.querySelector('input[name="kode_buku"]');
    const judulEl = document.querySelector('input[name="judul"]') || document.querySelector('.book-title');
    if (kodeInput && kodeInput.value.trim() !== '') {
        prefix = kodeInput.value.trim().toUpperCase();
    } else if (judulEl) {
        const t = (judulEl.value || judulEl.textContent || '').toUpperCase();
        const m = (t.match(/[A-Z0-9]/g) || []).slice(0,3).join('');
        prefix = m || 'EKS';
    } else {
        prefix = 'EKS';
    }

    // calculate provisional max number from DOM (existing eksemplar) but limited to same prefix
    const existingRows = document.querySelectorAll('#eksemplar-container tr[id^="row-eks-"]');
    let maxNumber = 0;
    existingRows.forEach(row => {
        const kodeCell = row.querySelector('td:nth-child(2) strong');
        if (kodeCell) {
            const match = kodeCell.textContent.match(new RegExp(`^${prefix}-(\\d+)`));
            if (match) maxNumber = Math.max(maxNumber, parseInt(match[1],10));
            else {
                // also accept older EKS- prefixed rows for continuity if no prefix-match found
                const m2 = kodeCell.textContent.match(/-(\d+)$/);
                if (m2) maxNumber = Math.max(maxNumber, parseInt(m2[1],10));
            }
        }
    });

    if (scannedNewUIDs.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-barcode fa-3x opacity-50 mb-2"></i>
                <p class="mb-0">Belum ada scan</p>
            </div>
        `;
    } else {
        let html = '<div class="list-group">';
        scannedNewUIDs.forEach((uid, index) => {
            const newNumber = maxNumber + index + 1;
            const kodeEksemplar = `${prefix}-${String(newNumber).padStart(4, '0')}`;

            html += `
                <div class="list-group-item d-flex justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <strong class="me-2">#${index + 1}</strong>
                            <code class="me-3">${uid.uid}</code>
                            <small class="text-muted">UID-buffer-id: ${uid.id}</small>
                        </div>

                        <div class="row g-2 align-items-center">
                            <div class="col-auto" style="min-width:160px;">
                                <label class="form-label small mb-1">Kode Eksemplar</label>
                                <input type="text" class="form-control form-control-sm kode-input" data-uid-index="${index}" value="${kodeEksemplar}">
                            </div>
                            <div class="col-auto" style="min-width:140px;">
                                <label class="form-label small mb-1">Kondisi</label>
                                <select class="form-select form-select-sm kondisi-select" data-uid-index="${index}">
                                    <option value="baik" selected>Baik</option>
                                    <option value="rusak_ringan">Rusak Ringan</option>
                                    <option value="rusak_berat">Rusak Berat</option>
                                    <option value="hilang">Hilang</option>
                                </select>
                            </div>
                            <div class="col-auto ms-auto">
                                <label class="form-label small mb-1">&nbsp;</label>
                                <div>
                                    <button type="button" class="btn btn-sm btn-danger ms-1" onclick="removeScannedUID(${index})" title="Hapus UID">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    if (badge) {
        badge.textContent = `${scannedNewUIDs.length} UID Terscan`;
        badge.className = scannedNewUIDs.length > 0 ? 'badge bg-success' : 'badge bg-info';
    }
}

/**
 * Remove Scanned UID from List
 */
function removeScannedUID(index) {
    scannedNewUIDs.splice(index, 1);
    updateScanResultsUI();
}

/**
 * Submit New Eksemplar to Server
 */
async function submitNewEksemplar(bookId) {
    if (scannedNewUIDs.length === 0) {
        Swal.showValidationMessage('Belum ada UID yang di-scan!');
        return false;
    }

    // collect user-edited kode and kondisi from modal inputs
    const kodeInputs = Array.from(document.querySelectorAll('.kode-input'));
    const kondisiSelects = Array.from(document.querySelectorAll('.kondisi-select'));

    const prepared = scannedNewUIDs.map((uid, idx) => {
        const kodeEl = kodeInputs.find(i => parseInt(i.dataset.uidIndex) === idx);
        const kondisiEl = kondisiSelects.find(s => parseInt(s.dataset.uidIndex) === idx);
        return {
            uid_buffer_id: uid.id,
            uid_raw: uid.uid,
            kode_eksemplar: (kodeEl ? kodeEl.value.trim() : (`EKS-${String(idx+1).padStart(4,'0')}`)),
            kondisi: (kondisiEl ? kondisiEl.value : 'baik')
        };
    });

    // Client-side duplicate check via API (quick feedback)
    for (const item of prepared) {
        try {
            const res = await fetch(`../includes/api/check_duplicate_uid.php?uid=${encodeURIComponent(item.uid_raw)}&book_id=${parseInt(bookId)}`);
            const json = await res.json();
            if (!json.success) {
                Swal.showValidationMessage(json.message || 'Gagal cek UID');
                return false;
            }
            if (json.isDuplicate) {
                const used = json.usedIn || {};
                Swal.showValidationMessage(`UID ${item.uid_raw} sudah terdaftar pada buku: ${used.book_title || 'N/A'} (kode ${used.kode_eksemplar || '-'})`);
                return false;
            }
        } catch (err) {
            console.error('[DUP CHECK ERROR]', err);
            Swal.showValidationMessage('Gagal menghubungi server untuk validasi UID');
            return false;
        }
    }

    // Ready to send to server
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    try {
        const res = await fetch('../controllers/AddEksemplarController.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                book_id: parseInt(bookId),
                new_eksemplar: prepared
            })
        });
        const data = await res.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: data.message || 'Eksemplar berhasil ditambahkan',
                confirmButtonColor: '#28a745',
                customClass: isDark ? { popup: 'swal-dark' } : {}
            }).then(()=> location.reload());
        } else {
            Swal.showValidationMessage(data.message || 'Gagal menambahkan eksemplar');
            return false;
        }
    } catch (err) {
        console.error('[SUBMIT ERROR]', err);
        Swal.showValidationMessage('Gagal menambahkan eksemplar (server error)');
        return false;
    }
}

// ensure modal uses dark class when open
function openAddEksemplarModal(reset = true) {
    const bookId = document.querySelector('input[name="book_id"]').value;
    if (reset) scannedNewUIDs = [];

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    Swal.fire({
        title: '<i class="fas fa-plus-circle me-2"></i>Tambah Eksemplar Baru',
        html: `
            <div class="text-start">
                <div class="alert alert-info border-0 mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Panduan:</strong> Scan RFID untuk unit baru yang akan ditambahkan
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Jumlah Unit Baru</label>
                    <input type="number" id="swal_jumlah_unit" class="form-control" value="1" min="1" max="50">
                    <small class="text-muted">Maksimal 50 unit per sekali tambah</small>
                </div>
                
                <div class="text-center mb-3">
                    <button type="button" class="btn btn-primary btn-lg px-4" onclick="startScanNewEksemplar()">
                        <i class="fas fa-qrcode me-2"></i>MULAI SCAN RFID
                    </button>
                </div>
                
                <div id="scan_results_container" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto; min-height: 100px;">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-barcode fa-3x opacity-50 mb-2"></i>
                        <p class="mb-0">Belum ada scan</p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <span class="badge bg-info" id="scan_count_badge">0 UID Terscan</span>
                </div>
            </div>
        `,
        width: 700,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-2"></i>Simpan Unit Baru',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
        showLoaderOnConfirm: true,
        didOpen: () => updateScanResultsUI(),
        preConfirm: () => submitNewEksemplar(bookId),
        allowOutsideClick: () => !Swal.isLoading(),
        customClass: isDark ? { popup: 'swal-dark' } : {}
    });
}

// ========================================
// AUTHOR SECTION HANDLERS
// ========================================

function handleAuthorChange() {
    const authorSelect = document.getElementById('author_select');
    const newAuthorFields = document.getElementById('new_author_fields');
    const existingAuthorFields = document.getElementById('existing_author_fields');
    const biografiTextarea = document.getElementById('author_biografi');
    const charCount = document.getElementById('char_count_author');
    
    if (!authorSelect) return;
    
    const selectedValue = authorSelect.value;
    
    if (selectedValue === 'new') {
        newAuthorFields.classList.remove('d-none');
        existingAuthorFields.classList.add('d-none');
        
        if (biografiTextarea) {
            biografiTextarea.value = '';
            biografiTextarea.disabled = false;
        }
        if (charCount) {
            charCount.textContent = '0/200';
        }
        
        document.getElementById('author_action').value = 'new';
        
    } else if (selectedValue && selectedValue !== '') {
        newAuthorFields.classList.add('d-none');
        existingAuthorFields.classList.remove('d-none');
        
        document.getElementById('author_action').value = 'existing';
        fetchAuthorData(selectedValue);
        
    } else {
        newAuthorFields.classList.add('d-none');
        existingAuthorFields.classList.add('d-none');
        
        if (biografiTextarea) {
            biografiTextarea.value = '';
            biografiTextarea.disabled = true;
        }
    }
}

function fetchAuthorData(authorId) {
    const biografiTextarea = document.getElementById('author_biografi');
    const charCount = document.getElementById('char_count_author');
    const loadingMsg = document.getElementById('author_loading');
    
    if (loadingMsg) loadingMsg.classList.remove('d-none');
    
    fetch(`../includes/api/get_author_data.php?id=${authorId}`)
        .then(res => res.json())
        .then(data => {
            if (loadingMsg) loadingMsg.classList.add('d-none');
            
            if (data.success) {
                currentAuthorData = data.author;
                
                if (biografiTextarea) {
                    biografiTextarea.value = data.author.biografi || '';
                    biografiTextarea.disabled = false;
                }
                
                if (charCount) {
                    const length = (data.author.biografi || '').length;
                    charCount.textContent = `${length}/200`;
                }
            }
        })
        .catch(err => {
            if (loadingMsg) loadingMsg.classList.add('d-none');
            console.error('[AUTHOR FETCH ERROR]', err);
        });
}

// ========================================
// PUBLISHER SECTION HANDLERS
// ========================================

function handlePublisherChange() {
    const publisherSelect = document.getElementById('publisher_select');
    const newPublisherFields = document.getElementById('new_publisher_fields');
    const existingPublisherFields = document.getElementById('existing_publisher_fields');
    
    if (!publisherSelect) return;
    
    const selectedValue = publisherSelect.value;
    
    if (selectedValue === 'new') {
        newPublisherFields.classList.remove('d-none');
        existingPublisherFields.classList.add('d-none');
        document.getElementById('publisher_action').value = 'new';
        
    } else if (selectedValue && selectedValue !== '') {
        newPublisherFields.classList.add('d-none');
        existingPublisherFields.classList.remove('d-none');
        document.getElementById('publisher_action').value = 'existing';
        fetchPublisherData(selectedValue);
        
    } else {
        newPublisherFields.classList.add('d-none');
        existingPublisherFields.classList.add('d-none');
    }
}

function fetchPublisherData(publisherId) {
    const loadingMsg = document.getElementById('publisher_loading');
    
    if (loadingMsg) loadingMsg.classList.remove('d-none');
    
    fetch(`../includes/api/get_publisher_data.php?id=${publisherId}`)
        .then(res => res.json())
        .then(data => {
            if (loadingMsg) loadingMsg.classList.add('d-none');
            
            if (data.success) {
                currentPublisherData = data.publisher;
                
                const alamatInput = document.getElementById('publisher_alamat');
                const teleponInput = document.getElementById('publisher_telepon');
                const emailInput = document.getElementById('publisher_email');
                
                if (alamatInput) alamatInput.value = data.publisher.alamat || '';
                if (teleponInput) teleponInput.value = data.publisher.no_telepon || '';
                if (emailInput) emailInput.value = data.publisher.email || '';
            }
        })
        .catch(err => {
            if (loadingMsg) loadingMsg.classList.add('d-none');
            console.error('[PUBLISHER FETCH ERROR]', err);
        });
}

// ========================================
// CHARACTER COUNTER
// ========================================

function initBiografiCounter() {
    const biografiTextarea = document.getElementById('author_biografi');
    const charCount = document.getElementById('char_count_author');
    
    if (biografiTextarea && charCount) {
        biografiTextarea.addEventListener('input', function() {
            const currentLength = this.value.length;
            charCount.textContent = `${currentLength}/200`;
            
            if (currentLength > 200) {
                this.value = this.value.substring(0, 200);
                charCount.textContent = '200/200';
            }
        });
    }
}

// ========================================
// FORM SUBMIT HANDLER
// ========================================

function initFormSubmit() {
    const form = document.getElementById('formEditInventory');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading
            const btnSubmit = document.getElementById('btnSimpanEdit');
            const originalText = btnSubmit.innerHTML;
            
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
            
            // Submit form
            const formData = new FormData(this);
            
            // Ensure deleted_eksemplar is set
            formData.set('delete_eksemplar', JSON.stringify(deletedEksemplarIds));
            
            console.log('[FORM SUBMIT] Deleted IDs:', deletedEksemplarIds);
            
            fetch('../controllers/EditInventoryController.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message,
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        window.location.href = data.redirect || 'inventory.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menyimpan',
                        text: data.message,
                        confirmButtonColor: '#dc3545'
                    });
                    
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalText;
                }
            })
            .catch(err => {
                console.error('[SUBMIT ERROR]', err);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error Sistem',
                    text: 'Terjadi kesalahan saat menyimpan data',
                    confirmButtonColor: '#dc3545'
                });
                
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText;
            });
        });
    }
}

// ========================================
// INIT ON DOM READY
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('[EDIT INVENTORY JS] FIXED & ENHANCED VERSION Loaded');
    
    const authorSelect = document.getElementById('author_select');
    if (authorSelect) {
        authorSelect.addEventListener('change', handleAuthorChange);
    }
    
    const publisherSelect = document.getElementById('publisher_select');
    if (publisherSelect) {
        publisherSelect.addEventListener('change', handlePublisherChange);
    }
    
    initBiografiCounter();
    initFormSubmit();
    
    console.log('[EDIT INVENTORY JS] All handlers attached successfully');
});

// ========================================
// HELPER: Switch Tab
// ========================================

function switchToTab(tabId) {
    const tabButton = document.getElementById(tabId);
    if (tabButton) {
        tabButton.click();
    }
}

console.log('[EDIT INVENTORY JS] Version 3.0 - FIXED & ENHANCED with Add Eksemplar Feature');