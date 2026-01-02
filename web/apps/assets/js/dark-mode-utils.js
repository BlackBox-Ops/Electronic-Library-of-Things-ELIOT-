/**
 * Dark Mode Utilities
 * Path: web/apps/assets/js/dark-mode-utils.js
 * 
 * Utility functions untuk detect dan handle dark mode
 * 
 * @author ELIOT System
 * @version 1.0.0
 */

const DarkModeUtils = (function() {
    'use strict';

    /**
     * Check if dark mode is active
     * @returns {boolean}
     */
    function isDarkMode() {
        return document.documentElement.getAttribute('data-theme') === 'dark';
    }

    /**
     * Get SweetAlert2 custom classes based on theme
     * @returns {object}
     */
    function getSwalCustomClass() {
        if (isDarkMode()) {
            return {
                popup: 'swal-dark-popup',
                title: 'swal-dark-title',
                htmlContainer: 'swal-dark-html',
                confirmButton: 'swal-dark-confirm',
                cancelButton: 'swal-dark-cancel',
                actions: 'swal-dark-actions'
            };
        }
        return {};
    }

    /**
     * Get SweetAlert2 configuration with dark mode support
     * @param {object} config - Base Swal configuration
     * @returns {object}
     */
    function getSwalConfig(config = {}) {
        const baseConfig = {
            customClass: getSwalCustomClass(),
            ...config
        };

        // Apply dark mode background if needed
        if (isDarkMode()) {
            baseConfig.background = '#1e1e1e';
            baseConfig.color = '#e0e0e0';
        }

        return baseConfig;
    }

    /**
     * Show toast with dark mode support
     * @param {string} type - Toast type (success, error, warning, info)
     * @param {string} message - Toast message
     * @param {number} timer - Auto close timer (default 3000ms)
     */
    function showToast(type, message, timer = 3000) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: timer,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            },
            customClass: getSwalCustomClass(),
            background: isDarkMode() ? '#2d2d2d' : '#ffffff',
            color: isDarkMode() ? '#e0e0e0' : '#212529'
        });

        Toast.fire({
            icon: type,
            title: message
        });
    }

    /**
     * Show confirmation dialog with dark mode support
     * @param {object} options - Dialog options
     * @returns {Promise}
     */
    function showConfirm(options = {}) {
        const defaultOptions = {
            title: 'Anda yakin?',
            text: 'Tindakan ini tidak dapat dibatalkan',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            reverseButtons: true
        };

        return Swal.fire(getSwalConfig({
            ...defaultOptions,
            ...options
        }));
    }

    /**
     * Show success message with dark mode support
     * @param {string} title - Success title
     * @param {string} text - Success message
     * @returns {Promise}
     */
    function showSuccess(title, text = '') {
        return Swal.fire(getSwalConfig({
            icon: 'success',
            title: title,
            text: text,
            confirmButtonText: 'OK'
        }));
    }

    /**
     * Show error message with dark mode support
     * @param {string} title - Error title
     * @param {string} text - Error message
     * @returns {Promise}
     */
    function showError(title, text = '') {
        return Swal.fire(getSwalConfig({
            icon: 'error',
            title: title,
            text: text,
            confirmButtonText: 'OK'
        }));
    }

    /**
     * Show loading indicator with dark mode support
     * @param {string} text - Loading text
     */
    function showLoading(text = 'Memproses...') {
        Swal.fire(getSwalConfig({
            title: text,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        }));
    }

    /**
     * Close any active Swal
     */
    function closeLoading() {
        Swal.close();
    }

    // Listen for theme changes
    const themeObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'data-theme') {
                console.log('[DarkMode] Theme changed to:', isDarkMode() ? 'dark' : 'light');
            }
        });
    });

    // Start observing theme changes
    if (document.documentElement) {
        themeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });
    }

    // Expose public methods
    return {
        isDarkMode,
        getSwalCustomClass,
        getSwalConfig,
        showToast,
        showConfirm,
        showSuccess,
        showError,
        showLoading,
        closeLoading
    };
})();

// Make available globally
window.DarkModeUtils = DarkModeUtils;