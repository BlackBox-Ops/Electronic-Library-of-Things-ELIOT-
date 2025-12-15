// ~/Documents/ELIOT/web/apps/assets/js/dashboard.js
// Menggunakan Camel Case untuk penamaan variabel

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // --- Elements ---
    const html = document.documentElement;
    const wrapper = document.getElementById('wrapper');
    const sidebar = document.getElementById('sidebar');
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const fullscreenToggleBtn = document.getElementById('fullscreenToggle');
    const themeToggleBtn = document.getElementById('themeToggle');

    // --- 1. Theme Toggle Logic ---
    function updateThemeIcon(theme) {
        if (!themeToggleBtn) return;
        const icon = themeToggleBtn.querySelector('.eliot-theme-icon');
        if (!icon) return;
        
        if (theme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    }
    
    function getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        updateThemeIcon(theme);
    }

    function setThemeOnLoad() {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            applyTheme(savedTheme);
        } else {
            applyTheme(getSystemTheme());
        }
    }
    
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            applyTheme(newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }
    
    setThemeOnLoad();

    // --- 2. Sidebar Toggle Logic (Full Hide) ---
    if (sidebarToggleBtn && sidebar && wrapper) {
        
        function toggleSidebar() {
            // Toggle class 'sidebar-toggled' pada wrapper (Untuk Desktop & Mobile)
            wrapper.classList.toggle('sidebar-toggled'); 
            
            // Logika spesifik untuk mobile (overlay)
            if (window.innerWidth <= 768) { 
                // Toggle kelas sidebar-toggled di mobile juga akan mengaktifkan CSS 'left: 0'
                // Kita hanya perlu mengelola overlay pada body
                document.body.classList.toggle('sidebar-mobile-overlay');
            }
        }
        
        // Event Listener Tombol Hamburger
        sidebarToggleBtn.addEventListener('click', toggleSidebar);

        // Menutup sidebar jika mengklik di luar (hanya di mobile)
        document.body.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) { 
                // Cek jika sidebar terbuka (berarti wrapper memiliki sidebar-toggled)
                if (wrapper.classList.contains('sidebar-toggled') && !sidebar.contains(e.target) && !sidebarToggleBtn.contains(e.target)) {
                    wrapper.classList.remove('sidebar-toggled');
                    document.body.classList.remove('sidebar-mobile-overlay');
                }
            }
        });
        
        // Atur ulang state saat resize dari mobile ke desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.body.classList.remove('sidebar-mobile-overlay');
            }
        });
        
    }
    
    // --- 3. Fullscreen Toggle Logic ---
    if (fullscreenToggleBtn) {
        
        function updateFullscreenIcon() {
            const icon = fullscreenToggleBtn.querySelector('i');
            if (document.fullscreenElement) {
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
            } else {
                icon.classList.remove('fa-compress');
                icon.classList.add('fa-expand');
            }
        }
        
        fullscreenToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.error(`Error attempting to enable full-screen mode: ${err.message}`);
                });
            } else {
                document.exitFullscreen();
            }
        });
        
        document.addEventListener('fullscreenchange', updateFullscreenIcon);
        
        updateFullscreenIcon();
    }
});