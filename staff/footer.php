    </main>
</div>

<script>
(function() {
    var saved = localStorage.getItem('staff-theme');
    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var theme = saved || (prefersDark ? 'dark' : 'light');
    applyTheme(theme);

    var btn = document.getElementById('staffSidebar');
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 767 && btn.classList.contains('open')) {
            var sidebar = btn;
            var toggle = e.target.closest('.sidebar-toggle-btn');
            if (!sidebar.contains(e.target) || toggle) {
                sidebar.classList.remove('open');
            }
        }
    });
})();

function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    var icon = document.getElementById('themeIcon');
    if (icon) {
        icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    }
    localStorage.setItem('staff-theme', theme);
}

function toggleTheme() {
    var current = document.documentElement.getAttribute('data-bs-theme') || 'light';
    applyTheme(current === 'dark' ? 'light' : 'dark');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
