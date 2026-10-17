</div>

<!-- Scripts needed for the app to work - jquery for ajax, chart.js for the graphs -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../js/chart.js"></script>
<script src="../js/ajax.js"></script>

<script>
    // this function sets up the dark/light mode toggle buttons
    function initialiseThemeToggle() {
        // get the html element and body so we can change the theme class on both
        const html = document.documentElement;
        const body = document.body;
        // grabs both toggle buttons (one for desktop, one for mobile)
        const desktopToggleBtn = document.getElementById('themeToggle');
        const mobileToggleBtn = document.getElementById('themeToggleMobile');

        // checks what theme is already set on the html element
        // and makes sure the body matches it so they dont go out of sync
        var dark = html.classList.contains('theme-dark');
        body.classList.toggle('theme-dark', dark);
        body.classList.toggle('theme-light', !dark);

        // updates the icons to match whatever theme is currently active
        updateThemeIcons();

        // this runs when either toggle button is clicked
        function toggleTheme() {
            var nowDark = html.classList.contains('theme-dark');
            // checks if currently in dark mode
            if (nowDark) {
                html.classList.remove('theme-dark');
                html.classList.add('theme-light');
                body.classList.remove('theme-dark');
                body.classList.add('theme-light');
                // saves the choice in localStorage so it remembers after refresh
                localStorage.setItem('financeTrackerTheme', 'light');
            } else {
                html.classList.remove('theme-light');
                html.classList.add('theme-dark');
                body.classList.remove('theme-light');
                body.classList.add('theme-dark');
                localStorage.setItem('financeTrackerTheme', 'dark');
            }
            // update the sun/moon icons after switching
            updateThemeIcons();

            // reload the charts if they exist on the page, otherwise they wont update colours
            if (typeof loadDashboardChart === 'function') loadDashboardChart();
            if (typeof loadReportsCharts === 'function') loadReportsCharts();
        }
        // attaches the toggle function to both buttons if they exist on the page
        if (desktopToggleBtn) desktopToggleBtn.onclick = toggleTheme;
        if (mobileToggleBtn) mobileToggleBtn.onclick = toggleTheme;

        // changes the icon inside the button depending on the current theme
        // shows sun icon in dark mode and moon icon in light mode
        function updateThemeIcons() {
            var isDark = html.classList.contains('theme-dark');
            [desktopToggleBtn, mobileToggleBtn].forEach(function(button) {
                if (!button) return;
                var icon = button.querySelector('i');
                if (!icon) return;
                icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            });
        }
    }
    // waits for the page to fully load before running the theme toggle setup
    document.addEventListener('DOMContentLoaded', function() {
        initialiseThemeToggle();
    });
</script>

</body>

</html>
