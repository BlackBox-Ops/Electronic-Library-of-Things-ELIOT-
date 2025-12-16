document.addEventListener('DOMContentLoaded', function() {
    const html = document.documentElement;
    const wrapper = document.getElementById('wrapper');
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const themeToggleBtn = document.getElementById('themeToggle');
    const fullscreenToggleBtn = document.getElementById('fullscreenToggle');

    // Theme Toggle
    function updateThemeIcon(theme) {
        const icon = themeToggleBtn.querySelector('.eliot-theme-icon');
        if (theme === 'dark') {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    }

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        updateThemeIcon(theme);
        localStorage.setItem('theme', theme);
    }

    function setThemeOnLoad() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        applyTheme(savedTheme);
    }

    themeToggleBtn.addEventListener('click', () => {
        const current = html.getAttribute('data-theme');
        applyTheme(current === 'dark' ? 'light' : 'dark');
    });

    setThemeOnLoad();

    // Sidebar Toggle
    sidebarToggleBtn.addEventListener('click', () => {
        wrapper.classList.toggle('sidebar-toggled');
        if (window.innerWidth <= 768) {
            document.body.classList.toggle('sidebar-mobile-overlay');
        }
    });

    // Fullscreen Toggle
    fullscreenToggleBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    });
});