<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

initialiseDataFiles();

$transactionsFile = __DIR__ . '/../data/transactions.json';
$categoriesFile = __DIR__ . '/../data/categories.json';
$usersFile = __DIR__ . '/../data/users.json';

$transactions = loadJsonData($transactionsFile);
$categories = loadJsonData($categoriesFile);
$users = loadJsonData($usersFile);

$userId = $_SESSION['user_id'] ?? null;
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// default to gbp unless the user has changed it in their account
$userCurrency = 'GBP';
foreach ($users as $user) {
    if (isset($user['userId']) && (int)$user['userId'] === (int)$userId) {
        if (!empty($user['defaultCurrency'])) {
            $userCurrency = (string)$user['defaultCurrency'];
        }
        break;
    }
}

$typeFilter = trim($_GET['type'] ?? 'all');
$categoryFilter = trim($_GET['category'] ?? 'all');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$period = trim($_GET['period'] ?? '');

// one period drives the whole page - cards, charts and table all match
$periodOptions = [
    '7days' => 'Last 7 days',
    '30days' => 'Last 30 days',
    'month' => 'This month',
    'year' => 'This year',
    'all' => 'All time',
    'custom' => 'Custom range',
];

if (!array_key_exists($period, $periodOptions)) {
    $period = ($startDate !== '' || $endDate !== '') ? 'custom' : '30days';
}

$errors = [];

$allowedTypes = ['all', 'incoming', 'outgoing'];

// stops invalid type values from being used in the filters
if (!in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = 'all';
}

if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $errors['start_date'] = 'Please enter a valid start date.';
    $startDate = '';
}

if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $errors['end_date'] = 'Please enter a valid end date.';
    $endDate = '';
}

if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    $errors['date_range'] = 'Start date must be before end date.';
}

// the preset periods fill the date range in for you
$today = date('Y-m-d');
$presetRanges = [
    '7days' => [date('Y-m-d', strtotime('-6 days')), $today],
    '30days' => [date('Y-m-d', strtotime('-29 days')), $today],
    'month' => [date('Y-m-01'), $today],
    'year' => [date('Y-01-01'), $today],
    'all' => ['', ''],
];

if ($period !== 'custom') {
    [$startDate, $endDate] = $presetRanges[$period];
}

// only pull this users transactions for the report
$userTransactions = array_filter($transactions, function ($transaction) use ($userId) {
    return isset($transaction['userId']) && (int)$transaction['userId'] === (int)$userId;
});

// makes sure the category dropdown includes both saved categories and ones already used in transactions
$categoryNamesFromFile = [];
foreach (getCategoriesForUser($categories, $userId) as $category) {
    if (!empty($category['name'])) {
        $categoryNamesFromFile[] = (string)$category['name'];
    }
}

$categoryNamesFromTransactions = array_column(array_values($userTransactions), 'category');
$allCategories = array_unique(array_filter(array_merge($categoryNamesFromFile, $categoryNamesFromTransactions)));
sort($allCategories);

if ($categoryFilter !== 'all' && $categoryFilter !== '' && !in_array($categoryFilter, $allCategories, true)) {
    $categoryFilter = 'all';
}

// only apply the filters if the date inputs are valid
if (empty($errors)) {
    $filteredTransactions = array_filter($userTransactions, function ($transaction) use ($typeFilter, $categoryFilter, $startDate, $endDate) {
        $transactionType = trim((string)($transaction['type'] ?? ''));
        $transactionCategory = trim((string)($transaction['category'] ?? ''));
        $transactionDate = trim((string)($transaction['date'] ?? ''));

        $matchesType = true;
        $matchesCategory = true;
        $matchesStart = true;
        $matchesEnd = true;

        if ($typeFilter !== 'all') {
            $matchesType = ($transactionType === $typeFilter);
        }

        if ($categoryFilter !== 'all') {
            $matchesCategory = ($transactionCategory === $categoryFilter);
        }

        if ($startDate !== '') {
            $matchesStart = ($transactionDate >= $startDate);
        }

        if ($endDate !== '') {
            $matchesEnd = ($transactionDate <= $endDate);
        }

        return $matchesType && $matchesCategory && $matchesStart && $matchesEnd;
    });
} else {
    $filteredTransactions = [];
}

// newest first in the report table as well
usort($filteredTransactions, function ($a, $b) {
    return strtotime($b['date'] ?? '') <=> strtotime($a['date'] ?? '');
});

$totalIncome = 0;
$totalExpenses = 0;
$categoryTotals = [];

// works out the totals for the top cards
foreach ($filteredTransactions as $transaction) {
    $amount = (float)($transaction['amount'] ?? 0);
    $type = trim((string)($transaction['type'] ?? ''));

    if ($type === 'incoming') {
        $totalIncome += $amount;
    } elseif ($type === 'outgoing') {
        $totalExpenses += $amount;

        $categoryName = trim((string)($transaction['category'] ?? 'Unknown'));
        if (!isset($categoryTotals[$categoryName])) {
            $categoryTotals[$categoryName] = 0;
        }

        $categoryTotals[$categoryName] += $amount;
    }
}

arsort($categoryTotals);

$netTotal = $totalIncome - $totalExpenses;

// the charts show the same filtered data as the cards and table
$chartIncome = $totalIncome;
$chartExpenses = $totalExpenses;
$chartCategoryTotals = $categoryTotals;

$expenseChartLabels = array_values(array_keys($chartCategoryTotals));
$expenseChartValues = array_values($chartCategoryTotals);

$comparisonChartLabels = ['Income', 'Expenses'];
$comparisonChartValues = [$chartIncome, $chartExpenses];

// if export=csv is passed in, download the filtered report instead
if (isset($_GET['export']) && $_GET['export'] === 'csv' && empty($errors)) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="report.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Description', 'Category', 'Amount', 'Currency', 'Type']);

    foreach ($filteredTransactions as $transaction) {
        fputcsv($output, array_map('sanitiseCsvCell', [
            $transaction['date'] ?? '',
            $transaction['description'] ?? '',
            $transaction['category'] ?? '',
            $transaction['amount'] ?? '',
            $transaction['currency'] ?? $userCurrency,
            $transaction['type'] ?? ''
        ]));
    }

    fclose($output);
    exit();
}

// text version of the chart data for screen readers
$expenseSummaryParts = [];
for ($i = 0; $i < count($expenseChartLabels); $i++) {
    $label = $expenseChartLabels[$i];
    $value = $expenseChartValues[$i] ?? 0;
    $expenseSummaryParts[] = $label . ': ' . $userCurrency . ' ' . number_format((float)$value, 2);
}
$expenseSummaryText = implode(', ', $expenseSummaryParts);

$comparisonSummaryText =
    'Income: ' . $userCurrency . ' ' . number_format($chartIncome, 2) .
    ', Expenses: ' . $userCurrency . ' ' . number_format($chartExpenses, 2);

// the current filters, so export and page links keep them
$reportQuery = http_build_query(array_filter([
    'period' => $period,
    'start_date' => $period === 'custom' ? $startDate : '',
    'end_date' => $period === 'custom' ? $endDate : '',
    'type' => $typeFilter,
    'category' => $categoryFilter,
], 'strlen'));

if ($period !== 'custom') {
    $periodLabel = $periodOptions[$period];
} elseif ($startDate === '' && $endDate === '') {
    $periodLabel = 'All dates';
} else {
    $periodLabel = ($startDate !== '' ? formatDisplayDate($startDate) : 'Start') . ' – ' . ($endDate !== '' ? formatDisplayDate($endDate) : 'today');
}

$pagination = paginate($filteredTransactions, (int)($_GET['page'] ?? 1), 15);
$paginationBase = 'reports.php?' . $reportQuery;
$paginationNoun = 'transactions';

// the list next to the doughnut shows the top 5 and lumps the rest into "Other"
$categoryListTotals = array_slice($chartCategoryTotals, 0, 5, true);
$otherCategoriesTotal = array_sum(array_slice($chartCategoryTotals, 5));
if ($otherCategoriesTotal > 0) {
    $categoryListTotals['Other'] = $otherCategoriesTotal;
}

// the doughnut uses the same grouping so its slices match the list
$expenseChartLabels = array_keys($categoryListTotals);
$expenseChartValues = array_values($categoryListTotals);

$pageTitle = 'Reports';
$currentPage = 'reports';
?>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php endif; ?>

<main class="dashboard-main reports-main" id="main-content">
    <!-- page header -->
    <header class="dashboard-header reports-header">
        <div>
            <h2>Reports</h2>
            <p>Track income, spending, and trends over time.</p>
        </div>
    </header>

    <div class="reports-content">

        <!-- one set of filters for the whole report -->
        <form method="get" action="reports.php" class="tx-toolbar report-filters ajax-form" aria-label="Report filters">
            <div class="report-filter-field">
                <label for="report-period">Period</label>
                <select name="period" id="report-period" class="tx-select">
                    <?php foreach ($periodOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $period === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="report-filter-field report-date-field" <?php echo $period === 'custom' ? '' : 'hidden'; ?>>
                <label for="report-start">From</label>
                <input type="date" name="start_date" id="report-start" class="tx-select" value="<?php echo sanitiseOutput($period === 'custom' ? $startDate : ''); ?>">
            </div>

            <div class="report-filter-field report-date-field" <?php echo $period === 'custom' ? '' : 'hidden'; ?>>
                <label for="report-end">To</label>
                <input type="date" name="end_date" id="report-end" class="tx-select" value="<?php echo sanitiseOutput($period === 'custom' ? $endDate : ''); ?>">
            </div>

            <div class="report-filter-field">
                <label for="report-type">Type</label>
                <select name="type" id="report-type" class="tx-select">
                    <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All types</option>
                    <option value="incoming" <?php echo $typeFilter === 'incoming' ? 'selected' : ''; ?>>Incoming</option>
                    <option value="outgoing" <?php echo $typeFilter === 'outgoing' ? 'selected' : ''; ?>>Outgoing</option>
                </select>
            </div>

            <div class="report-filter-field">
                <label for="report-category">Category</label>
                <select name="category" id="report-category" class="tx-select">
                    <option value="all">All categories</option>
                    <?php foreach ($allCategories as $categoryOption): ?>
                        <option value="<?php echo sanitiseOutput($categoryOption); ?>" <?php echo $categoryFilter === $categoryOption ? 'selected' : ''; ?>>
                            <?php echo sanitiseOutput($categoryOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="report-filter-actions">
                <button type="submit" class="btn-primary-sm">Apply</button>
                <?php if ($period !== '30days' || $typeFilter !== 'all' || $categoryFilter !== 'all'): ?>
                    <a href="reports.php" class="btn-secondary-sm ajax-link">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($errors)): ?>
            <div class="error-summary" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitiseOutput($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- mobile hero card -->
        <section class="mobile-reports-hero" aria-hidden="true">
            <div class="mobile-reports-hero-label">Total Spending</div>
            <div class="mobile-reports-hero-amount">
                <?php echo sanitiseOutput(formatMoney($totalExpenses, $userCurrency)); ?>
            </div>
            <div class="mobile-reports-hero-sub"><?php echo sanitiseOutput($periodLabel); ?></div>
        </section>

        <!-- top report summary cards -->
        <section class="summary-cards reports-summary-cards" aria-label="Report summary">
            <div class="summary-card">
                <div class="stat-card-top">
                    <h3>Total Income</h3>
                    <div class="stat-icon stat-icon-income">
                        <i class="fas fa-arrow-down" aria-hidden="true"></i>
                    </div>
                </div>
                <p class="amount-positive">
                    <?php echo sanitiseOutput(formatMoney($totalIncome, $userCurrency)); ?>
                </p>
                <span><?php echo sanitiseOutput($periodLabel); ?></span>
            </div>

            <div class="summary-card">
                <div class="stat-card-top">
                    <h3>Total Expenses</h3>
                    <div class="stat-icon stat-icon-expense">
                        <i class="fas fa-arrow-up" aria-hidden="true"></i>
                    </div>
                </div>
                <p class="amount-negative">
                    <?php echo sanitiseOutput(formatMoney($totalExpenses, $userCurrency)); ?>
                </p>
                <span><?php echo sanitiseOutput($periodLabel); ?></span>
            </div>

            <div class="summary-card">
                <div class="stat-card-top">
                    <h3>Net Total</h3>
                    <div class="stat-icon stat-icon-net">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                    </div>
                </div>
                <p class="<?php echo $netTotal >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                    <?php echo sanitiseOutput(formatMoney($netTotal, $userCurrency, true)); ?>
                </p>
                <span>Income minus expenses</span>
            </div>

            <div class="summary-card">
                <div class="stat-card-top">
                    <h3>Transactions</h3>
                    <div class="stat-icon stat-icon-tx">
                        <i class="fas fa-receipt" aria-hidden="true"></i>
                    </div>
                </div>
                <p><?php echo count($filteredTransactions); ?></p>
                <span>Transactions in view</span>
            </div>
        </section>

        <!-- two main chart panels -->
        <section class="reports-main-grid">
            <!-- income vs expenses chart -->
            <section class="panel report-trend-panel">
                <div class="panel-header report-chart-header">
                    <div>
                        <h3>Income vs Expenses</h3>
                        <p><?php echo sanitiseOutput($periodLabel); ?></p>
                    </div>
                </div>

                <?php if ($chartIncome > 0 || $chartExpenses > 0): ?>
                    <div class="report-chart-container report-trend-chart">
                        <canvas
                            id="incomeExpenseChart"
                            aria-describedby="comparison-chart-summary"
                            data-labels='<?php echo json_encode($comparisonChartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'
                            data-values='<?php echo json_encode($comparisonChartValues, JSON_NUMERIC_CHECK); ?>'
                            data-currency="<?php echo sanitiseOutput($userCurrency); ?>"></canvas>
                    </div>
                    <p id="comparison-chart-summary" class="visually-hidden">
                        Income versus expenses chart. Values shown are <?php echo sanitiseOutput($comparisonSummaryText); ?>.
                    </p>
                <?php else: ?>
                    <p class="report-empty">No income or expenses in this period.</p>
                <?php endif; ?>
            </section>

            <!-- category doughnut chart -->
            <section class="panel report-breakdown-panel">
                <div class="panel-header">
                    <div>
                        <h3>Category Breakdown</h3>
                        <p>Where most of your spending is going</p>
                    </div>
                </div>

                <?php if (!empty($expenseChartLabels)): ?>
                    <div class="report-chart-container report-doughnut-chart">
                        <canvas
                            id="expenseDoughnutChart"
                            aria-describedby="expense-chart-summary"
                            data-labels='<?php echo json_encode($expenseChartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'
                            data-values='<?php echo json_encode($expenseChartValues, JSON_NUMERIC_CHECK); ?>'
                            data-currency="<?php echo sanitiseOutput($userCurrency); ?>"></canvas>
                    </div>
                    <p id="expense-chart-summary" class="visually-hidden">
                        Expense breakdown by category. Values shown are <?php echo sanitiseOutput($expenseSummaryText); ?>.
                    </p>
                <?php else: ?>
                    <p class="report-empty">No spending in this period.</p>
                <?php endif; ?>

                <?php if (!empty($chartCategoryTotals)): ?>
                    <div class="report-category-stack">
                        <?php $colorIdx = 0; ?>
                        <?php foreach ($categoryListTotals as $categoryName => $total): ?>
                            <?php
                            $dotClass = $categoryName === 'Other' ? 'report-cat-dot-other' : 'report-cat-dot-' . ($colorIdx++ % 8);
                            $pct = $chartExpenses > 0 ? ($total / $chartExpenses * 100) : 0;
                            ?>
                            <div class="report-category-row">
                                <span class="report-cat-label">
                                    <span class="report-cat-dot <?php echo $dotClass; ?>"></span>
                                    <?php echo sanitiseOutput($categoryName); ?> (<?php echo number_format($pct, 1); ?>%)
                                </span>
                                <strong><?php echo sanitiseOutput(formatMoney($total, $userCurrency)); ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <div class="report-category-row report-category-total">
                            <span>Total</span>
                            <strong class="report-total-value"><?php echo sanitiseOutput(formatMoney($chartExpenses, $userCurrency)); ?></strong>
                        </div>
                    </div>

                    <div class="mobile-report-category-cards">
                        <?php foreach ($categoryListTotals as $categoryName => $total): ?>
                            <div class="mobile-report-category-card">
                                <div class="mobile-report-category-left">
                                    <div class="mobile-report-category-icon">
                                        <i class="fas fa-tag" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <div class="mobile-report-category-name"><?php echo sanitiseOutput($categoryName); ?></div>
                                        <div class="mobile-report-category-sub">Expense category</div>
                                    </div>
                                </div>
                                <div class="mobile-report-category-amount">
                                    <?php echo sanitiseOutput(formatMoney($total, $userCurrency)); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>

        <!-- detailed transactions table -->
        <section class="panel report-table-panel" id="report-transactions" data-paginated>
            <div class="panel-header">
                <div>
                    <h3>Transactions in this report</h3>
                    <p><?php echo sanitiseOutput($periodLabel); ?></p>
                </div>

                <a
                    href="<?php echo sanitiseOutput('reports.php?export=csv&' . $reportQuery); ?>"
                    class="btn-secondary-sm">
                    <i class="fas fa-download" aria-hidden="true"></i> Export CSV
                </a>
            </div>

            <?php if (!empty($filteredTransactions)): ?>
                <div class="table-wrapper report-table-wrapper">
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
                            <?php foreach ($pagination['items'] as $transaction): ?>
                                <?php
                                $transactionCurrency = (string)($transaction['currency'] ?? $userCurrency);
                                $isIncoming = (($transaction['type'] ?? '') === 'incoming');
                                ?>
                                <tr>
                                    <td class="td-date"><?php echo sanitiseOutput(formatDisplayDate((string)($transaction['date'] ?? ''))); ?></td>
                                    <td class="td-desc"><?php echo sanitiseOutput((string)($transaction['description'] ?? '')); ?></td>
                                    <td>
                                        <span class="badge <?php echo categoryBadgeClass((string)($transaction['category'] ?? '')); ?>">
                                            <?php echo sanitiseOutput((string)($transaction['category'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="type-pill <?php echo $isIncoming ? 'type-in' : 'type-out'; ?>">
                                            <?php echo $isIncoming ? 'Incoming' : 'Outgoing'; ?>
                                        </span>
                                    </td>
                                    <td class="td-amount <?php echo $isIncoming ? 'amount-positive' : 'amount-negative'; ?>">
                                        <?php $amount = (float)($transaction['amount'] ?? 0); ?>
                                        <?php echo sanitiseOutput(formatMoney($isIncoming ? $amount : -$amount, $transactionCurrency, true)); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php require __DIR__ . '/includes/pagination.php'; ?>
            <?php else: ?>
                <p class="report-empty">No transactions match these filters.</p>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
