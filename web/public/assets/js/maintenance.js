/**
 * maintenance.js - Maintenance Page JavaScript
 * ELIOT - Electronic Library of Things
 */

(function() {
    'use strict';
    
    console.log('%cELIOT Maintenance Mode', 'font-size: 16px; color: #ff9800; font-weight: bold;');
    
    // ========================================
    // COUNTDOWN TIMER
    // ========================================
    
    /**
     * Update countdown timer display
     */
    function updateCountdown() {
        const timerElement = document.getElementById('countdownTimer');
        if (!timerElement) return;
        
        let time = timerElement.textContent.trim().split(':');
        if (time.length !== 3) return;
        
        let hours = parseInt(time[0]);
        let minutes = parseInt(time[1]);
        let seconds = parseInt(time[2]);
        
        // Countdown logic
        if (seconds > 0) {
            seconds--;
        } else {
            if (minutes > 0) {
                minutes--;
                seconds = 59;
            } else {
                if (hours > 0) {
                    hours--;
                    minutes = 59;
                    seconds = 59;
                } else {
                    // Countdown finished
                    handleMaintenanceComplete();
                    return;
                }
            }
        }
        
        // Update display
        timerElement.textContent = 
            hours.toString().padStart(2, '0') + ':' +
            minutes.toString().padStart(2, '0') + ':' +
            seconds.toString().padStart(2, '0');
        
        // Change color when less than 30 minutes
        if (hours === 0 && minutes < 30) {
            timerElement.style.color = 'var(--color-maintenance)';
        }
    }
    
    /**
     * Handle maintenance completion
     */
    function handleMaintenanceComplete() {
        const timerElement = document.getElementById('countdownTimer');
        if (timerElement) {
            timerElement.innerHTML = '<i class="fas fa-check-circle me-2"></i>COMPLETE';
            timerElement.style.color = 'var(--color-progress)';
        }
        
        // Show notification
        setTimeout(() => {
            if (confirm('Maintenance completed! Reload page to access system?')) {
                window.location.reload();
            }
        }, 1000);
    }
    
    // ========================================
    // PROGRESS ANIMATION
    // ========================================
    
    /**
     * Animate progress bar
     */
    function animateProgress() {
        const progressFills = document.querySelectorAll('.feature-progress-fill');
        
        progressFills.forEach(fill => {
            const targetWidth = fill.style.width;
            fill.style.width = '0%';
            
            setTimeout(() => {
                fill.style.width = targetWidth;
            }, 300);
        });
    }
    
    // ========================================
    // NOTIFICATION SYSTEM
    // ========================================
    
    /**
     * Setup notification button
     */
    function setupNotification() {
        const notifyBtn = document.getElementById('notifyBtn');
        if (!notifyBtn) return;
        
        // Check if already notified
        const savedEmail = localStorage.getItem('eliot_maintenance_notify');
        if (savedEmail) {
            notifyBtn.innerHTML = '<i class="fas fa-bell-slash me-2"></i>Already Notified';
            notifyBtn.style.background = 'linear-gradient(135deg, #95a5a6, #7f8c8d)';
            notifyBtn.disabled = true;
            return;
        }
        
        // Add click handler
        notifyBtn.addEventListener('click', function() {
            const email = prompt('Enter your email to get notified when maintenance is complete:');
            
            if (email && validateEmail(email)) {
                // Save to localStorage
                localStorage.setItem('eliot_maintenance_notify', email);
                
                // Update button
                this.innerHTML = '<i class="fas fa-check me-2"></i>Notification Set!';
                this.style.background = 'linear-gradient(135deg, var(--color-progress), #2ecc71)';
                this.disabled = true;
                
                // Show confirmation
                setTimeout(() => {
                    alert(`✓ You will receive an email at ${email} when maintenance is complete.`);
                }, 300);
                
                console.log('Notification registered:', email);
            } else if (email) {
                alert('Please enter a valid email address.');
            }
        });
    }
    
    /**
     * Validate email address
     */
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // ========================================
    // STATUS CHECK
    // ========================================
    
    /**
     * Check maintenance status periodically
     */
    function checkMaintenanceStatus() {
        // In real implementation, this would be an API call
        fetch('/api/maintenance/status')
            .then(response => response.json())
            .then(data => {
                if (!data.maintenance_mode) {
                    console.log('Maintenance complete! Reloading...');
                    window.location.reload();
                }
            })
            .catch(error => {
                console.log('Status check (simulated):', error);
            });
    }
    
    // ========================================
    // AUTO REFRESH
    // ========================================
    
    /**
     * Setup auto refresh
     */
    function setupAutoRefresh() {
        const refreshInterval = 60000; // 60 seconds
        
        setInterval(() => {
            console.log('Auto-checking maintenance status...');
            checkMaintenanceStatus();
        }, refreshInterval);
    }
    
    // ========================================
    // MODULE INTERACTION
    // ========================================
    
    /**
     * Setup module tag interactions
     */
    function setupModuleTags() {
        const moduleTags = document.querySelectorAll('.module-tag');
        
        moduleTags.forEach(tag => {
            tag.addEventListener('click', function() {
                const moduleName = this.querySelector('span').textContent;
                const progress = this.querySelector('.module-progress').textContent;
                
                alert(`Module: ${moduleName}\nProgress: ${progress}`);
            });
        });
    }
    
    // ========================================
    // INITIALIZATION
    // ========================================
    
    /**
     * Initialize all features
     */
    function init() {
        console.log('Initializing maintenance page...');
        
        // Animate progress bars
        animateProgress();
        
        // Setup notification button
        setupNotification();
        
        // Setup module tags
        setupModuleTags();
        
        // Start countdown
        setInterval(updateCountdown, 1000);
        
        // Setup auto refresh (check status every minute)
        setupAutoRefresh();
        
        console.log('Maintenance page initialized ✓');
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // ========================================
    // EXPOSE PUBLIC API
    // ========================================
    
    window.ELIOTMaintenance = {
        checkStatus: checkMaintenanceStatus,
        simulateComplete: handleMaintenanceComplete
    };
    
})();