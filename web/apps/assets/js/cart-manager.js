/**
 * Cart Manager Module
 * Path: web/apps/assets/js/cart-manager.js
 * 
 * Features:
 * - Shopping cart management
 * - Add/remove books
 * - Cart validation
 * - Visual updates
 * 
 * @author ELIOT System
 * @version 1.0.0
 */

const CartManager = (function() {
    'use strict';

    // Private variables
    let cart = [];
    let maxBooks = 0;

    /**
     * Initialize Cart Manager
     */
    function init() {
        console.log('[Cart] Initializing Cart Manager...');
        
        // Setup event listeners
        setupEventListeners();
        
        // Initial render
        render();
        
        console.log('[Cart] Initialization complete');
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Process button
        document.getElementById('btn-process')?.addEventListener('click', processTransaction);
        
        // Reset button
        document.getElementById('btn-reset')?.addEventListener('click', resetCart);
    }

    /**
     * Add book to cart
     */
    function addBook(bookData) {
        console.log('[Cart] Adding book:', bookData);
        
        // Validation: Check if already in cart
        const exists = cart.find(item => item.uid === bookData.uid);
        if (exists) {
            showToast('warning', 'Buku sudah ada di keranjang');
            return false;
        }
        
        // Validation: Check max books limit
        if (cart.length >= maxBooks) {
            showToast('error', `Maksimal ${maxBooks} buku untuk member ini`);
            return false;
        }
        
        // Add to cart
        cart.push(bookData);
        
        // Update UI
        render();
        
        return true;
    }

    /**
     * Remove book from cart
     */
    function removeBook(index) {
        console.log('[Cart] Removing book at index:', index);
        
        Swal.fire({
            title: 'Hapus Buku?',
            text: `Hapus "${cart[index].judul_buku}" dari keranjang?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-secondary'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                cart.splice(index, 1);
                render();
                showToast('success', 'Buku dihapus dari keranjang');
            }
        });
    }

    /**
     * Reset entire cart
     */
    function resetCart() {
        if (cart.length === 0) {
            showToast('info', 'Keranjang sudah kosong');
            return;
        }
        
        Swal.fire({
            title: 'Reset Transaksi?',
            html: `
                <p>Menghapus semua buku dari keranjang dan mereset member?</p>
                <p class="text-danger"><strong>${cart.length} buku</strong> akan dihapus</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Reset',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-warning',
                cancelButton: 'btn btn-secondary'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                cart = [];
                render();
                
                // Also reset member
                if (typeof resetMember === 'function') {
                    resetMember();
                }
                
                showToast('success', 'Transaksi direset');
            }
        });
    }

    /**
     * Render cart UI
     */
    function render() {
        const cartEmpty = document.getElementById('cart-empty');
        const cartItems = document.getElementById('cart-items');
        const cartSummary = document.getElementById('cart-summary');
        const cartBadge = document.getElementById('cart-badge');
        const btnProcess = document.getElementById('btn-process');
        
        // Update badge
        cartBadge.textContent = `${cart.length} buku`;
        
        if (cart.length === 0) {
            // Show empty state
            cartEmpty.classList.remove('d-none');
            cartItems.classList.add('d-none');
            cartSummary.classList.add('d-none');
            btnProcess.disabled = true;
        } else {
            // Show cart items
            cartEmpty.classList.add('d-none');
            cartItems.classList.remove('d-none');
            cartSummary.classList.remove('d-none');
            btnProcess.disabled = false;
            
            // Render items
            renderItems();
            
            // Update summary
            updateSummary();
        }
    }

    /**
     * Render cart items
     */
    function renderItems() {
        const container = document.getElementById('cart-items');
        
        const html = cart.map((book, index) => {
            const kondisiBadge = {
                'baik': 'bg-success',
                'rusak_ringan': 'bg-warning'
            }[book.kondisi] || 'bg-secondary';
            
            return `
                <div class="cart-item" data-index="${index}">
                    <div class="cart-item-cover">
                        <img src="${book.cover_image || '../../../assets/img/default-book.png'}" 
                             alt="${book.judul_buku}">
                    </div>
                    <div class="cart-item-info">
                        <div class="cart-item-title">${book.judul_buku}</div>
                        <div class="cart-item-author">${book.pengarang || 'Anonim'}</div>
                        <div class="cart-item-meta">
                            <span class="badge bg-info">${book.kode_eksemplar}</span>
                            <span class="badge ${kondisiBadge}">${book.kondisi.replace('_', ' ')}</span>
                            <span class="badge bg-secondary">ISBN: ${book.isbn}</span>
                        </div>
                        <div class="cart-item-due">
                            <i class="far fa-calendar-alt me-1"></i>
                            Kembali: ${book.due_date_preview}
                        </div>
                    </div>
                    <div class="cart-item-actions">
                        <button class="btn btn-sm btn-danger" 
                                onclick="CartManager.removeBook(${index})"
                                title="Hapus">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = html;
    }

    /**
     * Update cart summary
     */
    function updateSummary() {
        const totalBooksSpan = document.getElementById('total-books');
        const dueDateDisplay = document.getElementById('due-date-display');
        
        totalBooksSpan.textContent = cart.length;
        
        // Get due date from first book (all have same due date)
        if (cart.length > 0) {
            dueDateDisplay.textContent = cart[0].due_date_preview;
        }
    }

    /**
     * Process transaction
     */
    async function processTransaction() {
        if (cart.length === 0) {
            showToast('error', 'Keranjang kosong');
            return;
        }
        
        const currentMember = window.PeminjamanApp?.currentMember;
        if (!currentMember) {
            showToast('error', 'Member tidak ditemukan');
            return;
        }
        
        // Show confirmation
        const confirm = await Swal.fire({
            title: 'Konfirmasi Peminjaman',
            html: `
                <div class="text-start">
                    <p><strong>Member:</strong> ${currentMember.nama}</p>
                    <p><strong>NIM:</strong> ${currentMember.no_identitas}</p>
                    <p><strong>Jumlah Buku:</strong> ${cart.length} buku</p>
                    <p><strong>Tanggal Kembali:</strong> ${cart[0].due_date_preview}</p>
                    <hr>
                    <p class="text-muted small mb-0">Pastikan semua data sudah benar</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Proses',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-secondary'
            }
        });
        
        if (!confirm.isConfirmed) return;
        
        // Show loading
        Swal.fire({
            title: 'Memproses...',
            html: 'Mohon tunggu, sedang memproses peminjaman',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            const response = await fetch('../../../includes/api/peminjaman/process_peminjaman.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    member_id: currentMember.id,
                    books: cart.map(book => ({
                        book_id: book.id,
                        uid_buffer_id: book.uid_buffer_id,
                        kode_eksemplar: book.kode_eksemplar
                    })),
                    durasi_hari: window.SYSTEM_CONFIG?.durasiBuku || 7
                })
            });
            
            if (!response.ok) {
                throw new Error(`API error: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                showSuccessModal(result.data);
                
                // Reset cart and member
                cart = [];
                render();
                if (typeof resetMember === 'function') {
                    resetMember();
                }
                
                // Refresh monitoring table
                if (typeof refreshMonitoringTable === 'function') {
                    refreshMonitoringTable();
                }
                
                // Update statistics
                if (typeof updateStatistics === 'function') {
                    updateStatistics();
                }
            } else {
                throw new Error(result.message || 'Terjadi kesalahan');
            }
        } catch (error) {
            console.error('[Cart] Process error:', error);
            Swal.fire({
                title: 'Gagal!',
                text: error.message || 'Terjadi kesalahan saat memproses peminjaman',
                icon: 'error',
                confirmButtonText: 'Tutup'
            });
        }
    }

    /**
     * Show success modal with receipt options
     */
    function showSuccessModal(data) {
        const detailsHtml = data.details.map(d => `
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <span>${d.buku}</span>
                <code class="text-success">${d.kode}</code>
            </div>
        `).join('');
        
        Swal.fire({
            title: '✅ Peminjaman Berhasil!',
            html: `
                <div class="text-start">
                    <div class="alert alert-success">
                        <strong>Kode Batch:</strong> ${data.kode_batch}
                    </div>
                    <p><strong>${data.total_books} buku</strong> berhasil dipinjam</p>
                    <hr>
                    <div class="small">
                        ${detailsHtml}
                    </div>
                </div>
            `,
            icon: 'success',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fas fa-print me-2"></i>Cetak Struk',
            denyButtonText: '<i class="fas fa-redo me-2"></i>Transaksi Baru',
            cancelButtonText: 'Tutup',
            customClass: {
                confirmButton: 'btn btn-primary',
                denyButton: 'btn btn-success',
                cancelButton: 'btn btn-secondary'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                printReceipt(data.kode_batch);
            } else if (result.isDenied) {
                // Already reset, just close
                showToast('info', 'Siap untuk transaksi baru');
            }
        });
    }

    /**
     * Print receipt
     */
    function printReceipt(kodeBatch) {
        const url = `../../../includes/api/peminjaman/print_receipt.php?batch=${kodeBatch}`;
        window.open(url, '_blank', 'width=800,height=600');
    }

    /**
     * Get cart data
     */
    function getCart() {
        return cart;
    }

    /**
     * Set max books limit
     */
    function setMaxBooks(max) {
        maxBooks = max;
    }

    /**
     * Utility: Show toast notification
     */
    function showToast(type, message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    }

    // Public API
    return {
        init: init,
        addBook: addBook,
        removeBook: removeBook,
        reset: resetCart,
        getCart: getCart,
        setMaxBooks: setMaxBooks
    };
})();

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    CartManager.init();
});