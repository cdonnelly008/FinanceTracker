document.addEventListener('DOMContentLoaded', function () {
    // gets the main html element and the desktop theme toggle button
    const root = document.documentElement;
    const toggleBtn = document.getElementById('themeToggle');

    // checks what theme was saved last time
    const savedTheme = localStorage.getItem('financeTrackerTheme');

    // applies the saved theme straight away
    if (savedTheme === 'dark') {
        root.classList.add('theme-dark');
        root.classList.remove('theme-light');
    } else {
        root.classList.add('theme-light');
        root.classList.remove('theme-dark');
    }

    // sets the right icon when the page first loads
    updateThemeIcon();

    if (toggleBtn) {
        // switches between light and dark mode when clicked
        toggleBtn.addEventListener('click', function () {
            if (root.classList.contains('theme-light')) {
                root.classList.remove('theme-light');
                root.classList.add('theme-dark');
                localStorage.setItem('financeTrackerTheme', 'dark');
            } else {
                root.classList.remove('theme-dark');
                root.classList.add('theme-light');
                localStorage.setItem('financeTrackerTheme', 'light');
            }

            // swap the icon after changing theme
            updateThemeIcon();
        });
    }

    // moon shows in light mode, sun shows in dark mode
    function updateThemeIcon() {
        if (!toggleBtn) return;

        const icon = toggleBtn.querySelector('i');
        if (!icon) return;

        if (root.classList.contains('theme-light')) {
            icon.className = 'fas fa-moon';
        } else {
            icon.className = 'fas fa-sun';
        }
    }
});
