<?php
// use a blank value if the page didnt set the current page name
$currentPage = $currentPage ?? '';
?>

<!-- Mobile header -->
<div class="mobile-header" id="mobile-header">
    <div class="mobile-header-brand">
        <div class="logo-box"><i class="fas fa-wallet" aria-hidden="true"></i></div>
        <span>FinanceTracker</span>
    </div>

    <div class="mobile-header-actions">
        <!-- dark mode switch for mobile -->
        <button
            type="button"
            class="theme-toggle-btn mobile-theme-toggle"
            id="themeToggleMobile"
            aria-label="Toggle light and dark mode">
            <i class="fas fa-moon"></i>
        </button>

        <!-- hamburger button that opens the sidebar on mobile -->
        <button
            class="hamburger-btn"
            id="hamburger-btn"
            aria-label="Open navigation"
            aria-expanded="false"
            aria-controls="sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</div>

<!-- dark overlay behind the sidebar when it's open on mobile -->
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">
    <!-- close button inside the sidebar for mobile -->
    <button class="sidebar-close-btn" id="sidebar-close-btn" aria-label="Close navigation">
        <i class="fas fa-times"></i>
    </button>

    <div class="sidebar-brand">
        <div class="sidebar-brand-main">
            <div class="logo-box"><i class="fas fa-wallet" aria-hidden="true"></i></div>

            <div class="sidebar-brand-text">
                <h1>FinanceTracker</h1>
                <!-- show the current page name under the logo, ucfirst capitalises the first letter -->
                <p id="sidebar-current-page"><?php echo ucfirst($currentPage); ?></p>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Main navigation">
        <!-- each link checks if its the current page and adds the active class if so -->
        <!-- aria-current helps screen readers know which page is active -->
        <a href="dashboard.php"
            data-page-name="Dashboard"
            class="<?php echo ($currentPage === 'dashboard') ? 'active ajax-link' : 'ajax-link'; ?>"
            <?php echo ($currentPage === 'dashboard') ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>

        <a href="transactions.php"
            data-page-name="Transactions"
            class="<?php echo ($currentPage === 'transactions') ? 'active ajax-link' : 'ajax-link'; ?>"
            <?php echo ($currentPage === 'transactions') ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-receipt"></i>
            <span>Transactions</span>
        </a>

        <a href="reports.php"
            data-page-name="Reports"
            class="<?php echo ($currentPage === 'reports') ? 'active ajax-link' : 'ajax-link'; ?>"
            <?php echo ($currentPage === 'reports') ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>

        <a href="categories.php"
            data-page-name="Categories"
            class="<?php echo ($currentPage === 'categories') ? 'active ajax-link' : 'ajax-link'; ?>"
            <?php echo ($currentPage === 'categories') ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-tags"></i>
            <span>Categories</span>
        </a>

        <a href="account.php"
            data-page-name="Account"
            class="<?php echo ($currentPage === 'account') ? 'active ajax-link' : 'ajax-link'; ?>"
            <?php echo ($currentPage === 'account') ? 'aria-current="page"' : ''; ?>>
            <i class="fas fa-user"></i>
            <span>Account</span>
        </a>
        <!-- logout doesnt need an active state -->
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <!-- add transaction button sits at the bottom of the sidebar -->
    <div class="sidebar-action">
        <!-- desktop dark mode switch - lives here so it's on every page -->
        <button type="button" class="sidebar-theme-btn" id="themeToggle" aria-label="Toggle light and dark mode">
            <i class="fas fa-moon" aria-hidden="true"></i>
            <span class="theme-toggle-label">Dark mode</span>
        </button>

        <a href="add-transaction.php" data-page-name="New Transaction" class="btn-primary ajax-link">
            <i class="fas fa-plus"></i>
            <span>New Transaction</span>
        </a>
    </div>
</aside>

<!-- bottom nav bar shown on mobile instead of the sidebar -->
<nav class="mobile-bottom-nav" aria-label="Mobile navigation">
    <a href="dashboard.php"
        class="mobile-nav-item ajax-link <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>"
        <?php echo ($currentPage === 'dashboard') ? 'aria-current="page"' : ''; ?>>
        <i class="fas fa-chart-line" aria-hidden="true"></i>
        <span>Dashboard</span>
    </a>

    <a href="transactions.php"
        class="mobile-nav-item ajax-link <?php echo ($currentPage === 'transactions') ? 'active' : ''; ?>"
        <?php echo ($currentPage === 'transactions') ? 'aria-current="page"' : ''; ?>>
        <i class="fas fa-receipt" aria-hidden="true"></i>
        <span>Transactions</span>
    </a>

    <!-- floating action button in the middle of the bottom nav for adding a transaction quickly -->
    <a href="add-transaction.php" class="mobile-nav-fab ajax-link" aria-label="Add transaction">
        <i class="fas fa-plus" aria-hidden="true"></i>
    </a>

    <a href="reports.php"
        class="mobile-nav-item ajax-link <?php echo ($currentPage === 'reports') ? 'active' : ''; ?>"
        <?php echo ($currentPage === 'reports') ? 'aria-current="page"' : ''; ?>>
        <i class="fas fa-chart-bar" aria-hidden="true"></i>
        <span>Reports</span>
    </a>

    <a href="categories.php"
        class="mobile-nav-item ajax-link <?php echo ($currentPage === 'categories') ? 'active' : ''; ?>"
        <?php echo ($currentPage === 'categories') ? 'aria-current="page"' : ''; ?>>
        <i class="fas fa-tags" aria-hidden="true"></i>
        <span>Categories</span>
    </a>
</nav>

<script>
    // sets up all the open/close behaviour for the mobile sidebar
    function initialiseMobileSidebar() {
        var hamburger = document.getElementById('hamburger-btn');
        var closeBtn = document.getElementById('sidebar-close-btn');
        var overlay = document.getElementById('sidebar-overlay');
        var sidebar = document.getElementById('sidebar');

        // stop the function if any of the elements are missing from the page
        if (!hamburger || !closeBtn || !overlay || !sidebar) {
            return;
        }

        function openSidebar() {
            sidebar.classList.add('sidebar-open');
            overlay.classList.add('active'); // show the dark overlay behind the sidebar
            overlay.setAttribute('aria-hidden', 'false');
            hamburger.setAttribute('aria-expanded', 'true'); // tells screen readers the menu is open
            document.body.style.overflow = 'hidden'; // stop the page scrolling while sidebar is open
        }

        function closeSidebar() {
            sidebar.classList.remove('sidebar-open');
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        // hook the buttons up to the open and close functions
        hamburger.onclick = openSidebar;
        closeBtn.onclick = closeSidebar;
        overlay.onclick = closeSidebar; // clicking the overlay also closes the sidebar

        // clicking the overlay also closes the sidebar
        sidebar.querySelectorAll('.sidebar-nav a').forEach(function(link) {
            link.onclick = closeSidebar;
        });

        // clicking the overlay also closes the sidebar
        document.onkeydown = function(e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        };
    }

    // run this once the page has loaded
    document.addEventListener('DOMContentLoaded', function() {
        initialiseMobileSidebar();
    });
</script>
