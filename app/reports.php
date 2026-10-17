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
$chartFilter = trim($_GET['chart_filter'] ?? '30days');

$errors = [];

$allowedTypes = ['all', 'incoming', 'outgoing'];

// stops invalid type values from being used in the filters
if (!in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = 'all';
}

$allowedChartFilters = ['7days', '30days', 'month', 'year'];

// same idea here for the chart range dropdown
if (!in_array($chartFilter, $allowedChartFilters, true)) {
    $chartFilter = '30days';
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

// only pull this users transactions for the report
$userTransactions = array_filter($transactions, function ($transaction) use ($userId) {
    return isset($transaction['userId']) && (int)$transaction['userId'] === (int)$userId;
});

// makes sure the category dropdown includes both saved categories and ones already used in transactions
$categoryNamesFromFile = [];
foreach ($categories as $category) {
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

$chartTransactions = $filteredTransactions;
$today = date('Y-m-d');

// this one is just for the chart range, separate from the main filters above
$chartTransactions = array_filter($chartTransactions, function ($transaction) use ($chartFilter, $today) {
    $transactionDate = trim((string)($transaction['date'] ?? ''));
    if ($transactionDate === '') {
        return false;
    }

    $timestamp = strtotime($transactionDate);
    if ($timestamp === false) {
        return false;
    }

    switch ($chartFilter) {
        case '7days':
            return $transactionDate >= date('Y-m-d', strtotime('-6 days', strtotime($today)));

        case '30days':
            return $transactionDate >= date('Y-m-d', strtotime('-29 days', strtotime($today)));

        case 'month':
            return date('Y-m', $timestamp) === date('Y-m', strtotime($today));

        case 'year':
            return date('Y', $timestamp) === date('Y', strtotime($today));

        default:
            return true;
    }
});

$chartIncome = 0;
$chartExpenses = 0;
$chartCategoryTotals = [];

// totals for the two charts
foreach ($chartTransactions as $transaction) {
    $amount = (float)($transaction['amount'] ?? 0);
    $type = trim((string)($transaction['type'] ?? ''));

    if ($type === 'incoming') {
        $chartIncome += $amount;
    } elseif ($type === 'outgoing') {
        $chartExpenses += $amount;

        $categoryName = trim((string)($transaction['category'] ?? 'Unknown'));
        if (!isset($chartCategoryTotals[$categoryName])) {
            $chartCategoryTotals[$categoryName] = 0;
        }

        $chartCategoryTotals[$categoryName] += $amount;
    }
}

arsort($chartCategoryTotals);

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
        fputcsv($output, [
            $transaction['date'] ?? '',
            $transaction['description'] ?? '',
            $transaction['category'] ?? '',
            $transaction['amount'] ?? '',
            $transaction['currency'] ?? $userCurrency,
            $transaction['type'] ?? ''
        ]);
    }

    fclose($output);
    exit();
}

// makes the report table dates easier to read
function formatReportDate(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('d M Y', $timestamp);
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

        <!-- mobile hero card -->
        <section class="mobile-reports-hero" aria-hidden="true">
            <div class="mobile-reports-hero-label">Total Spending</div>
            <div class="mobile-reports-hero-amount">
                <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($totalExpenses, 2); ?>
            </div>
            <div class="mobile-reports-hero-sub">Based on your current filters</div>
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
                    <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($totalIncome, 2); ?>
                </p>
                <span>Filtered results</span>
            </div>

            <div class="summary-card">
                <div class="stat-card-top">
                    <h3>Total Expenses</h3>
                    <div class="stat-icon stat-icon-expense">
                        <i class="fas fa-arrow-up" aria-hidden="true"></i>
                    </div>
                </div>
                <p class="amount-negative">
                    <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($totalExpenses, 2); ?>
                </p>
                <span>Filtered results</span>
            </div>

            <div class="summary-card">
                <div class="stat-card-top">
                    <h3>Net Total</h3>
                    <div class="stat-icon stat-icon-net">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                    </div>
                </div>
                <p class="<?php echo $netTotal >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                    <?php echo ($netTotal >= 0 ? '+' : '-'); ?>
                    <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format(abs($netTotal), 2); ?>
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
                        <p>How income and spending compare right now</p>
                    </div>

                    <form method="get" action="reports.php" class="chart-filter-form">
                        <input type="hidden" name="type" value="<?php echo sanitiseOutput($typeFilter); ?>">
                        <input type="hidden" name="category" value="<?php echo sanitiseOutput($categoryFilter); ?>">
                        <input type="hidden" name="start_date" value="<?php echo sanitiseOutput($startDate); ?>">
                        <input type="hidden" name="end_date" value="<?php echo sanitiseOutput($endDate); ?>">

                        <select name="chart_filter" class="chart-filter-select" onchange="this.form.submit()">
                            <option value="7days" <?php echo $chartFilter === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30days" <?php echo $chartFilter === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="month" <?php echo $chartFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="year" <?php echo $chartFilter === 'year' ? 'selected' : ''; ?>>This Year</option>
                        </select>
                    </form>
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
                    <p>No income or expense data available for the selected chart period.</p>
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
                    <p>No expense data available for the selected chart period.</p>
                <?php endif; ?>

                <?php if (!empty($chartCategoryTotals)): ?>
                    <div class="report-category-stack">
                        <?php
                        $displayedCategories = 0;
                        $colorIdx = 0;
                        foreach ($chartCategoryTotals as $categoryName => $total):
                            if ($displayedCategories >= 4) {
                                break;
                            }
                            $displayedCategories++;
                            $dotColorIndex = $colorIdx % 8;
                            $colorIdx++;
                            $pct = $chartExpenses > 0 ? ($total / $chartExpenses * 100) : 0;
                        ?>
                            <div class="report-category-row">
                                <span class="report-cat-label">
                                    <span class="report-cat-dot report-cat-dot-<?php echo $dotColorIndex; ?>"></span>
                                    <?php echo sanitiseOutput($categoryName); ?> (<?php echo number_format($pct, 1); ?>%)
                                </span>
                                <strong><?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($total, 2); ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <div class="report-category-row report-category-total">
                            <span>Total</span>
                            <strong class="report-total-value"><?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($chartExpenses, 2); ?></strong>
                        </div>
                    </div>

                    <div class="mobile-report-category-cards">
                        <?php
                        $displayedCategories = 0;
                        foreach ($chartCategoryTotals as $categoryName => $total):
                            if ($displayedCategories >= 4) {
                                break;
                            }
                            $displayedCategories++;
                        ?>
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
                                    <?php echo sanitiseOutput($userCurrency); ?> <?php echo number_format($total, 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>

        <!-- detailed transactions table -->
        <section class="panel report-table-panel">
            <div class="panel-header">
                <div>
                    <h3>Detailed Spending Breakdown</h3>
                    <p><?php echo count($filteredTransactions); ?> result(s)</p>
                </div>

                <a
                    href="reports.php?export=csv&amp;type=<?php echo urlencode($typeFilter); ?>&amp;category=<?php echo urlencode($categoryFilter); ?>&amp;start_date=<?php echo urlencode($startDate); ?>&amp;end_date=<?php echo urlencode($endDate); ?>&amp;chart_filter=<?php echo urlencode($chartFilter); ?>"
                    class="btn-primary-sm">
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
                                <th scope="col">Amount</th>
                                <th scope="col">Currency</th>
                                <th scope="col">Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredTransactions as $transaction): ?>
                                <?php
                                $transactionCurrency = (string)($transaction['currency'] ?? $userCurrency);
                                $isIncoming = (($transaction['type'] ?? '') === 'incoming');
                                ?>
                                <tr>
                                    <td class="td-date"><?php echo sanitiseOutput(formatReportDate((string)($transaction['date'] ?? ''))); ?></td>
                                    <td class="td-desc"><?php echo sanitiseOutput((string)($transaction['description'] ?? '')); ?></td>
                                    <td><?php echo sanitiseOutput((string)($transaction['category'] ?? '')); ?></td>
                                    <td class="<?php echo $isIncoming ? 'amount-positive' : 'amount-negative'; ?>">
                                        <?php echo $isIncoming ? '+' : '-'; ?>
                                        <?php echo sanitiseOutput($transactionCurrency); ?> <?php echo number_format((float)($transaction['amount'] ?? 0), 2); ?>
                                    </td>
                                    <td><?php echo sanitiseOutput($transactionCurrency); ?></td>
                                    <td>
                                        <span class="type-pill <?php echo $isIncoming ? 'type-in' : 'type-out'; ?>">
                                            <?php echo sanitiseOutput(ucfirst((string)($transaction['type'] ?? ''))); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No transactions found for the selected filters.</p>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
