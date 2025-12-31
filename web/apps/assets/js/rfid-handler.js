/**
 * RFID Handler Module
 * Path: web/apps/assets/js/rfid-handler.js
 * 
 * Features:
 * - RFID scanner simulation
 * - Real RFID reader integration
 * - Visual & audio feedback
 * - State management
 * 
 * @author ELIOT System
 * @version 1.0.0
 */

const RFIDHandler = (function() {
    'use strict';

    // Private variables
    let isScanning = false;
    let scanMode = null; // 'member' or 'book'
    let audioContext = null;
    
    // Audio feedback
    const sounds = {
        success: () => playBeep(800, 100),
        error: () => playBeep(300, 200),
        scanning: () => playBeep(600, 50)
    };

    /**
     * Initialize RFID Handler
     */
    function init() {
        console.log('[RFID] Initializing RFID Handler...');
        
        // Initialize audio context
        try {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('[RFID] Audio context not available');
        }

        // Setup keyboard listener for RFID simulation
        setupKeyboardListener();
        
        // Setup click listeners for manual input
        setupManualInput();
        
        console.log('[RFID] Initialization complete');
    }

    /**
     * Setup keyboard listener for RFID scanner
     * Most RFID readers act as keyboard input devices
     */
    function setupKeyboardListener() {
        let buffer = '';
        let lastKeyTime = Date.now();
        
        document.addEventListener('keypress', function(e) {
            const currentTime = Date.now();
            
            // If more than 100ms between keystrokes, reset buffer
            // (RFID scanners input very fast)
            if (currentTime - lastKeyTime > 100) {
                buffer = '';
            }
            
            lastKeyTime = currentTime;
            
            // If Enter key, process the buffer as RFID data
            if (e.key === 'Enter' && buffer.length > 0) {
                e.preventDefault();
                handleRFIDScan(buffer.trim());
                buffer = '';
            } else if (e.key !== 'Enter') {
                // Don't add to buffer if focus is on input/textarea
                if (document.activeElement.tagName !== 'INPUT' && 
                    document.activeElement.tagName !== 'TEXTAREA') {
                    buffer += e.key;
                }
            }
        });
    }

    /**
     * Setup manual input for testing without RFID hardware
     */
    function setupManualInput() {
        // Add manual scan buttons (for testing)
        document.addEventListener('click', function(e) {
            if (e.target.closest('[data-manual-scan]')) {
                e.preventDefault();
                const scanType = e.target.closest('[data-manual-scan]').dataset.manualScan;
                showManualScanModal(scanType);
            }
        });
    }

    /**
     * Show manual scan modal for testing
     */
    function showManualScanModal(scanType) {
        Swal.fire({
            title: `Scan ${scanType === 'member' ? 'Member' : 'Buku'} (Manual)`,
            html: `
                <input type="text" id="manual-uid" class="swal2-input" 
                       placeholder="Masukkan UID" autofocus>
                <small class="text-muted d-block mt-2">
                    Untuk testing tanpa RFID reader
                </small>
            `,
            showCancelButton: true,
            confirmButtonText: 'Scan',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-secondary'
            },
            preConfirm: () => {
                const uid = document.getElementById('manual-uid').value;
                if (!uid) {
                    Swal.showValidationMessage('UID tidak boleh kosong');
                    return false;
                }
                return uid;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                handleRFIDScan(result.value);
            }
        });
    }

    /**
     * Handle RFID scan data
     */
    async function handleRFIDScan(uid) {
        console.log('[RFID] Scanned UID:', uid);
        
        // Prevent duplicate scans
        if (isScanning) {
            console.warn('[RFID] Already processing a scan');
            return;
        }
        
        // Determine scan mode based on current state
        determineScanMode();
        
        if (!scanMode) {
            showToast('error', 'Sistem belum siap untuk scan');
            return;
        }
        
        isScanning = true;
        
        // Visual feedback: scanning state
        updateScannerState(scanMode, 'scanning');
        sounds.scanning();
        
        try {
            if (scanMode === 'member') {
                await processMemberScan(uid);
            } else if (scanMode === 'book') {
                await processBookScan(uid);
            }
        } catch (error) {
            console.error('[RFID] Scan error:', error);
            updateScannerState(scanMode, 'error');
            sounds.error();
            showToast('error', 'Terjadi kesalahan saat memproses scan');
        } finally {
            isScanning = false;
        }
    }

    /**
     * Determine scan mode based on UI state
     */
    function determineScanMode() {
        const memberSection = document.getElementById('member-scan-section');
        const bookSection = document.getElementById('book-scan-section');
        
        // Check if member info is already loaded
        const memberInfo = document.getElementById('member-info');
        const hasMember = !memberInfo.classList.contains('d-none');
        
        if (!hasMember) {
            scanMode = 'member';
        } else if (!bookSection.classList.contains('disabled-section')) {
            scanMode = 'book';
        } else {
            scanMode = null;
        }
    }

    /**
     * Process member card scan
     */
    async function processMemberScan(uid) {
        console.log('[RFID] Processing member scan...');
        
        const response = await fetch('../../../includes/api/peminjaman/validate_member.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ uid: uid })
        });
        
        if (!response.ok) {
            throw new Error(`API error: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.validation.valid) {
            updateScannerState('member', 'success');
            sounds.success();
            displayMemberInfo(result.data, result.slots_available);
            showToast('success', `Member teridentifikasi: ${result.data.nama}`);
        } else {
            updateScannerState('member', 'error');
            sounds.error();
            
            const errorMessage = result.validation.errors.join('<br>');
            showToast('error', errorMessage || 'Member tidak valid');
            
            // Reset scanner after error
            setTimeout(() => {
                updateScannerState('member', 'idle');
            }, 2000);
        }
    }

    /**
     * Process book scan
     */
    async function processBookScan(uid) {
        console.log('[RFID] Processing book scan...');
        
        const currentMember = window.PeminjamanApp?.currentMember;
        
        if (!currentMember) {
            throw new Error('No member selected');
        }
        
        const response = await fetch('../../../includes/api/peminjaman/validate_book.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                uid: uid,
                member_id: currentMember.id 
            })
        });
        
        if (!response.ok) {
            throw new Error(`API error: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.success && result.validation.valid) {
            updateScannerState('book', 'success');
            sounds.success();
            
            // Add to cart via CartManager
            window.CartManager.addBook(result.data);
            
            showToast('success', `Buku ditambahkan: ${result.data.judul_buku}`);
            
            // Reset scanner for next book
            setTimeout(() => {
                updateScannerState('book', 'idle');
            }, 800);
        } else {
            updateScannerState('book', 'error');
            sounds.error();
            
            const errorMessage = result.validation.errors.join('<br>');
            showToast('error', errorMessage || 'Buku tidak valid');
            
            // Reset scanner after error
            setTimeout(() => {
                updateScannerState('book', 'idle');
            }, 2000);
        }
    }

    /**
     * Update scanner visual state
     */
    function updateScannerState(type, state) {
        const scannerId = type === 'member' ? 'member-scanner' : 'book-scanner';
        const scanner = document.getElementById(scannerId);
        
        if (!scanner) return;
        
        scanner.setAttribute('data-state', state);
        
        const statusBadge = scanner.querySelector('.scanner-status .badge');
        const scannerText = scanner.querySelector('.scanner-text');
        
        const stateConfig = {
            idle: {
                text: type === 'member' ? 'Menunggu scan kartu...' : 'Tempelkan buku...',
                badge: 'Status: Idle',
                badgeClass: 'bg-secondary'
            },
            scanning: {
                text: 'Memproses...',
                badge: 'Status: Scanning',
                badgeClass: 'bg-primary'
            },
            success: {
                text: 'Berhasil!',
                badge: 'Status: Success',
                badgeClass: 'bg-success'
            },
            error: {
                text: 'Gagal! Coba lagi',
                badge: 'Status: Error',
                badgeClass: 'bg-danger'
            },
            disabled: {
                text: type === 'member' ? 'Scanner disabled' : 'Scan member terlebih dahulu',
                badge: 'Status: Disabled',
                badgeClass: 'bg-secondary'
            }
        };
        
        const config = stateConfig[state];
        if (config) {
            scannerText.textContent = config.text;
            statusBadge.textContent = config.badge;
            statusBadge.className = `badge ${config.badgeClass}`;
        }
    }

    /**
     * Display member information
     */
    function displayMemberInfo(memberData, slotsAvailable) {
        const memberInfo = document.getElementById('member-info');
        const bookSection = document.getElementById('book-scan-section');
        const maxBooksSpan = document.getElementById('max-books');
        
        // Build warnings/errors HTML
        let alertsHtml = '';
        
        if (memberData.total_denda > 0) {
            alertsHtml += `
                <div class="member-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Peringatan:</strong> Member memiliki denda Rp ${numberFormat(memberData.total_denda)}
                </div>
            `;
        }
        
        const html = `
            <div class="d-flex align-items-start gap-3">
                <div class="member-avatar">
                    <img src="${memberData.foto_profil || '../../../assets/img/default-avatar.png'}" 
                         alt="${memberData.nama}">
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-1">${memberData.nama}</h5>
                    <p class="text-muted small mb-2">NIM: ${memberData.no_identitas}</p>
                    <div class="member-meta">
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i>${memberData.status}
                        </span>
                        <span class="badge bg-info">
                            <i class="fas fa-book me-1"></i>
                            Slot: ${memberData.jumlah_pinjam_aktif}/${memberData.max_peminjaman}
                        </span>
                        <span class="badge bg-primary">
                            <i class="fas fa-check me-1"></i>
                            Tersedia: ${slotsAvailable} slot
                        </span>
                    </div>
                    ${alertsHtml}
                </div>
                <button class="btn btn-sm btn-outline-secondary" onclick="resetMember()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        memberInfo.innerHTML = html;
        memberInfo.classList.remove('d-none');
        
        // Enable book scanning
        bookSection.classList.remove('disabled-section');
        updateScannerState('book', 'idle');
        maxBooksSpan.textContent = slotsAvailable;
        
        // Store member data globally
        if (window.PeminjamanApp) {
            window.PeminjamanApp.currentMember = memberData;
            window.PeminjamanApp.maxBooks = slotsAvailable;
        }
    }

    /**
     * Play beep sound
     */
    function playBeep(frequency, duration) {
        if (!audioContext) return;
        
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = frequency;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration / 1000);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + duration / 1000);
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
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    }

    /**
     * Utility: Number formatting
     */
    function numberFormat(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }

    // Public API
    return {
        init: init,
        scan: handleRFIDScan,
        updateState: updateScannerState,
        sounds: sounds
    };
})();

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    RFIDHandler.init();
});