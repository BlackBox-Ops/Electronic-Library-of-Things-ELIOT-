// filepath: assets/js/app.js
// Fungsi Umum untuk Login dan Registrasi: Theme Toggle & Password Toggle

document.addEventListener('DOMContentLoaded', function() {
    // --- Elements ---
    const html = document.documentElement;
    const themeToggleBtn = document.getElementById('themeToggle');
    // Mencari password input pertama, karena ada 2 di registrasi (password & confirm)
    // Di halaman login, ini akan mengambil input password tunggal.
    const passwordInput = document.getElementById('password'); 
    const togglePasswordBtn = document.getElementById('togglePassword');
    const form = document.getElementById('loginForm') || document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn') || document.querySelector('.btn-custom-orange');
    const btnText = document.getElementById('btnText') || submitBtn?.querySelector('span');


    // --- 1. Theme Toggle Logic ---
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
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
            icon.className = 'fas fa-sun text-warning'; // Ikon matahari kuning untuk mode gelap
        } else {
            icon.className = 'fas fa-moon text-secondary'; // Ikon bulan abu-abu untuk mode terang
        }
    }

    // --- 2. Password Visibility Toggle (Password Utama) ---
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
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

    // --- 3. Form Validation & Loading State ---
    if (form) {
        // Cek validasi konfirmasi password hanya untuk halaman registrasi
        if (form.id === 'registerForm') {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const confirmToggleBtn = document.getElementById('toggleConfirmPassword'); // Menggunakan ID yang baru
            
            // Tambahkan toggle password juga untuk konfirmasi password
            if (confirmToggleBtn && confirmPasswordInput) {
                confirmToggleBtn.addEventListener('click', function() {
                    const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordInput.setAttribute('type', type);
                    
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

            confirmPasswordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (password !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity("Password tidak cocok.");
                    document.getElementById('confirmPasswordFeedback').textContent = "Password tidak cocok dengan yang di atas.";
                } else {
                    confirmPasswordInput.setCustomValidity("");
                }
            });
            passwordInput.addEventListener('input', function() {
                // Trigger ulang validasi konfirmasi saat password utama berubah
                confirmPasswordInput.dispatchEvent(new Event('input'));
            });
        }


        form.addEventListener('submit', function(event) {
            // Periksa validitas formulir dan konfirmasi password (jika ini form registrasi)
            if (!this.checkValidity() || (form.id === 'registerForm' && passwordInput.value !== document.getElementById('confirm_password').value)) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                // Tampilkan loading saat sukses validasi klien dan siap kirim
                if (submitBtn) {
                    const originalText = btnText ? btnText.textContent : 'Memproses...';
                    
                    if(btnText) btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Memproses...';
                    
                    submitBtn.setAttribute('data-original-text', originalText);
                    submitBtn.classList.add('disabled');
                }
            }
            this.classList.add('was-validated');
        }, false);
    }
});