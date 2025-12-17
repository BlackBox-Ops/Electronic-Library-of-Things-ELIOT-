function viewDetail(id) {
    // Logika menampilkan modal detail atau redirect
    window.location.href = 'user_detail.php?id=' + id;
}

function changeStatus(id, currentStatus) {
    // Logika ganti status (bisa menggunakan SweetAlert2 untuk UX yang lebih baik)
    const newStatus = prompt("Masukkan status baru (aktif/nonaktif/suspended):", currentStatus);
    if (newStatus && newStatus !== currentStatus) {
        // Kirim data ke backend (misal: update_status.php) via Fetch API atau form
        console.log(`Mengubah user ${id} menjadi ${newStatus}`);
    }
}

function deleteUser(id) {
    if (confirm("Apakah Anda yakin ingin menghapus user ini? (Data akan dipindahkan ke tempat sampah)")) {
        // Kirim request soft-delete ke backend
        console.log(`Menghapus user ${id}`);
        // window.location.href = 'process/delete_user.php?id=' + id;
    }
}