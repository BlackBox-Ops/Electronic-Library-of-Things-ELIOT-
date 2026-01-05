/**
 * edit_inventory.js - ENHANCED VERSION with Multiple Authors
 * Path: web/apps/assets/js/edit_inventory.js
 * 
 * NEW FEATURES:
 * ✅ Multiple authors dengan role selection
 * ✅ Add/Edit/Remove authors functionality
 * ✅ Publisher fields always visible (tidak hidden)
 * ✅ Flexible author management
 * ✅ All previous features preserved
 */

// ========================================
// GLOBAL STATE
// ========================================
let currentPublisherData = null;
let deletedEksemplarIds = [];
let scannedNewUIDs = [];
let newAuthorsCounter = 0;
let deletedAuthorIds = [];

// ========================================
// AUTHORS MANAGEMENT (NEW)
// ========================================

/**
 * Open Modal to Add New Author
 */
function openAddAuthorModal() {
    // Check if dark mode is active
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    
    Swal.fire({
        title: '<i class="fas fa-user-plus me-2"></i>Tambah Penulis',
        customClass: {
            popup: isDarkMode ? 'swal-dark' : ''
        },
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold">Pilih Penulis</label>
                    <select id="swal_author_select" class="form-select">
                        <option value="">-- Pilih Penulis --</option>
                        <option value="new">+ Tambah Penulis Baru</option>
                    </select>
                </div>
                
                <div id="swal_new_author_fields" class="d-none">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Penulis Baru <span class="text-danger">*</span></label>
                        <input type="text" id="swal_new_author_name" class="form-control" 
                               placeholder="Prof. Dr. Ahmad Dahlan">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Role Penulis <span class="text-danger">*</span></label>
                    <select id="swal_author_role" class="form-select">
                        <option value="penulis_utama">Penulis Utama</option>
                        <option value="co_author">Co-Author</option>
                        <option value="editor">Editor</option>
                        <option value="translator">Translator</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Biografi (Opsional)</label>
                    <textarea id="swal_author_bio" class="form-control" rows="3" maxlength="200"
                              placeholder="Biografi singkat penulis (max 200 karakter)"></textarea>
                    <small class="text-muted" id="swal_bio_counter">0/200</small>
                </div>
            </div>
        `,
        width: 600,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i>Tambah',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
        confirmButtonColor: '#007bff',
        cancelButtonColor: '#6c757d',
        didOpen: () => {
            // Load authors list
            loadAuthorsDropdown();
            
            // Author select change handler
            const authorSelect = document.getElementById('swal_author_select');
            const newFields = document.getElementById('swal_new_author_fields');
            
            authorSelect.addEventListener('change', function() {
                if (this.value === 'new') {
                    newFields.classList.remove('d-none');
                } else {
                    newFields.classList.add('d-none');
                }
            });
            
            // Bio counter
            const bioTextarea = document.getElementById('swal_author_bio');
            const counter = document.getElementById('swal_bio_counter');
            
            bioTextarea.addEventListener('input', function() {
                counter.textContent = `${this.value.length}/200`;
            });
        },
        preConfirm: () => {
            const authorSelect = document.getElementById('swal_author_select').value;
            const newName = document.getElementById('swal_new_author_name').value.trim();
            const role = document.getElementById('swal_author_role').value;
            const bio = document.getElementById('swal_author_bio').value.trim();
            
            if (authorSelect === 'new' && !newName) {
                Swal.showValidationMessage('Nama penulis baru wajib diisi!');
                return false;
            }
            
            if (!authorSelect) {
                Swal.showValidationMessage('Pilih penulis atau tambah baru!');
                return false;
            }
            
            return {
                authorId: authorSelect,
                newName: newName,
                role: role,
                bio: bio
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            addAuthorToList(result.value);
        }
    });
}

/**
 * Load Authors Dropdown from Database
 */
function loadAuthorsDropdown() {
    fetch('../includes/api/get_all_authors.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('swal_author_select');
                
                data.authors.forEach(author => {
                    const option = document.createElement('option');
                    option.value = author.id;
                    option.textContent = author.nama_pengarang;
                    select.insertBefore(option, select.lastElementChild);
                });
            }
        })
        .catch(err => {
            console.error('[LOAD AUTHORS ERROR]', err);
        });
}

/**
 * Add Author to List
 */
function addAuthorToList(data) {
    const container = document.getElementById('new-authors-container');
    newAuthorsCounter++;
    
    const roleLabels = {
        'penulis_utama': 'Penulis Utama',
        'co_author': 'Co-Author',
        'editor': 'Editor',
        'translator': 'Translator'
    };
    
    const roleBadgeClass = data.role === 'penulis_utama' ? 'bg-primary' : 'bg-secondary';
    const authorName = data.authorId === 'new' ? data.newName : 'Loading...';
    
    const itemHtml = `
        <div class="list-group-item mb-2 border rounded shadow-sm" id="new-author-${newAuthorsCounter}">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <h6 class="mb-1 fw-bold" id="author-name-${newAuthorsCounter}">${authorName}</h6>
                    <span class="badge ${roleBadgeClass} mb-2">${roleLabels[data.role]}</span>
                    ${data.bio ? `<p class="mb-0 small text-muted"><i class="fas fa-quote-left me-1"></i>${data.bio}</p>` : ''}
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger ms-2" 
                        onclick="removeNewAuthor(${newAuthorsCounter})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <input type="hidden" name="new_authors[${newAuthorsCounter}][author_id]" value="${data.authorId}">
            <input type="hidden" name="new_authors[${newAuthorsCounter}][new_name]" value="${data.newName}">
            <input type="hidden" name="new_authors[${newAuthorsCounter}][role]" value="${data.role}">
            <input type="hidden" name="new_authors[${newAuthorsCounter}][bio]" value="${data.bio}">
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', itemHtml);
    
    // If existing author, fetch name
    if (data.authorId !== 'new') {
        fetchAuthorName(data.authorId, newAuthorsCounter);
    }
    
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Penulis berhasil ditambahkan',
        timer: 1500,
        showConfirmButton: false
    });
}

/**
 * Fetch Author Name
 */
function fetchAuthorName(authorId, counter) {
    fetch(`../includes/api/get_author_data.php?id=${authorId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const nameElement = document.getElementById(`author-name-${counter}`);
                if (nameElement) {
                    nameElement.textContent = data.author.nama_pengarang;
                }
            }
        })
        .catch(err => {
            console.error('[FETCH AUTHOR NAME ERROR]', err);
        });
}

/**
 * Remove New Author
 */
function removeNewAuthor(counter) {
    Swal.fire({
        title: 'Hapus Penulis?',
        text: 'Penulis ini akan dihapus dari daftar',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            const item = document.getElementById(`new-author-${counter}`);
            if (item) {
                item.remove();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Terhapus!',
                    text: 'Penulis berhasil dihapus',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        }
    });
}

/**
 * Edit Existing Author
 */
function editAuthor(relationId, authorId, authorName, role, bio) {
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    
    Swal.fire({
        title: '<i class="fas fa-edit me-2"></i>Edit Penulis',
        customClass: {
            popup: isDarkMode ? 'swal-dark' : ''
        },
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Penulis</label>
                    <input type="text" class="form-control" value="${authorName}" disabled>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Role Penulis</label>
                    <select id="edit_author_role" class="form-select">
                        <option value="penulis_utama" ${role === 'penulis_utama' ? 'selected' : ''}>Penulis Utama</option>
                        <option value="co_author" ${role === 'co_author' ? 'selected' : ''}>Co-Author</option>
                        <option value="editor" ${role === 'editor' ? 'selected' : ''}>Editor</option>
                        <option value="translator" ${role === 'translator' ? 'selected' : ''}>Translator</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Biografi</label>
                    <textarea id="edit_author_bio" class="form-control" rows="3" maxlength="200">${bio || ''}</textarea>
                    <small class="text-muted" id="edit_bio_counter">${(bio || '').length}/200</small>
                </div>
            </div>
        `,
        width: 600,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-2"></i>Simpan',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        didOpen: () => {
            const bioTextarea = document.getElementById('edit_author_bio');
            const counter = document.getElementById('edit_bio_counter');
            
            bioTextarea.addEventListener('input', function() {
                counter.textContent = `${this.value.length}/200`;
            });
        },
        preConfirm: () => {
            return {
                role: document.getElementById('edit_author_role').value,
                bio: document.getElementById('edit_author_bio').value.trim()
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateAuthorInList(relationId, authorId, result.value.role, result.value.bio);
        }
    });
}

/**
 * Update Author in List
 */
function updateAuthorInList(relationId, authorId, newRole, newBio) {
    const item = document.getElementById(`author-item-${relationId}`);
    if (!item) return;
    
    // Update hidden inputs
    const inputs = item.querySelectorAll('input[type="hidden"]');
    inputs.forEach(input => {
        if (input.name.includes('[peran]')) {
            input.value = newRole;
        }
        if (input.name.includes('[biografi]')) {
            input.value = newBio;
        }
    });
    
    // Update visual display
    const roleLabels = {
        'penulis_utama': 'Penulis Utama',
        'co_author': 'Co-Author',
        'editor': 'Editor',
        'translator': 'Translator'
    };
    
    const badge = item.querySelector('.badge');
    if (badge) {
        badge.textContent = roleLabels[newRole];
        badge.className = `badge ${newRole === 'penulis_utama' ? 'bg-primary' : 'bg-secondary'} mb-2`;
    }
    
    // Update bio display
    const bioP = item.querySelector('p.small');
    if (newBio) {
        if (bioP) {
            bioP.innerHTML = `<i class="fas fa-quote-left me-1"></i>${newBio.substring(0, 100)}${newBio.length > 100 ? '...' : ''}`;
        } else {
            const bioHtml = `<p class="mb-0 small text-muted"><i class="fas fa-quote-left me-1"></i>${newBio.substring(0, 100)}${newBio.length > 100 ? '...' : ''}</p>`;
            item.querySelector('.flex-grow-1').insertAdjacentHTML('beforeend', bioHtml);
        }
    } else if (bioP) {
        bioP.remove();
    }
    
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Data penulis berhasil diupdate',
        timer: 1500,
        showConfirmButton: false
    });
}

/**
 * Remove Existing Author
 */
function removeAuthor(relationId) {
    Swal.fire({
        title: 'Hapus Penulis?',
        text: 'Penulis ini akan dihapus dari buku',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            const item = document.getElementById(`author-item-${relationId}`);
            if (item) {
                // Add to deleted list
                deletedAuthorIds.push(relationId);
                
                // Update hidden input
                updateDeletedAuthorsInput();
                
                // Remove from DOM
                item.style.transition = 'opacity 0.3s';
                item.style.opacity = '0';
                
                setTimeout(() => {
                    item.remove();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus!',
                        text: 'Penulis berhasil dihapus',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }, 300);
            }
        }
    });
}

/**
 * Update Deleted Authors Input
 */
function updateDeletedAuthorsInput() {
    const form = document.getElementById('formEditInventory');
    let hiddenInput = document.getElementById('deleted_authors');
    
    if (!hiddenInput && form) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'deleted_authors';
        hiddenInput.name = 'deleted_authors';
        form.appendChild(hiddenInput);
    }
    
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(deletedAuthorIds);
    }
}

// ========================================
// PUBLISHER SECTION HANDLERS (ENHANCED)
// ========================================

function handlePublisherChange() {
    const publisherSelect = document.getElementById('publisher_select');
    const newNameField = document.getElementById('new_publisher_name_field');
    const actionInput = document.getElementById('publisher_action');
    
    if (!publisherSelect) return;
    
    const selectedValue = publisherSelect.value;
    
    if (selectedValue === 'new') {
        // Show new publisher name field
        newNameField.classList.remove('d-none');
        actionInput.value = 'new';
        
        // Clear other fields for new entry
        document.getElementById('publisher_alamat').value = '';
        document.getElementById('publisher_telepon').value = '';
        document.getElementById('publisher_email').value = '';
        
    } else if (selectedValue && selectedValue !== '') {
        // Hide new publisher name field
        newNameField.classList.add('d-none');
        actionInput.value = 'existing';
        
        // Fetch and populate publisher data
        fetchPublisherData(selectedValue);
        
    } else {
        // No selection
        newNameField.classList.add('d-none');
        actionInput.value = '';
    }
}

function fetchPublisherData(publisherId) {
    fetch(`../includes/api/get_publisher_data.php?id=${publisherId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                currentPublisherData = data.publisher;
                
                // Populate fields (always visible)
                const alamatInput = document.getElementById('publisher_alamat');
                const teleponInput = document.getElementById('publisher_telepon');
                const emailInput = document.getElementById('publisher_email');
                
                if (alamatInput) alamatInput.value = data.publisher.alamat || '';
                if (teleponInput) teleponInput.value = data.publisher.no_telepon || '';
                if (emailInput) emailInput.value = data.publisher.email || '';
                
                console.log('[PUBLISHER] Data loaded:', data.publisher);
            }
        })
        .catch(err => {
            console.error('[PUBLISHER FETCH ERROR]', err);
        });
}

// ========================================
// DELETE EKSEMPLAR FUNCTIONS
// ========================================

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
            if (!deletedEksemplarIds.includes(eksId)) {
                deletedEksemplarIds.push(eksId);
            }
            
            updateDeletedInput();
            
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
                
                if (!deletedEksemplarIds.includes(eksId)) {
                    deletedEksemplarIds.push(eksId);
                }
                
                const row = document.getElementById(`row-eks-${eksId}`);
                if (row) row.remove();
            });
            
            updateDeletedInput();
            updateEksemplarCount();
            
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

function updateDeletedInput() {
    const form = document.getElementById('formEditInventory');
    let hiddenInput = document.getElementById('deleted_eksemplar');
    
    if (!hiddenInput && form) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'deleted_eksemplar';
        hiddenInput.name = 'delete_eksemplar';
        form.appendChild(hiddenInput);
    }
    
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(deletedEksemplarIds);
    }
}

function updateEksemplarCount() {
    const tbody = document.getElementById('eksemplar-container');
    if (tbody) {
        const visibleRows = tbody.querySelectorAll('tr:not(.d-none)').length;
        console.log('[COUNT] Visible eksemplar:', visibleRows);
    }
}

// ========================================
// ADD NEW EKSEMPLAR FUNCTIONS
// ========================================

// ========================================
// REPLACE THESE FUNCTIONS IN edit_inventory.js
// ========================================

/**
 * FIXED: Open Modal untuk Bulk Add dengan scanning otomatis
 */
function openAddEksemplarModal(reset = true) {
    const bookId = document.querySelector('input[name="book_id"]').value;
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    
    if (reset) scannedNewUIDs = [];
    
    Swal.fire({
        title: '<i class="fas fa-plus-circle me-2"></i>Tambah Eksemplar Baru',
        customClass: {
            popup: isDarkMode ? 'swal-dark' : ''
        },
        html: `
            <div class="text-start">
                <div class="alert alert-info border-0 mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Panduan:</strong> Sistem akan otomatis scan sesuai jumlah yang diinput. Jika UID tidak cukup, akan ambil yang tersedia saja.
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Jumlah Unit Baru <span class="text-danger">*</span></label>
                    <input type="number" id="swal_jumlah_unit" class="form-control" value="1" min="1" max="50">
                    <small class="text-muted">Maksimal 50 unit per sekali tambah</small>
                </div>
                
                <div class="text-center mb-3">
                    <button type="button" class="btn btn-primary btn-lg px-4" onclick="startBulkScanRFID()">
                        <i class="fas fa-qrcode me-2"></i>MULAI SCAN RFID
                    </button>
                </div>
                
                <div id="scan_results_container" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto; min-height: 100px;">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-barcode fa-3x opacity-50 mb-2"></i>
                        <p class="mb-0">Belum ada scan</p>
                    </div>
                </div>
                
                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <span class="badge bg-info" id="scan_count_badge">0 UID Terscan</span>
                    <button type="button" class="btn btn-sm btn-warning" onclick="clearAllScans()">
                        <i class="fas fa-redo me-1"></i>Reset Scan
                    </button>
                </div>
            </div>
        `,
        width: 700,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-2"></i>Simpan Unit Baru',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        showLoaderOnConfirm: true,
        didOpen: () => {
            updateScanResultsUI();
        },
        preConfirm: () => {
            return submitNewEksemplar(bookId);
        },
        allowOutsideClick: () => !Swal.isLoading()
    });
}

/**
 * FIXED: Bulk scan RFID dengan retry logic dan handling partial success
 */
function startBulkScanRFID() {
    const jumlahUnit = parseInt(document.getElementById('swal_jumlah_unit').value) || 1;
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    
    if (jumlahUnit < 1 || jumlahUnit > 50) {
        Swal.fire({
            icon: 'warning',
            title: 'Input Tidak Valid',
            text: 'Jumlah unit harus antara 1-50',
            confirmButtonColor: '#ffc107'
        }).then(() => openAddEksemplarModal(false));
        return;
    }
    
    // Show scanning progress
    Swal.fire({
        title: 'Memulai Scan...',
        html: `
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x mb-3 text-primary"></i>
                <p class="mb-2">Mencari UID yang tersedia...</p>
                <div class="progress mt-3" style="height: 25px;">
                    <div id="scan_progress" class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%">0 / ${jumlahUnit}</div>
                </div>
                <small class="text-muted mt-2 d-block">Ini mungkin memakan waktu beberapa detik...</small>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false,
        customClass: {
            popup: isDarkMode ? 'swal-dark' : ''
        }
    });
    
    // Fetch UIDs dari server
    fetch(`../includes/api/check_latest_uid.php?limit=${jumlahUnit}`)
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.text(); // Get as text first to debug
        })
        .then(text => {
            console.log('[BULK SCAN] Raw response:', text);
            
            // Try parse JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('[BULK SCAN] JSON parse error:', e);
                console.error('[BULK SCAN] Response text:', text);
                throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
            }
            
            return data;
        })
        .then(data => {
            console.log('[BULK SCAN] Parsed data:', data);
            
            Swal.close();
            
            if (!data.success) {
                throw new Error(data.message || 'Gagal mengambil UID dari server');
            }
            
            if (!data.uids || data.uids.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tidak Ada UID Tersedia',
                    html: `
                        <p>Tidak ada UID yang terdeteksi dalam 5 menit terakhir.</p>
                        <hr>
                        <small class="text-muted">
                            <strong>Troubleshooting:</strong><br>
                            • Pastikan RFID reader sudah aktif<br>
                            • Scan beberapa tag RFID terlebih dahulu<br>
                            • Tunggu beberapa detik dan coba lagi
                        </small>
                    `,
                    confirmButtonColor: '#ffc107'
                }).then(() => openAddEksemplarModal(false));
                return;
            }
            
            // Process found UIDs
            const foundCount = data.uids.length;
            const requestedCount = jumlahUnit;
            
            // Check for duplicates before adding
            const validUIDs = [];
            const duplicateUIDs = [];
            
            data.uids.forEach(uid => {
                const isDuplicate = scannedNewUIDs.some(u => u.id === uid.id);
                if (!isDuplicate) {
                    validUIDs.push(uid);
                } else {
                    duplicateUIDs.push(uid);
                }
            });
            
            // Add valid UIDs to scanned list
            scannedNewUIDs.push(...validUIDs);
            
            // Update UI
            updateScanResultsUI();
            
            // Show result
            let resultIcon = 'success';
            let resultTitle = 'Scan Berhasil!';
            let resultText = `${validUIDs.length} UID berhasil ditambahkan`;
            
            if (validUIDs.length < requestedCount) {
                resultIcon = 'warning';
                resultTitle = 'Scan Partial';
                resultText = `Hanya ${validUIDs.length} dari ${requestedCount} UID yang tersedia`;
            }
            
            if (duplicateUIDs.length > 0) {
                resultText += `\n${duplicateUIDs.length} UID duplicate diabaikan`;
            }
            
            Swal.fire({
                icon: resultIcon,
                title: resultTitle,
                text: resultText,
                confirmButtonColor: resultIcon === 'success' ? '#28a745' : '#ffc107',
                timer: 2000
            }).then(() => {
                openAddEksemplarModal(false);
            });
            
        })
        .catch(err => {
            Swal.close();
            console.error('[BULK SCAN ERROR]', err);
            
            Swal.fire({
                icon: 'error',
                title: 'Error Scan',
                html: `
                    <p>${err.message}</p>
                    <hr>
                    <small class="text-muted">
                        <strong>Detail Error:</strong><br>
                        ${err.stack ? err.stack.split('\n')[0] : 'Unknown error'}
                    </small>
                `,
                confirmButtonColor: '#dc3545'
            }).then(() => {
                openAddEksemplarModal(false);
            });
        });
}

/**
 * NEW: Clear all scanned UIDs
 */
function clearAllScans() {
    if (scannedNewUIDs.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Tidak Ada Scan',
            text: 'Belum ada UID yang di-scan',
            timer: 1500,
            showConfirmButton: false
        });
        return;
    }
    
    Swal.fire({
        title: 'Reset Semua Scan?',
        text: `${scannedNewUIDs.length} UID akan dihapus`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Reset',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ffc107'
    }).then(result => {
        if (result.isConfirmed) {
            scannedNewUIDs = [];
            updateScanResultsUI();
            
            Swal.fire({
                icon: 'success',
                title: 'Direset!',
                text: 'Semua scan telah dihapus',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                openAddEksemplarModal(false);
            });
        } else {
            openAddEksemplarModal(false);
        }
    });
}

/**
 * FIXED: Update scan results UI
 */
function updateScanResultsUI() {
    const container = document.getElementById('scan_results_container');
    const badge = document.getElementById('scan_count_badge');
    
    if (!container) return;
    
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
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <strong>#${index + 1}</strong>
                        <code class="ms-2 text-primary">${uid.uid}</code>
                        <small class="text-muted d-block">ID: ${uid.id} • ${uid.timestamp}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeScannedUID(${index})">
                        <i class="fas fa-times"></i>
                    </button>
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
 * FIXED: Remove scanned UID
 */
function removeScannedUID(index) {
    const uid = scannedNewUIDs[index];
    
    Swal.fire({
        title: 'Hapus UID?',
        text: `UID: ${uid.uid}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc3545'
    }).then(result => {
        if (result.isConfirmed) {
            scannedNewUIDs.splice(index, 1);
            updateScanResultsUI();
            
            Swal.fire({
                icon: 'success',
                title: 'Terhapus!',
                timer: 1000,
                showConfirmButton: false
            }).then(() => {
                openAddEksemplarModal(false);
            });
        } else {
            openAddEksemplarModal(false);
        }
    });
}

/**
 * FIXED: Submit new eksemplar dengan error handling lebih baik
 */
function submitNewEksemplar(bookId) {
    if (scannedNewUIDs.length === 0) {
        Swal.showValidationMessage('Belum ada UID yang di-scan!');
        return false;
    }
    
    console.log('[SUBMIT] Starting submission with', scannedNewUIDs.length, 'UIDs');
    
    // Get existing max number
    const existingRows = document.querySelectorAll('#eksemplar-container tr[id^="row-eks-"]');
    let maxNumber = 0;
    
    existingRows.forEach(row => {
        const kodeCell = row.querySelector('td:nth-child(2) strong');
        if (kodeCell) {
            const match = kodeCell.textContent.match(/(\d+)/);
            if (match) {
                maxNumber = Math.max(maxNumber, parseInt(match[1]));
            }
        }
    });
    
    console.log('[SUBMIT] Max existing number:', maxNumber);
    
    // Prepare new eksemplar data
    const newEksemplar = scannedNewUIDs.map((uid, idx) => {
        return {
            uid_buffer_id: uid.id,
            kondisi: 'baik'
        };
    });
    
    const payload = {
        book_id: parseInt(bookId),
        new_eksemplar: newEksemplar
    };
    
    console.log('[SUBMIT] Payload:', JSON.stringify(payload, null, 2));
    
    // Submit to controller
    return fetch('../controllers/AddEksemplarController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json; charset=utf-8'
        },
        body: JSON.stringify(payload)
    })
    .then(res => {
        console.log('[SUBMIT] Response status:', res.status);
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        return res.text(); // Get as text first for debugging
    })
    .then(text => {
        console.log('[SUBMIT] Raw response:', text);
        
        // Try parse JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('[SUBMIT] JSON parse error:', e);
            console.error('[SUBMIT] Response text:', text);
            throw new Error('Invalid JSON response: ' + text.substring(0, 200));
        }
        
        console.log('[SUBMIT] Parsed data:', data);
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                html: `
                    <p>${data.message}</p>
                    ${data.warnings ? `<hr><small class="text-warning">${data.warnings.length} UID gagal diproses</small>` : ''}
                `,
                confirmButtonColor: '#28a745',
                timer: 3000
            }).then(() => {
                location.reload();
            });
        } else {
            throw new Error(data.message || 'Unknown error');
        }
    })
    .catch(err => {
        console.error('[SUBMIT ERROR]', err);
        
        Swal.showValidationMessage(`
            <div class="text-start">
                <strong>Gagal menambahkan eksemplar:</strong><br>
                ${err.message}<br>
                <hr>
                <small class="text-muted">
                    Periksa console browser (F12) untuk detail lengkap
                </small>
            </div>
        `);
        
        return false;
    });
}

// ========================================
// REPLACE THESE FUNCTIONS ABOVE IN YOUR edit_inventory.js
// Keep all other functions unchanged
// ========================================

function startScanNewEksemplar() {
    const jumlahUnit = parseInt(document.getElementById('swal_jumlah_unit').value) || 1;
    const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
    
    if (scannedNewUIDs.length >= jumlahUnit) {
        Swal.fire({
            icon: 'warning',
            title: 'Scan Sudah Cukup',
            text: `Anda sudah scan ${scannedNewUIDs.length} UID. Klik Simpan untuk melanjutkan.`,
            confirmButtonColor: '#ffc107',
            customClass: {
                popup: isDarkMode ? 'swal-dark' : ''
            }
        }).then(() => {
            openAddEksemplarModal(false);
        });
        return;
    }
    
    Swal.fire({
        title: 'Scanning RFID...',
        html: '<i class="fas fa-spinner fa-spin fa-3x mb-3"></i><br>Dekatkan RFID tag ke reader',
        allowOutsideClick: false,
        showConfirmButton: false,
        customClass: {
            popup: isDarkMode ? 'swal-dark' : ''
        }
    });
    
    fetch('../includes/api/check_latest_uid.php?limit=1')
        .then(res => res.json())
        .then(data => {
            Swal.close();
            
            if (data.success && data.uids && data.uids.length > 0) {
                const uid = data.uids[0];
                
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
                
                scannedNewUIDs.push(uid);
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

function updateScanResultsUI() {
    const container = document.getElementById('scan_results_container');
    const badge = document.getElementById('scan_count_badge');
    
    if (!container) return;
    
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
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>#${index + 1}</strong>
                        <code class="ms-2">${uid.uid}</code>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeScannedUID(${index})">
                        <i class="fas fa-times"></i>
                    </button>
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

function removeScannedUID(index) {
    scannedNewUIDs.splice(index, 1);
    updateScanResultsUI();
}

function submitNewEksemplar(bookId) {
    if (scannedNewUIDs.length === 0) {
        Swal.showValidationMessage('Belum ada UID yang di-scan!');
        return false;
    }
    
    const existingRows = document.querySelectorAll('#eksemplar-container tr[id^="row-eks-"]');
    let maxNumber = 0;
    
    existingRows.forEach(row => {
        const kodeCell = row.querySelector('td:nth-child(2) strong');
        if (kodeCell) {
            const match = kodeCell.textContent.match(/EKS-(\d+)/);
            if (match) {
                maxNumber = Math.max(maxNumber, parseInt(match[1]));
            }
        }
    });
    
    const newEksemplar = scannedNewUIDs.map((uid, idx) => {
        const newNumber = maxNumber + idx + 1;
        const kodeEksemplar = `EKS-${String(newNumber).padStart(4, '0')}`;
        
        return {
            uid_buffer_id: uid.id,
            kode_eksemplar: kodeEksemplar,
            kondisi: 'baik'
        };
    });
    
    return fetch('../controllers/AddEksemplarController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            book_id: parseInt(bookId),
            new_eksemplar: newEksemplar
        })
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
                location.reload();
            });
        } else {
            Swal.showValidationMessage(data.message);
        }
    })
    .catch(err => {
        console.error('[SUBMIT ERROR]', err);
        Swal.showValidationMessage('Gagal menambahkan eksemplar');
    });
}

// ========================================
// FORM SUBMIT HANDLER (ENHANCED)
// ========================================

function initFormSubmit() {
    const form = document.getElementById('formEditInventory');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate authors
            const existingAuthors = document.querySelectorAll('[id^="author-item-"]').length;
            const newAuthors = document.querySelectorAll('[id^="new-author-"]').length;
            
            if (existingAuthors + newAuthors === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Minimal harus ada 1 penulis!',
                    confirmButtonColor: '#ffc107'
                });
                return;
            }
            
            const btnSubmit = document.getElementById('btnSimpanEdit');
            const originalText = btnSubmit.innerHTML;
            
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
            
            const formData = new FormData(this);
            
            // Ensure deleted arrays are set
            formData.set('delete_eksemplar', JSON.stringify(deletedEksemplarIds));
            formData.set('deleted_authors', JSON.stringify(deletedAuthorIds));
            
            console.log('[FORM SUBMIT] Deleted Eksemplar:', deletedEksemplarIds);
            console.log('[FORM SUBMIT] Deleted Authors:', deletedAuthorIds);
            
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
    console.log('[EDIT INVENTORY JS] ENHANCED VERSION with Multiple Authors Loaded');
    
    const publisherSelect = document.getElementById('publisher_select');
    if (publisherSelect) {
        publisherSelect.addEventListener('change', handlePublisherChange);
    }
    
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.check-item');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }
    
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

console.log('[EDIT INVENTORY JS] Version 4.0 - ENHANCED with Multiple Authors & Flexible Publisher');