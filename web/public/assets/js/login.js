// filepath: assets/js/login.js

document.addEventListener('DOMContentLoaded', function() {
    // --- Elements ---
    const html = document.documentElement;
    const themeToggleBtn = document.getElementById('themeToggle');
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.querySelector('.btn-login');

    // --- 1. Theme Toggle Logic ---
    // Check localStorage for saved theme, default to 'light'
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent scroll jump
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    }

    function updateThemeIcon(theme) {
        if (!themeToggleBtn) return;
        const icon = themeToggleBtn.querySelector('i');
        if (theme === 'dark') {
            icon.className = 'fas fa-sun text-warning'; // Sun icon yellow/orange
        } else {
            icon.className = 'fas fa-moon text-secondary';
        }
    }

    // --- 2. Password Visibility Toggle (Updated for Bootstrap Button) ---
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle Icon
            const icon = this.querySelector('i');
            if (type === 'password') {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        });
    }

    // --- 3. Form Submission Handling (Loading State) ---
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            // Cek validitas form bawaan HTML5/Bootstrap
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                // Jika valid, tampilkan loading
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Loading...';
                submitBtn.classList.add('disabled');
            }
            
            this.classList.add('was-validated');
        });
    }
});