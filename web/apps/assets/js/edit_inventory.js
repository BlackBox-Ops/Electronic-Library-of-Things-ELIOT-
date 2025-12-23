/**
 * edit_inventory.js
 * Path: web/apps/admin/assets/js/edit_inventory.js
 * 
 * Features:
 * - Tab switching dengan smooth transition
 * - Toggle Publisher & Author baru
 * - Switch Keterangan field
 * - Delete eksemplar dengan soft delete
 * - Scan eksemplar baru
 * - Form submit dengan AJAX
 */

// ========================================
// 1. TAB SWITCHING FUNCTION
// ========================================
function switchToTab(tabId) {
    const tab = document.getElementById(tabId);
    if (tab) {
        const bootstrapTab = new bootstrap.Tab(tab);
        bootstrapTab.show();
        
        // Smooth scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// ========================================
// 2. SWITCH KETERANGAN HANDLER
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    const switchKeterangan = document.getElementById('switchKeterangan');
    const inputKeterangan = document.getElementById('input_keterangan');
    const wrapperField = document.getElementById('keterangan_field_wrapper');
    
    if (switchKeterangan && inputKeterangan) {
        // Set initial state based on checked
        if (!switchKeterangan.checked) {
            inputKeterangan.disabled = true;
        }
        
        // Toggle handler
        switchKeterangan.addEventListener('change', function() {
            if (this.checked) {
                inputKeterangan.disabled = false;
                inputKeterangan.focus();
                wrapperField.classList.add('field-enabled');
                inputKeterangan.style.opacity = '1';
                console.log('[KETERANGAN] Field enabled');
            } else {
                inputKeterangan.disabled = true;
                inputKeterangan.value = '';
                wrapperField.classList.remove('field-enabled');
                inputKeterangan.style.opacity = '0.6';
                console.log('[KETERANGAN] Field disabled & cleared');
            }
        });
    }

    // ========================================
    // MULTI-AUTHOR SWITCH HANDLER
    // ========================================
    const switchMultiAuthor = document.getElementById('switchMultiAuthor');
    const peranField = document.getElementById('peran_field');
    
    if (switchMultiAuthor && peranField) {
        // Initial state: hidden
        peranField.classList.add('d-none');
        
        // Toggle handler
        switchMultiAuthor.addEventListener('change', function() {
            if (this.checked) {
                // Show peran field with animation
                peranField.classList.remove('d-none');
                peranField.style.animation = 'fadeIn 0.3s ease-in-out';
                console.log('[MULTI-AUTHOR] Mode enabled - Peran field visible');
                
                // Show info toast
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: 'Mode Multi-Penulis Aktif',
                    text: 'Field peran penulis ditampilkan',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                // Hide peran field
                peranField.classList.add('d-none');
                // Reset to default value
                document.getElementById('peran_select').value = 'penulis_utama';
                console.log('[MULTI-AUTHOR] Mode disabled - Peran field hidden');
            }
        });
    }
});

// ========================================
// 3. TOGGLE PUBLISHER & AUTHOR FIELDS
// ========================================
document.getElementById('publisher_select').addEventListener('change', function() {
    const newFields = document.getElementById('new_publisher_fields');
    const detailFields = document.getElementById('publisher_detail');
    
    if (this.value === 'new') {
        newFields.classList.remove('d-none');
        detailFields.classList.add('d-none');
    } else if (this.value === '') {
        newFields.classList.add('d-none');
        detailFields.classList.add('d-none');
    } else {
        newFields.classList.add('d-none');
        detailFields.classList.remove('d-none');
    }
});

document.getElementById('author_select').addEventListener('change', function() {
    const newFields = document.getElementById('new_author_fields');
    const detailFields = document.getElementById('author_detail');
    
    if (this.value === 'new') {
        newFields.classList.remove('d-none');
        detailFields.classList.add('d-none');
    } else if (this.value === '') {
        newFields.classList.add('d-none');
        detailFields.classList.add('d-none');
    } else {
        newFields.classList.add('d-none');
        detailFields.classList.remove('d-none');
    }
});

// Character counter untuk biografi author baru
const bioTextarea = document.getElementById('author_biografi_new');
const charCount = document.getElementById('char-count-new');
if (bioTextarea && charCount) {
    bioTextarea.addEventListener('input', function() {
        charCount.textContent = this.value.length + '/200';
    });
}

// ========================================
// 4. DELETE EKSEMPLAR (SOFT DELETE) + DYNAMIC BUTTON
// ========================================
let initialEksemplarCount = document.querySelectorAll('#eksemplarTableBody tr').length;

document.querySelectorAll('.btn-delete-eksemplar').forEach(button => {
    button.addEventListener('click', function() {
        const eksemplarId = this.getAttribute('data-id');
        const row = this.closest('tr');
        const remainingRows = document.querySelectorAll('#eksemplarTableBody tr').length;
        
        // Prevent deleting last eksemplar
        if (remainingRows <= 1) {
            Swal.fire({
                icon: 'warning',
                title: 'Tidak Bisa Hapus',
                html: `
                    <p>Ini adalah eksemplar terakhir!</p>
                    <p class="text-muted small">Jika ingin menghapus buku ini sekaligus, hapus eksemplar terakhir ini dan sistem akan otomatis menampilkan tombol <strong>"Hapus Buku"</strong>.</p>
                `,
                confirmButtonColor: '#6c757d'
            });
            return;
        }
        
        Swal.fire({
            title: 'Hapus Eksemplar?',
            text: 'Eksemplar ini akan dihapus dari database (soft delete)',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash me-2"></i>Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Remove row from DOM
                row.remove();
                
                // Add hidden input untuk delete
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'delete_eksemplar[]';
                hiddenInput.value = eksemplarId;
                document.getElementById('formEditBuku').appendChild(hiddenInput);
                
                // Update count & check for dynamic button
                updateEksemplarCount();
                checkDynamicButton();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Dihapus!',
                    text: 'Eksemplar akan dihapus saat Anda klik Update/Hapus Buku',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                console.log('[DELETE EKSEMPLAR] ID:', eksemplarId);
            }
        });
    });
});

function updateEksemplarCount() {
    const rows = document.querySelectorAll('#eksemplarTableBody tr').length;
    const header = document.querySelector('.card-body h5');
    const badge = document.getElementById('remaining-count');
    const statusBadge = document.getElementById('badge-eksemplar-status');
    
    if (header) {
        header.innerHTML = `<i class="fas fa-tags me-2 text-warning"></i>Eksemplar RFID (${rows} unit)`;
    }
    
    if (badge) {
        badge.textContent = rows;
    }
    
    // Change badge color based on count
    if (statusBadge) {
        if (rows === 0) {
            statusBadge.className = 'badge bg-danger';
            statusBadge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>0 eksemplar - Buku akan dihapus';
        } else if (rows === 1) {
            statusBadge.className = 'badge bg-warning text-dark';
            statusBadge.innerHTML = '<i class="fas fa-info-circle me-1"></i>1 eksemplar tersisa (minimum)';
        } else {
            statusBadge.className = 'badge bg-info text-dark';
            statusBadge.innerHTML = `<i class="fas fa-info-circle me-1"></i>${rows} eksemplar tersisa`;
        }
    }
    
    return rows;
}

function checkDynamicButton() {
    const remaining = document.querySelectorAll('#eksemplarTableBody tr').length;
    const btnUpdate = document.getElementById('btnUpdate');
    const btnDelete = document.getElementById('btnDeleteBook');
    
    if (remaining === 0) {
        // Switch to Delete Book mode
        btnUpdate.classList.add('d-none');
        btnDelete.classList.remove('d-none');
        
        // Show warning alert
        Swal.fire({
            icon: 'warning',
            title: 'Mode Hapus Buku',
            html: `
                <p>Semua eksemplar telah dihapus!</p>
                <p class="fw-bold text-danger">Tombol berubah menjadi "Hapus Buku Ini"</p>
                <p class="text-muted small">Klik tombol tersebut untuk menghapus buku ini dari database (soft delete).</p>
            `,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Mengerti'
        });
        
        console.log('[DYNAMIC BUTTON] Switched to DELETE mode');
    } else {
        // Normal Update mode
        btnUpdate.classList.remove('d-none');
        btnDelete.classList.add('d-none');
        
        console.log('[DYNAMIC BUTTON] UPDATE mode - Remaining:', remaining);
    }
}

// Call on page load to set initial state
document.addEventListener('DOMContentLoaded', function() {
    checkDynamicButton();
});

// ========================================
// 5. SCAN EKSEMPLAR BARU
// ========================================
let newEksemplarList = [];
let isScanningNew = false;

function triggerScanEksemplarBaru() {
    if (isScanningNew) return;

    isScanningNew = true;
    const btn = document.getElementById('btnScanEksemplarBaru');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Scanning...';

    fetch('../includes/api/check_latest_uid.php?limit=10&_=' + Date.now())
        .then(res => res.json())
        .then(data => {
            if (data.success && data.uids.length > 0) {
                const bookId = document.querySelector('input[name="book_id"]').value;
                const prefix = document.querySelector('input[name="judul_buku"]').value.substring(0, 3).toUpperCase();
                
                fetch(`../includes/api/get_next_code.php?prefix=${prefix}&count=${data.uids.length}&book_id=${bookId}&_=` + Date.now())
                    .then(codeRes => codeRes.json())
                    .then(codeData => {
                        if (codeData.success) {
                            data.uids.forEach((uidItem, i) => {
                                newEksemplarList.push({
                                    uid_buffer_id: uidItem.id,
                                    kode_eksemplar: codeData.next_codes[i],
                                    kondisi: 'baik',
                                    uid: uidItem.uid
                                });
                            });

                            renderNewEksemplarList();
                            document.getElementById('inputNewEksemplar').value = JSON.stringify(newEksemplarList);
                            document.getElementById('btnSimpanEksemplarBaru').disabled = false;

                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: `${data.uids.length} RFID baru di-scan`,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', codeData.message, 'error');
                        }
                    });
            } else {
                Swal.fire('Gagal', data.message || 'Tidak ada UID baru ditemukan', 'warning');
            }
        })
        .catch(err => {
            console.error('[SCAN ERROR]', err);
            Swal.fire('Error', 'Gagal menghubungi server scan', 'error');
        })
        .finally(() => {
            isScanningNew = false;
            btn.disabled = false;
            btn.innerHTML = original;
        });
}

function renderNewEksemplarList() {
    const container = document.getElementById('containerScanBaru');
    if (newEksemplarList.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-barcode fa-4x mb-3 opacity-50"></i>
                <p class="fw-bold mb-0">Belum ada RFID di-scan</p>
            </div>`;
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-light"><tr><th>No</th><th>Kode</th><th>UID</th><th>Kondisi</th><th>Aksi</th></tr></thead><tbody>';
    
    newEksemplarList.forEach((item, idx) => {
        html += `<tr>
                    <td>${idx + 1}</td>
                    <td><strong>${item.kode_eksemplar}</strong></td>
                    <td><code>${item.uid}</code></td>
                    <td>
                        <select class="form-select form-select-sm" onchange="newEksemplarList[${idx}].kondisi = this.value">
                            <option value="baik" selected>Baik</option>
                            <option value="rusak_ringan">Rusak Ringan</option>
                            <option value="rusak_berat">Rusak Berat</option>
                            <option value="hilang">Hilang</option>
                        </select>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeNewEksemplar(${idx})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function removeNewEksemplar(index) {
    newEksemplarList.splice(index, 1);
    renderNewEksemplarList();
    document.getElementById('inputNewEksemplar').value = JSON.stringify(newEksemplarList);
    document.getElementById('btnSimpanEksemplarBaru').disabled = newEksemplarList.length === 0;
}

// Simpan eksemplar baru via AJAX
document.getElementById('btnSimpanEksemplarBaru').addEventListener('click', function() {
    if (newEksemplarList.length === 0) return;

    const bookId = document.querySelector('input[name="book_id"]').value;

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    fetch('../controllers/AddEksemplarController.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            book_id: bookId,
            new_eksemplar: newEksemplarList
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: data.message,
                confirmButtonColor: '#41644A'
            }).then(() => {
                location.reload(); // Reload untuk refresh tabel eksemplar
            });
        } else {
            Swal.fire('Gagal', data.message, 'error');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-save me-2"></i>Simpan Eksemplar';
        }
    })
    .catch(err => {
        console.error('[SAVE ERROR]', err);
        Swal.fire('Error', 'Gagal menyimpan eksemplar', 'error');
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-save me-2"></i>Simpan Eksemplar';
    });
});

// ========================================
// 6. FORM SUBMIT DENGAN AJAX
// ========================================
document.getElementById('formEditBuku').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);
    const submitBtn = document.getElementById('btnUpdate');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                html: data.message || 'Data berhasil diupdate!',
                confirmButtonColor: '#41644A'
            }).then(() => {
                window.location.href = data.redirect || 'inventory.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: data.message || 'Terjadi kesalahan',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(err => {
        console.error('[SUBMIT ERROR]', err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal terhubung ke server',
            confirmButtonColor: '#dc3545'
        });
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// ========================================
// 7. CONFIRM DELETE BOOK (When remaining = 0)
// ========================================
function confirmDeleteBook() {
    const bookId = document.querySelector('input[name="book_id"]').value;
    const bookTitle = document.querySelector('input[name="judul_buku"]').value;
    
    Swal.fire({
        title: 'Hapus Buku Ini?',
        html: `
            <div class="text-start">
                <p class="fw-bold text-danger mb-3">PERHATIAN: Tindakan ini akan menghapus buku secara permanen!</p>
                <div class="alert alert-warning">
                    <strong>Buku:</strong> ${bookTitle}<br>
                    <strong>ID:</strong> ${bookId}
                </div>
                <p class="mb-2"><strong>Yang akan terjadi:</strong></p>
                <ul class="text-muted small">
                    <li>Buku di-soft delete (is_deleted = 1)</li>
                    <li>Semua eksemplar RFID di-soft delete</li>
                    <li>Semua UID di uid_buffer → status <code>invalid</code></li>
                    <li>Data masih bisa dipulihkan oleh admin</li>
                </ul>
                <p class="text-danger fw-bold mt-3">Yakin ingin melanjutkan?</p>
            </div>
        `,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Ya, Hapus Buku Ini',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
        customClass: {
            popup: 'swal-wide'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            executeDeleteBook(bookId);
        }
    });
}

function executeDeleteBook(bookId) {
    const btn = document.getElementById('btnDeleteBook');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menghapus...';
    
    fetch('../controllers/DeleteBookController.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            book_id: bookId,
            action: 'soft_delete'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Dihapus!',
                html: `
                    <p>${data.message}</p>
                    <div class="alert alert-info mt-3">
                        <small>
                            <strong>Summary:</strong><br>
                            - Buku: Soft deleted<br>
                            - Eksemplar: ${data.deleted_eksemplar || 0} unit<br>
                            - UID: ${data.invalidated_uid || 0} set ke invalid
                        </small>
                    </div>
                `,
                confirmButtonColor: '#41644A'
            }).then(() => {
                window.location.href = 'inventory.php?deleted=success';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Hapus',
                text: data.message || 'Terjadi kesalahan',
                confirmButtonColor: '#dc3545'
            });
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error('[DELETE BOOK ERROR]', err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal terhubung ke server',
            confirmButtonColor: '#dc3545'
        });
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

console.log('[EDIT INVENTORY] JavaScript loaded successfully');