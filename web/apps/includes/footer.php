<?php
// ~/Documents/ELIOT/web/apps/includes/footer.php
?>
        </main>
        <footer class="footer border-top p-3 text-center">
            <span class="text-muted small">&copy; <?= date('Y') ?> ELIOT - Integrated Asset Management.</span>
        </footer>
        
    </div> 
    </div> 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="<?= $baseUrl ?>/apps/assets/js/dashboard.js"></script>

<script>
// Logic ini diletakkan di footer sebagai fallback/ensure, tapi fungsi utamanya ada di dashboard.js
document.addEventListener('DOMContentLoaded', function() {
    const html = document.documentElement;
    const themeToggleBtn = document.getElementById('themeToggle');

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
    
    // Pastikan tema diterapkan saat load
    setThemeOnLoad();
});
</script>

</body>
</html>