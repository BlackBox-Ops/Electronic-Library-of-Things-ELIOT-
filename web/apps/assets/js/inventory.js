/**
 * web/apps/assets/js/inventory.js
 * Integrasi Scanner RFID dengan SweetAlert2 & UI Adaptive
 */

/**
 * Fungsi untuk memicu pemindaian UID dari hardware (ESP32)
 */
function triggerScan() {
    const statusLabel = document.getElementById('uid-status');
    const displayArea = document.getElementById('uid-display');
    const inputUid = document.getElementById('input_uid_id');
    const textUid = document.getElementById('detected-uid');
    const btnSimpan = document.getElementById('btnSimpan');
    const btnScan = document.getElementById('btnScanTrigger');

    // Mencegah error jika elemen tidak ditemukan di DOM
    if (!btnScan) return;

    // Path menuju API pengecekan UID terbaru
    const apiPath = '../includes/api/check_latest_uid.php';

    // UI Feedback: Mengubah tombol menjadi state loading
    btnScan.disabled = true;
    btnScan.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mencari...';

    fetch(apiPath)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Sembunyikan instruksi, tampilkan hasil scan
                if (statusLabel) statusLabel.classList.add('d-none');
                if (displayArea) displayArea.classList.remove('d-none');
                
                textUid.innerText = data.uid;
                inputUid.value = data.id; // Menyimpan ID buffer untuk diproses di controller
                
                // AKTIFKAN TOMBOL SIMPAN: Sekarang data sudah lengkap
                if (btnSimpan) btnSimpan.disabled = false;
                
                // Notifikasi sukses yang otomatis hilang
                Swal.fire({
                    icon: 'success',
                    title: 'Terdeteksi!',
                    text: 'UID ' + data.uid + ' berhasil terbaca.',
                    timer: 1500,
                    showConfirmButton: false
                });
                
                // Update tampilan tombol scan menjadi state berhasil
                btnScan.innerHTML = '<i class="fas fa-check"></i> Berhasil';
                btnScan.classList.replace('btn-primary', 'btn-outline-success');
            } else {
                // ALERT DANGER: Sesuai permintaan untuk pesan kesalahan hardware/registrasi
                Swal.fire({
                    icon: 'error',
                    title: 'Data Tidak Ditemukan',
                    text: 'Maaf, alamat UID belum ada. Silakan registrasi UID atau cek perangkat keras!',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Coba Lagi'
                });
                resetScanUI();
            }
        })
        .catch(err => {
            console.error("Polling error: ", err);
            Swal.fire({
                icon: 'warning',
                title: 'Error Sistem',
                text: 'Gagal terhubung ke API scanner. Periksa koneksi jaringan Anda.',
                confirmButtonColor: '#f8bb86'
            });
            resetScanUI();
        });
}

/**
 * Mengembalikan tampilan tombol scan ke kondisi awal
 */
function resetScanUI() {
    const btnScan = document.getElementById('btnScanTrigger');
    if (btnScan) {
        btnScan.disabled = false;
        btnScan.innerHTML = '<i class="fas fa-qrcode me-2"></i>AMBIL DATA SCAN TERBARU';
        btnScan.classList.remove('btn-outline-success');
        btnScan.classList.add('btn-primary');
    }
}

/**
 * Handler pengiriman form melalui AJAX
 */
const formRegistrasi = document.getElementById('formRegistrasiAset');
if (formRegistrasi) {
    formRegistrasi.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('btnSimpan');
        const originalContent = btn.innerHTML;

        // Visual loading pada tombol simpan
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';

        fetch('../controllers/InventoryController.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Notifikasi sukses dan refresh halaman untuk memperbarui tabel
                Swal.fire('Berhasil!', data.message, 'success')
                    .then(() => location.reload());
            } else {
                // Tampilkan pesan error dari controller
                Swal.fire('Gagal!', data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        })
        .catch(err => {
            console.error("Submit error:", err);
            Swal.fire('Error', 'Terjadi kesalahan saat menyimpan data.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalContent;
        });
    });
}

/**
 * Reset modal saat ditutup agar bersih saat dibuka kembali
 */
const modalTambah = document.getElementById('modalTambahAset');
if (modalTambah) {
    modalTambah.addEventListener('hidden.bs.modal', function() {
        // Reset form input
        const form = this.querySelector('form');
        if (form) form.reset();
        
        // Kembalikan UI scanner ke instruksi awal
        const statusLabel = document.getElementById('uid-status');
        const displayArea = document.getElementById('uid-display');
        const btnSimpan = document.getElementById('btnSimpan');
        
        if (statusLabel) statusLabel.classList.remove('d-none');
        if (displayArea) displayArea.classList.add('d-none');
        if (btnSimpan) btnSimpan.disabled = true; // Kunci kembali tombol simpan
        
        resetScanUI();
    });
}

/**
 * Fungsi untuk menampilkan/menyembunyikan detail unit di tabel utama
 */
function toggleRow(rowId) {
    const row = document.getElementById(rowId);
    const icon = document.getElementById('icon-' + rowId);
    
    if (row) {
        row.classList.toggle('d-none'); // Bootstrap class untuk hide/show
        if (icon) {
            icon.classList.toggle('active'); // Memutar icon chevron melalui CSS
        }
    }
}