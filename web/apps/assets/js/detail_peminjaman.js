/**
 * Detail Peminjaman JavaScript
 * Path: web/apps/assets/js/detail_peminjaman.js
 * 
 * Features:
 * - Copy UID to clipboard
 * - Interactive UI elements
 * - Dark mode support
 * 
 * @author ELIOT System
 * @version 1.0.0
 * @date 2026-01-09
 */

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Detail Peminjaman] Page loaded');
    
    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(
            document.querySelectorAll('[data-bs-toggle="tooltip"]')
        );
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Add fade-in animation to cards
    animateCards();
    
    // Countdown timer for due date (if applicable)
    initCountdown();
});

// ============================================
// COPY UID TO CLIPBOARD
// ============================================
function copyToClipboard(text, element) {
    // Create temporary textarea
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    
    // Select and copy
    textarea.select();
    textarea.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        
        // Visual feedback
        const originalHTML = element.innerHTML;
        element.classList.add('copied');
        element.innerHTML = `${text} <i class="fas fa-check copy-icon"></i>`;
        
        // Show toast notification
        showToast('success', 'UID berhasil disalin!', text);
        
        // Reset after 2 seconds
        setTimeout(() => {
            element.classList.remove('copied');
            element.innerHTML = originalHTML;
        }, 2000);
        
    } catch (err) {
        console.error('[Copy] Failed to copy:', err);
        showToast('error', 'Gagal menyalin UID', 'Silakan copy manual');
    } finally {
        document.body.removeChild(textarea);
    }
}

// ============================================
// TOAST NOTIFICATION
// ============================================
function showToast(type, title, message) {
    const iconMap = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const colorMap = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#06b6d4'
    };
    
    // Use SweetAlert2 if available
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: title,
            text: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: {
                popup: 'swal2-toast-dark-mode'
            }
        });
    } else {
        // Fallback to console
        console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
    }
}

// ============================================
// ANIMATE CARDS ON LOAD
// ============================================
function animateCards() {
    const cards = document.querySelectorAll('.card');
    
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * index);
    });
}

// ============================================
// COUNTDOWN TIMER FOR DUE DATE
// ============================================
function initCountdown() {
    // Look for countdown elements
    const countdownElements = document.querySelectorAll('[data-due-date]');
    
    countdownElements.forEach(element => {
        const dueDate = new Date(element.dataset.dueDate);
        
        // Update countdown every second
        const interval = setInterval(() => {
            updateCountdown(element, dueDate, interval);
        }, 1000);
        
        // Initial update
        updateCountdown(element, dueDate, interval);
    });
}

function updateCountdown(element, dueDate, interval) {
    const now = new Date();
    const diff = dueDate - now;
    
    if (diff <= 0) {
        element.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Sudah Lewat';
        element.classList.add('badge', 'bg-danger');
        clearInterval(interval);
        return;
    }
    
    // Calculate time remaining
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    
    // Format display
    let display = '';
    if (days > 0) {
        display = `${days} hari ${hours} jam`;
    } else if (hours > 0) {
        display = `${hours} jam ${minutes} menit`;
    } else if (minutes > 0) {
        display = `${minutes} menit ${seconds} detik`;
    } else {
        display = `${seconds} detik`;
    }
    
    element.innerHTML = `<i class="fas fa-clock me-1"></i>${display} lagi`;
    
    // Color coding
    element.classList.remove('bg-success', 'bg-warning', 'bg-danger');
    if (days > 3) {
        element.classList.add('badge', 'bg-success');
    } else if (days > 0) {
        element.classList.add('badge', 'bg-warning', 'text-dark');
    } else {
        element.classList.add('badge', 'bg-danger');
    }
}

// ============================================
// PRINT FUNCTION (OPTIONAL)
// ============================================
function printDetail() {
    // Hide elements that shouldn't be printed
    const elementsToHide = document.querySelectorAll('.btn, .breadcrumb, nav, footer');
    elementsToHide.forEach(el => {
        el.style.display = 'none';
    });
    
    // Print
    window.print();
    
    // Restore hidden elements
    elementsToHide.forEach(el => {
        el.style.display = '';
    });
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
document.addEventListener('keydown', function(e) {
    // ESC key - Go back
    if (e.key === 'Escape') {
        const backButton = document.querySelector('a[href="index.php"]');
        if (backButton) {
            backButton.click();
        }
    }
    
    // Ctrl/Cmd + P - Print
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printDetail();
    }
});

// ============================================
// SMOOTH SCROLL TO SECTION (IF NEEDED)
// ============================================
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Format date
function formatDate(dateString, format = 'long') {
    const date = new Date(dateString);
    
    const options = {
        short: { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        },
        long: { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }
    };
    
    return new Intl.DateTimeFormat('id-ID', options[format]).format(date);
}

// Get theme
function getCurrentTheme() {
    return document.documentElement.getAttribute('data-theme') || 'light';
}

// ============================================
// EXPORT FUNCTIONS FOR EXTERNAL USE
// ============================================
window.detailPeminjaman = {
    copyToClipboard,
    printDetail,
    scrollToSection,
    formatCurrency,
    formatDate,
    getCurrentTheme
};

console.log('[Detail Peminjaman] JavaScript initialized');
console.log('[Detail Peminjaman] Available functions:', Object.keys(window.detailPeminjaman));