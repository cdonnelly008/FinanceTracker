<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

initialiseDataFiles();

$transactionsFile = __DIR__ . '/../data/transactions.json';
$usersFile = __DIR__ . '/../data/users.json';

$transactions = loadJsonData($transactionsFile);
$users = loadJsonData($usersFile);

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// default to gbp unless the user has picked something else
$userCurrency = 'GBP';

foreach ($users as $user) {
    if (isset($user['userId']) && (int)$user['userId'] === (int)$userId) {
        if (!empty($user['defaultCurrency'])) {
            $userCurrency = (string)$user['defaultCurrency'];
        }
        break;
    }
}

$search = trim($_GET['search'] ?? '');

$allowedPeriods = ['week', 'month', 'year'];
$period = $_GET['period'] ?? 'month';

// fallback to month if someone puts an invalid period in the url
if (!in_array($period, $allowedPeriods, true)) {
    $period = 'month';
}

// only pull transactions for the signed in user, then apply the search box if needed
$userTransactions = array_filter($transactions, function ($transaction) use ($userId, $search) {
    $belongsToUser = isset($transaction['userId']) && (int)$transaction['userId'] === (int)$userId;

    if (!$belongsToUser) {
        return false;
    }

    if ($search === '') {
        return true;
    }

    $searchLower = strtolower($search);
    $description = strtolower((string)($transaction['description'] ?? ''));
    $category = strtolower((string)($transaction['category'] ?? ''));
    $type = strtolower((string)($transaction['type'] ?? ''));
    $amount = strtolower((string)($transaction['amount'] ?? ''));

    return
        strpos($description, $searchLower) !== false ||
        strpos($category, $searchLower) !== false ||
        strpos($type, $searchLower) !== false ||
        strpos($amount, $searchLower) !== false;
});

$currentMonth = date('Y-m');
$monthlyIncome = 0;
$monthlyExpenses = 0;

// works out the quick totals for this month
foreach ($userTransactions as $transaction) {
    if (!isset($transaction['date'], $transaction['amount'], $transaction['type'])) {
        continue;
    }

    if (strpos((string)$transaction['date'], $currentMonth) === 0) {
        if (($transaction['type'] ?? '') === 'incoming') {
            $monthlyIncome += (float)$transaction['amount'];
        } elseif (($transaction['type'] ?? '') === 'outgoing') {
            $monthlyExpenses += (float)$transaction['amount'];
        }
    }
}

$monthlyNetBalance = $monthlyIncome - $monthlyExpenses;

// keeps the newest transactions at the top
usort($userTransactions, function ($a, $b) {
    return strtotime($b['date'] ?? '') <=> strtotime($a['date'] ?? '');
});

$recentTransactions = array_slice($userTransactions, 0, 5);

$categoryTotals = [];

// adds up spending per category for the dashboard breakdown
foreach ($userTransactions as $transaction) {
    if (
        isset($transaction['type'], $transaction['category'], $transaction['amount']) &&
        $transaction['type'] === 'outgoing'
    ) {
        $categoryName = (string)$transaction['category'];
        $categoryTotals[$categoryName] = ($categoryTotals[$categoryName] ?? 0) + (float)$transaction['amount'];
    }
}

arsort($categoryTotals);
$maxCategoryTotal = !empty($categoryTotals) ? max($categoryTotals) : 0;

$chartLabels = [];
$chartValues = [];

// builds the dashboard chart data depending on the selected period
if ($period === 'week') {
    $days = [];

    for ($i = 6; $i >= 0; $i--) {
        $dateKey = date('Y-m-d', strtotime("-$i days"));
        $days[$dateKey] = [
            'label' => date('D', strtotime($dateKey)),
            'total' => 0
        ];
    }

    foreach ($userTransactions as $transaction) {
        if (($transaction['type'] ?? '') !== 'outgoing') {
            continue;
        }

        $date = $transaction['date'] ?? '';
        if (isset($days[$date])) {
            $days[$date]['total'] += (float)($transaction['amount'] ?? 0);
        }
    }

    foreach ($days as $day) {
        $chartLabels[] = $day['label'];
        $chartValues[] = $day['total'];
    }
} elseif ($period === 'year') {
    $months = [];

    for ($i = 1; $i <= 12; $i++) {
        $monthKey = date('Y') . '-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $months[$monthKey] = [
            'label' => date('M', mktime(0, 0, 0, $i, 1)),
            'total' => 0
        ];
    }

    foreach ($userTransactions as $transaction) {
        if (($transaction['type'] ?? '') !== 'outgoing') {
            continue;
        }

        $monthKey = substr((string)($transaction['date'] ?? ''), 0, 7);
        if (isset($months[$monthKey])) {
            $months[$monthKey]['total'] += (float)($transaction['amount'] ?? 0);
        }
    }

    foreach ($months as $month) {
        $chartLabels[] = $month['label'];
        $chartValues[] = $month['total'];
    }
} else {
    $days = [];
    $daysInMonth = (int)date('t');

    for ($i = 1; $i <= $daysInMonth; $i++) {
        $dateKey = date('Y-m-') . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $days[$dateKey] = [
            'label' => (string)$i,
            'total' => 0
        ];
    }

    foreach ($userTransactions as $transaction) {
        if (($transaction['type'] ?? '') !== 'outgoing') {
            continue;
        }

        $date = $transaction['date'] ?? '';
        if (isset($days[$date])) {
            $days[$date]['total'] += (float)($transaction['amount'] ?? 0);
        }
    }

    foreach ($days as $day) {
        $chartLabels[] = $day['label'];
        $chartValues[] = $day['total'];
    }
}

$chartSummaryParts = [];

// text version of the chart so screen readers still get the info
for ($i = 0; $i < count($chartLabels); $i++) {
    $label = $chartLabels[$i];
    $value = $chartValues[$i] ?? 0;
    $chartSummaryParts[] = $label . ': ' . $userCurrency . ' ' . number_format((float)$value, 2);
}
$chartSummaryText = implode(', ', $chartSummaryParts);

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
?>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php endif; ?>

<main class="dashboard-main" id="main-content">
    <!-- page header -->
    <header class="dashboard-header dashboard-header-tools">
        <div>
            <h2>Dashboard</h2>
            <p>Hello, <?php echo sanitiseOutput($userName); ?>.</p>
        </div>

        <div class="dashboard-header-actions">
            <form method="get" action="dashboard.php" class="header-search">
                <label for="dashboard-search" class="visually-hidden">Search transactions</label>
                <input
                    type="text"
                    id="dashboard-search"
                    name="search"
                    placeholder="Search transactions..."
                    value="<?php echo sanitiseOutput($search); ?>">
            </form>

            <button type="button" class="theme-toggle-btn" id="themeToggle" aria-label="Toggle light and dark mode">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </header>

    <!-- mobile hero card -->
    <div class="mobile-dash-hero" aria-hidden="true">
        <div class="mobile-dash-hero-label">Primary Account Balance</div>
        <div class="mobile-dash-hero-amount">
            <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($monthlyNetBalance, 2); ?>
        </div>
        <div class="mobile-dash-hero-sub">
            <span class="mobile-dash-hero-pill">This month</span>
        </div>
        <div class="mobile-dash-mini-cards">
            <div class="mobile-dash-mini-card">
                <div class="mobile-dash-mini-label">Income</div>
                <div class="mobile-dash-mini-amount">
                    <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($monthlyIncome, 2); ?>
                </div>
            </div>
            <div class="mobile-dash-mini-card">
                <div class="mobile-dash-mini-label">Expenses</div>
                <div class="mobile-dash-mini-amount">
                    <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($monthlyExpenses, 2); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- top summary cards -->
    <section class="summary-cards dash-summary-cards" aria-label="Financial summary">
        <div class="summary-card stat-card">
            <div class="stat-card-top">
                <h3>Balance</h3>
                <div class="stat-icon stat-icon-balance"><i class="fas fa-wallet" aria-hidden="true"></i></div>
            </div>
            <p class="<?php echo $monthlyNetBalance < 0 ? 'amount-negative' : 'amount-positive'; ?>">
                <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($monthlyNetBalance, 2); ?>
            </p>
            <span>This month</span>
        </div>

        <div class="summary-card stat-card">
            <div class="stat-card-top">
                <h3>Income</h3>
                <div class="stat-icon stat-icon-income"><i class="fas fa-arrow-down" aria-hidden="true"></i></div>
            </div>
            <p class="amount-positive">
                <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($monthlyIncome, 2); ?>
            </p>
            <span>Money received</span>
        </div>

        <div class="summary-card stat-card">
            <div class="stat-card-top">
                <h3>Expenses</h3>
                <div class="stat-icon stat-icon-expense"><i class="fas fa-arrow-up" aria-hidden="true"></i></div>
            </div>
            <p class="amount-negative">
                <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($monthlyExpenses, 2); ?>
            </p>
            <span>Money spent</span>
        </div>
    </section>

    <!-- top two panel layout -->
    <section class="dashboard-top-grid">
        <!-- spending chart panel -->
        <div class="panel chart-panel">
            <div class="panel-header">
                <div>
                    <h3>Spending Trends</h3>
                    <p>Overview of outgoing transactions</p>
                </div>
                <div class="period-buttons" aria-label="Select chart period">
                    <a href="dashboard.php?period=week&search=<?php echo urlencode($search); ?>" class="<?php echo ($period === 'week') ? 'period-link active-period ajax-link' : 'period-link ajax-link'; ?>">Week</a>
                    <a href="dashboard.php?period=month&search=<?php echo urlencode($search); ?>" class="<?php echo ($period === 'month') ? 'period-link active-period ajax-link' : 'period-link ajax-link'; ?>">Month</a>
                    <a href="dashboard.php?period=year&search=<?php echo urlencode($search); ?>" class="<?php echo ($period === 'year') ? 'period-link active-period ajax-link' : 'period-link ajax-link'; ?>">Year</a>
                </div>
            </div>

            <div class="dashboard-chart-container">
                <canvas
                    id="spendingTrendChart"
                    aria-describedby="chart-summary"
                    data-labels='<?php echo json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'
                    data-values='<?php echo json_encode($chartValues, JSON_NUMERIC_CHECK); ?>'
                    data-currency="<?php echo sanitiseOutput($userCurrency); ?>"></canvas>
            </div>

            <p id="chart-summary" class="visually-hidden">
                Spending chart for the selected <?php echo sanitiseOutput($period); ?> period.
                Values shown are <?php echo sanitiseOutput($chartSummaryText); ?>.
            </p>
        </div>

        <!-- category breakdown panel -->
        <div class="panel category-panel">
            <h3>Expense Categories</h3>

            <?php if (!empty($categoryTotals)): ?>
                <?php foreach ($categoryTotals as $category => $total): ?>
                    <?php $percent = $maxCategoryTotal > 0 ? ($total / $maxCategoryTotal) * 100 : 0; ?>
                    <div class="category-row">
                        <div class="category-text">
                            <span><?php echo sanitiseOutput($category); ?></span>
                            <span><?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($total, 2); ?></span>
                        </div>
                        <div class="progress-bar" aria-hidden="true">
                            <div class="progress-fill" style="width: <?php echo round($percent); ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No category data available yet. Add some outgoing transactions to see your spending breakdown.</p>
            <?php endif; ?>

            <a href="categories.php" class="secondary-button ajax-link">View Category Details</a>
        </div>
    </section>

    <!-- recent transactions panel -->
    <section class="panel transaction-panel">
        <div class="panel-header">
            <div>
                <h3>Recent Transactions</h3>
                <p>Your latest transactions</p>
            </div>
            <a href="transactions.php" class="small-link ajax-link">See all activity</a>
        </div>

        <?php if (!empty($recentTransactions)): ?>
            <div class="table-wrapper tx-desktop-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Transaction</th>
                            <th scope="col">Category</th>
                            <th scope="col">Date</th>
                            <th scope="col">Type</th>
                            <th scope="col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <tr>
                                <td class="td-desc"><?php echo sanitiseOutput($transaction['description'] ?? ''); ?></td>
                                <td>
                                    <span class="badge badge-default">
                                        <?php echo sanitiseOutput($transaction['category'] ?? ''); ?>
                                    </span>
                                </td>
                                <td class="td-date"><?php echo sanitiseOutput($transaction['date'] ?? ''); ?></td>
                                <td>
                                    <span class="type-pill <?php echo (($transaction['type'] ?? '') === 'incoming') ? 'type-in' : 'type-out'; ?>">
                                        <?php echo sanitiseOutput(ucfirst($transaction['type'] ?? '')); ?>
                                    </span>
                                </td>
                                <td class="td-desc">
                                    <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format((float)($transaction['amount'] ?? 0), 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mobile-dash-tx-list" aria-label="Recent transactions">
                <?php foreach ($recentTransactions as $transaction):
                    $txIsIncoming = (($transaction['type'] ?? '') === 'incoming');
                    $txAmt        = (float)($transaction['amount'] ?? 0);
                    $txCat        = (string)($transaction['category'] ?? 'Uncategorised');
                    if ($txCat === '') $txCat = 'Uncategorised';
                    $txCurr       = (string)($transaction['currency'] ?? $userCurrency);
                ?>
                <div class="tx-card">
                    <div class="tx-card-indicator <?php echo $txIsIncoming ? 'incoming' : 'outgoing'; ?>">
                        <i class="fas <?php echo $txIsIncoming ? 'fa-arrow-down' : 'fa-arrow-up'; ?>" aria-hidden="true"></i>
                    </div>
                    <div class="tx-card-body">
                        <div class="tx-card-desc"><?php echo sanitiseOutput($transaction['description'] ?? ''); ?></div>
                        <div class="tx-card-meta">
                            <span class="badge badge-default"><?php echo sanitiseOutput($txCat); ?></span>
                        </div>
                    </div>
                    <div class="tx-card-amount <?php echo $txIsIncoming ? 'amount-positive' : 'amount-negative'; ?>">
                        <?php echo $txIsIncoming ? '+' : '−'; ?><?php echo sanitiseOutput($txCurr); ?> <?php echo number_format($txAmt, 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No recent transactions available. Add a new transaction to start tracking your finances.</p>
        <?php endif; ?>
    </section>
</main>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
