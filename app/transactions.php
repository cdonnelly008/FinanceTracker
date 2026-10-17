<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

initialiseDataFiles();

$transactionsFile = __DIR__ . '/../data/transactions.json';
$usersFile = __DIR__ . '/../data/users.json';

$transactions = loadJsonData($transactionsFile);
$users = loadJsonData($usersFile);

$userId = $_SESSION['user_id'] ?? null;

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// use the users default currency unless a transaction has its own one saved
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
$typeFilter = trim($_GET['type'] ?? '');
$catFilter = trim($_GET['category'] ?? '');

// keeps random querystring values from breaking the filter
$allowedTypes = ['all', 'incoming', 'outgoing'];

if ($typeFilter === '') {
    $typeFilter = 'all';
}

if (!in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = 'all';
}

// only show this users transactions
$userTransactions = array_filter($transactions, function ($t) use ($userId) {
    return isset($t['userId']) && (int)$t['userId'] === (int)$userId;
});

// apply search, type and category filters
$filteredTransactions = array_filter($userTransactions, function ($t) use ($search, $typeFilter, $catFilter) {
    $matchesSearch = true;
    $matchesType = true;
    $matchesCat = true;

    if ($search !== '') {
        $s = strtolower($search);
        $description = strtolower((string)($t['description'] ?? ''));
        $category = strtolower((string)($t['category'] ?? ''));
        $amount = strtolower((string)($t['amount'] ?? ''));

        $matchesSearch =
            strpos($description, $s) !== false ||
            strpos($category, $s) !== false ||
            strpos($amount, $s) !== false;
    }

    if ($typeFilter !== 'all') {
        $matchesType = (($t['type'] ?? '') === $typeFilter);
    }

    if ($catFilter !== '' && $catFilter !== 'all') {
        $matchesCat = (($t['category'] ?? '') === $catFilter);
    }

    return $matchesSearch && $matchesType && $matchesCat;
});

// newest first feels better for a transaction list
usort($filteredTransactions, function ($a, $b) {
    return strtotime($b['date'] ?? '') <=> strtotime($a['date'] ?? '');
});

// these are just for the summary cards at the top
$currentMonth = date('Y-m');
$monthlyIncome = 0;
$monthlyExpenses = 0;

foreach ($userTransactions as $t) {
    if (!isset($t['date'], $t['amount'], $t['type'])) {
        continue;
    }

    if (strpos((string)$t['date'], $currentMonth) !== 0) {
        continue;
    }

    if (($t['type'] ?? '') === 'incoming') {
        $monthlyIncome += (float)$t['amount'];
    }

    if (($t['type'] ?? '') === 'outgoing') {
        $monthlyExpenses += (float)$t['amount'];
    }
}

$netSavings = $monthlyIncome - $monthlyExpenses;

// builds the category dropdown from the users actual data
$allCategories = array_unique(array_filter(array_column(array_values($userTransactions), 'category')));
sort($allCategories);

// if export=csv is in the url, download the filtered results instead of showing the page
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="transactions.csv"');

    $output = fopen('php://output', 'w');

    fputcsv($output, ['Date', 'Description', 'Category', 'Amount', 'Currency', 'Type']);

    foreach ($filteredTransactions as $t) {
        fputcsv($output, array_map('sanitiseCsvCell', [
            $t['date'] ?? '',
            $t['description'] ?? '',
            $t['category'] ?? '',
            $t['amount'] ?? '',
            $t['currency'] ?? $userCurrency,
            $t['type'] ?? ''
        ]));
    }

    fclose($output);
    exit();
}

// only show one page of results at a time
$pagination = paginate($filteredTransactions, (int)($_GET['page'] ?? 1), 20);
$pageTransactions = $pagination['items'];
$paginationBase = 'transactions.php?' . http_build_query(array_filter(['search' => $search, 'type' => $typeFilter, 'category' => $catFilter], 'strlen'));
$paginationNoun = 'transactions';

$pageTitle = 'Transactions';
$currentPage = 'transactions';
?>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php endif; ?>

<main class="dashboard-main" id="main-content">
    <!-- page header -->
    <header class="dashboard-header">
        <div>
            <h2>Transactions</h2>
            <p>Search, filter and export everything you've logged.</p>
        </div>
    </header>

    <!-- summary cards -->
    <section class="summary-cards" aria-label="Transaction summary">
        <div class="summary-card stat-card">
            <div class="stat-card-top">
                <h3>Monthly Income</h3>
                <div class="stat-icon stat-icon-income"><i class="fas fa-arrow-down" aria-hidden="true"></i></div>
            </div>
            <p class="amount-positive"><?php echo sanitiseOutput(formatMoney($monthlyIncome, $userCurrency)); ?></p>
            <span>Incoming this month</span>
        </div>

        <div class="summary-card stat-card">
            <div class="stat-card-top">
                <h3>Monthly Expenses</h3>
                <div class="stat-icon stat-icon-expense"><i class="fas fa-arrow-up" aria-hidden="true"></i></div>
            </div>
            <p class="amount-negative"><?php echo sanitiseOutput(formatMoney($monthlyExpenses, $userCurrency)); ?></p>
            <span>Outgoing this month</span>
        </div>

        <div class="summary-card stat-card">
            <div class="stat-card-top">
                <h3>Net Savings</h3>
                <div class="stat-icon stat-icon-net"><i class="fas fa-piggy-bank" aria-hidden="true"></i></div>
            </div>
            <p class="<?php echo $netSavings >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                <?php echo sanitiseOutput(formatMoney($netSavings, $userCurrency, true)); ?>
            </p>
            <span><?php echo $netSavings >= 0 ? 'Surplus this month' : 'Deficit this month'; ?></span>
        </div>
    </section>

    <!-- main transactions panel -->
    <section class="panel" id="transactions-panel" data-paginated aria-labelledby="transactions-heading">
        <!-- top row with title and quick actions -->
        <div class="panel-header tx-top-bar">
            <div>
                <h3 id="transactions-heading">All Transactions</h3>
                <p>Newest first</p>
            </div>
            <div class="tx-top-actions">
                <a href="transactions.php?export=csv&amp;search=<?php echo urlencode($search); ?>&amp;type=<?php echo urlencode($typeFilter); ?>&amp;category=<?php echo urlencode($catFilter); ?>" class="btn-secondary-sm"><i class="fas fa-download" aria-hidden="true"></i> Export CSV</a>
                <a href="add-transaction.php" class="btn-primary-sm ajax-link"><i class="fas fa-plus" aria-hidden="true"></i> Add Transaction</a>
            </div>
        </div>

        <!-- search and filter bar -->
        <form method="get" action="transactions.php" class="tx-toolbar ajax-form">
            <div class="tx-search-wrap">
                <span class="tx-search-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.8" />
                        <path d="M13.5 13.5L17 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </span>
                <label for="search" class="visually-hidden">Search transactions</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    class="tx-search-input"
                    placeholder="Search by description, category or amount"
                    value="<?php echo sanitiseOutput($search); ?>">
            </div>

            <div class="tx-filter-row">
                <label for="type" class="visually-hidden">Filter by type</label>
                <select name="type" id="type" class="tx-select">
                    <option value="all" <?php echo ($typeFilter === 'all') ? 'selected' : ''; ?>>All types</option>
                    <option value="incoming" <?php echo ($typeFilter === 'incoming') ? 'selected' : ''; ?>>Incoming</option>
                    <option value="outgoing" <?php echo ($typeFilter === 'outgoing') ? 'selected' : ''; ?>>Outgoing</option>
                </select>

                <label for="category" class="visually-hidden">Filter by category</label>
                <select name="category" id="category" class="tx-select">
                    <option value="all" <?php echo ($catFilter === 'all' || $catFilter === '') ? 'selected' : ''; ?>>All categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo sanitiseOutput($cat); ?>" <?php echo ($catFilter === $cat) ? 'selected' : ''; ?>>
                            <?php echo sanitiseOutput($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-primary-sm">Apply</button>

                <?php if ($search !== '' || $typeFilter !== 'all' || ($catFilter !== '' && $catFilter !== 'all')): ?>
                    <a href="transactions.php" class="btn-secondary-sm ajax-link">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($filteredTransactions)): ?>
            <!-- Desktop table -->
            <div class="table-wrapper tx-desktop-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Description</th>
                            <th scope="col">Category</th>
                            <th scope="col">Type</th>
                            <th scope="col" class="th-amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageTransactions as $t): ?>
                            <?php
                            $isIncoming = (($t['type'] ?? '') === 'incoming');
                            $amount = (float)($t['amount'] ?? 0);
                            $cat = (string)($t['category'] ?? '');
                            $transactionCurrency = (string)($t['currency'] ?? $userCurrency);
                            ?>
                            <tr>
                                <td class="td-date">
                                    <?php echo sanitiseOutput(formatDisplayDate((string)($t['date'] ?? ''))); ?>
                                </td>
                                <td class="td-desc">
                                    <?php echo sanitiseOutput((string)($t['description'] ?? '')); ?>
                                </td>
                                <td>
                                    <?php if ($cat !== ''): ?>
                                        <span class="badge <?php echo categoryBadgeClass($cat); ?>">
                                            <?php echo sanitiseOutput($cat); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-default">Uncategorised</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-pill <?php echo $isIncoming ? 'type-in' : 'type-out'; ?>">
                                        <?php echo $isIncoming ? 'Incoming' : 'Outgoing'; ?>
                                    </span>
                                </td>
                                <td class="td-amount <?php echo $isIncoming ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo sanitiseOutput(formatMoney($isIncoming ? $amount : -$amount, $transactionCurrency, true)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards grouped by date -->
            <div class="mobile-tx-list" aria-label="Transactions list">
                <?php
                $grouped = [];
                foreach ($pageTransactions as $t) {
                    $grouped[formatDisplayDate((string)($t['date'] ?? ''))][] = $t;
                }
                foreach ($grouped as $dateLabel => $dayTxs):
                ?>
                <div class="tx-date-group">
                    <div class="tx-date-label"><?php echo sanitiseOutput($dateLabel); ?></div>
                    <?php foreach ($dayTxs as $t):
                        $isIncoming = (($t['type'] ?? '') === 'incoming');
                        $amount     = (float)($t['amount'] ?? 0);
                        $cat        = (string)($t['category'] ?? 'Uncategorised');
                        if ($cat === '') $cat = 'Uncategorised';
                        $txCurrency = (string)($t['currency'] ?? $userCurrency);
                    ?>
                    <div class="tx-card">
                        <div class="tx-card-indicator <?php echo $isIncoming ? 'incoming' : 'outgoing'; ?>">
                            <i class="fas <?php echo $isIncoming ? 'fa-arrow-down' : 'fa-arrow-up'; ?>" aria-hidden="true"></i>
                        </div>
                        <div class="tx-card-body">
                            <div class="tx-card-desc"><?php echo sanitiseOutput((string)($t['description'] ?? '')); ?></div>
                            <div class="tx-card-meta">
                                <span class="badge <?php echo categoryBadgeClass($cat); ?>"><?php echo sanitiseOutput($cat); ?></span>
                            </div>
                        </div>
                        <div class="tx-card-amount <?php echo $isIncoming ? 'amount-positive' : 'amount-negative'; ?>">
                            <?php echo sanitiseOutput(formatMoney($isIncoming ? $amount : -$amount, $txCurrency, true)); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php require __DIR__ . '/includes/pagination.php'; ?>
        <?php else: ?>
            <!-- empty state if no transactions match -->
            <div class="tx-empty">
                <p>No transactions found.</p>
                <p>Try changing your search or filters, or add a new transaction to get started.</p>
                <?php if ($search !== '' || $typeFilter !== 'all' || ($catFilter !== '' && $catFilter !== 'all')): ?>
                    <a href="transactions.php" class="btn-secondary-sm ajax-link">Clear filters</a> <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
