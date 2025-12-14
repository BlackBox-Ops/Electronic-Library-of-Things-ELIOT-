/**
 * 404.js - Error Page JavaScript with Dark Mode
 * ELIOT - Electronic Library of Things
 */

(function() {
    'use strict';
    
    console.log('%c404 Page Loaded', 'font-size: 16px; color: #628141; font-weight: bold;');
    console.log('%cℹ️ Note: The 404 error is expected - this is an error page', 'color: #888; font-style: italic;');
    
    // ========================================
    // DARK MODE FUNCTIONS
    // ========================================
    
    /**
     * Get theme from localStorage
     */
    function getTheme() {
        return localStorage.getItem('theme') || 'light';
    }
    
    /**
     * Set theme
     */
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        updateThemeIcon(theme);
        console.log('Theme changed to:', theme);
    }
    
    /**
     * Update theme toggle icon
     */
    function updateThemeIcon(theme) {
        const themeToggle = document.getElementById('themeToggle');
        if (!themeToggle) return;
        
        const icon = themeToggle.querySelector('i');
        if (!icon) return;
        
        if (theme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    }
    
    /**
     * Toggle theme
     */
    function toggleTheme() {
        const currentTheme = getTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    }
    
    /**
     * Initialize theme
     */
    function initTheme() {
        const theme = getTheme();
        setTheme(theme);
        console.log('Initial theme:', theme);
    }
    
    // ========================================
    // BACK BUTTON FUNCTION
    // ========================================
    
    /**
     * Safe back navigation function
     */
    function goBackSafely() {
        console.log('Back button clicked - Redirecting to login page');
        
        // Langsung redirect ke login.php
        const baseUrl = window.location.origin + '/eliot';
        window.location.href = baseUrl + '/login.php';
    }
    
    // ========================================
    // ANIMATION FUNCTIONS
    // ========================================
    
    /**
     * Initialize animations
     */
    function initAnimations() {
        const errorCode = document.querySelector('.error-code h1');
        if (errorCode) {
            errorCode.style.opacity = '0';
            errorCode.style.transform = 'translateY(-20px)';
            
            setTimeout(() => {
                errorCode.style.transition = 'all 0.8s ease';
                errorCode.style.opacity = '1';
                errorCode.style.transform = 'translateY(0)';
            }, 300);
        }
        
        const errorImage = document.querySelector('.error-image');
        if (errorImage) {
            errorImage.addEventListener('mouseenter', function() {
                this.style.transition = 'transform 0.3s ease';
            });
            
            errorImage.addEventListener('mouseleave', function() {
                this.style.transition = 'transform 0.5s ease';
            });
        }
    }
    
    // ========================================
    // EVENT LISTENERS
    // ========================================
    
    /**
     * Setup all event listeners
     */
    function setupEventListeners() {
        // Theme toggle button
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', toggleTheme);
        }
        
        // Back button event listener (jika menggunakan ID)
        const backButton = document.getElementById('backButton');
        if (backButton) {
            backButton.addEventListener('click', goBackSafely);
        }
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Easter egg: Click 5x on SVG
        setupEasterEgg();
    }
    
    // ========================================
    // EASTER EGG
    // ========================================
    
    /**
     * Setup easter egg (5x click)
     */
    function setupEasterEgg() {
        const errorImage = document.querySelector('.error-image');
        if (!errorImage) return;
        
        let clickCount = 0;
        let lastClickTime = 0;
        const clickTimeout = 2000; // 2 seconds
        
        errorImage.addEventListener('click', function(e) {
            const currentTime = new Date().getTime();
            
            // Reset count if too long between clicks
            if (currentTime - lastClickTime > clickTimeout) {
                clickCount = 0;
            }
            
            clickCount++;
            lastClickTime = currentTime;
            
            // Visual feedback
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 200);
            
            // Easter egg triggers
            if (clickCount >= 5) {
                showEasterEggMessage();
                clickCount = 0;
            }
        });
    }
    
    /**
     * Show easter egg message
     */
    function showEasterEggMessage() {
        const messages = [
            "You found the secret!",
            "Keep exploring ELIOT!",
            "Every search is an adventure",
            "Curiosity leads to knowledge",
            "Never stop learning"
        ];
        
        const randomMessage = messages[Math.floor(Math.random() * messages.length)];
        
        // Simple alert (dapat diganti dengan toast notification)
        console.log('%c' + randomMessage, 'font-size: 20px; color: #628141; font-weight: bold;');
        
        // Optional: Show browser alert
        if (confirm(randomMessage + '\n\nWant to go back to homepage?')) {
            window.location.href = '/eliot/index.php';
        }
    }
    
    // ========================================
    // UTILITY FUNCTIONS
    // ========================================
    
    /**
     * Check if element exists in DOM
     */
    function elementExists(selector) {
        return document.querySelector(selector) !== null;
    }
    
    /**
     * Log info to console
     */
    function logInfo() {
        console.log('%c╔═══════════════════════════════════════════╗', 'color: #628141;');
        console.log('%c║  ELIOT - 404 Error Page                  ║', 'color: #628141; font-weight: bold;');
        console.log('%c╠═══════════════════════════════════════════╣', 'color: #628141;');
        console.log('%c║  Theme: ' + getTheme().padEnd(33) + '║', 'color: #888;');
        console.log('%c║  URL: ' + window.location.pathname.slice(0, 33).padEnd(33) + '║', 'color: #888;');
        console.log('%c╚═══════════════════════════════════════════╝', 'color: #628141;');
    }
    
    // ========================================
    // INITIALIZATION
    // ========================================
    
    /**
     * Initialize everything when DOM is ready
     */
    function init() {
        console.log('Initializing 404 page...');
        
        // 1. Setup theme
        initTheme();
        
        // 2. Setup animations
        initAnimations();
        
        // 3. Setup event listeners
        setupEventListeners();
        
        // 4. Log info
        logInfo();
        
        console.log('404 page initialized successfully ✓');
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // ========================================
    // EXPOSE PUBLIC API (Optional)
    // ========================================
    
    window.ELIOT404 = {
        getTheme: getTheme,
        setTheme: setTheme,
        toggleTheme: toggleTheme,
        goBackSafely: goBackSafely
    };
    
})();