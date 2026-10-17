<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

initialiseDataFiles();

$transactionsFile = __DIR__ . '/../data/transactions.json';
$categoriesFile = __DIR__ . '/../data/categories.json';
$currenciesFile = __DIR__ . '/../data/currencies.json';
$usersFile = __DIR__ . '/../data/users.json';

$transactions = loadJsonData($transactionsFile);
$categories = loadJsonData($categoriesFile);
$currencies = loadJsonData($currenciesFile);
$users = loadJsonData($usersFile);

$date = '';
$description = '';
$category = '';
$amount = '';
$currency = '';
$type = '';

$errors = [];
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';

// start with the users default currency so the form feels quicker to fill in
foreach ($users as $user) {
    if (isset($user['userId']) && (int)$user['userId'] === (int)$userId) {
        $currency = $user['defaultCurrency'] ?? '';
        break;
    }
}

// save the form if they submit it
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $currency = trim($_POST['currency'] ?? '');
    $type = trim($_POST['type'] ?? '');

    if ($date === '') {
        $errors['date'] = 'Please enter a date.';
    }

    if ($description === '') {
        $errors['description'] = 'Please enter a description.';
    }

    if ($category === '') {
        $errors['category'] = 'Please select a category.';
    }

    if ($amount === '') {
        $errors['amount'] = 'Please enter an amount.';
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $errors['amount'] = 'Amount must be a positive number.';
    }

    if ($currency === '') {
        $errors['currency'] = 'Please select a currency.';
    }

    if ($type === '') {
        $errors['type'] = 'Please select a transaction type.';
    } elseif ($type !== 'incoming' && $type !== 'outgoing') {
        $errors['type'] = 'Invalid transaction type selected.';
    }

    if (empty($errors)) {
        $newTransaction = [
            'transactionId' => getNextId($transactions, 'transactionId'),
            'userId' => (int)$userId,
            'date' => $date,
            'description' => $description,
            'category' => $category,
            'amount' => (float)$amount,
            'currency' => $currency,
            'type' => $type
        ];

        $transactions[] = $newTransaction;
        saveJsonData($transactionsFile, $transactions);

        header('Location: transactions.php');
        exit();
    }
}

$pageTitle = 'Add Transaction';
$currentPage = 'transactions';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="dashboard-main" id="main-content">
    <!-- page header -->
    <header class="dashboard-header dashboard-header-tools">
        <div>
            <h2>Add Transaction</h2>
            <p>Add a new transaction, <?php echo sanitiseOutput($userName); ?>.</p>
        </div>

        <div class="dashboard-header-actions">
            <button type="button" class="theme-toggle-btn" id="themeToggle" aria-label="Toggle light and dark mode">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </header>

    <!-- main transaction form card -->
    <section class="panel transaction-form-panel transaction-form-modern">
        <div class="form-intro">
            <div class="form-intro-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div>
                <h3>New Transaction</h3>
                <p>Add your income or spending here.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-summary" role="alert">
                <h3>Please fix the following:</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitiseOutput($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- add transaction form -->
        <form action="add-transaction.php" method="post" novalidate>
            <!-- big amount field at the top -->
            <div class="amount-highlight-group">
                <label for="amount">Amount *</label>
                <div class="amount-input-wrap">
                    <span class="amount-currency-symbol">£</span>
                    <input
                        type="number"
                        step="0.01"
                        id="amount"
                        name="amount"
                        value="<?php echo sanitiseOutput($amount); ?>"
                        aria-required="true"
                        placeholder="0.00"
                        class="amount-highlight-input <?php echo isset($errors['amount']) ? 'input-error' : ''; ?>">
                </div>
                <?php if (isset($errors['amount'])): ?>
                    <p class="error" role="alert"><?php echo sanitiseOutput($errors['amount']); ?></p>
                <?php endif; ?>
            </div>

            <!-- date and currency row -->
            <div class="form-row">
                <div class="form-group">
                    <label for="date"><i class="fas fa-calendar-alt"></i> Date *</label>
                    <input
                        type="date"
                        id="date"
                        name="date"
                        value="<?php echo sanitiseOutput($date); ?>"
                        aria-required="true"
                        class="<?php echo isset($errors['date']) ? 'input-error' : ''; ?>">
                    <?php if (isset($errors['date'])): ?>
                        <p class="error" role="alert"><?php echo sanitiseOutput($errors['date']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="currency"><i class="fas fa-coins"></i> Currency *</label>
                    <select
                        id="currency"
                        name="currency"
                        aria-required="true"
                        class="<?php echo isset($errors['currency']) ? 'input-error' : ''; ?>">
                        <option value="">-- Select Currency --</option>
                        <?php foreach ($currencies as $currencyItem): ?>
                            <option
                                value="<?php echo sanitiseOutput($currencyItem['code']); ?>"
                                <?php echo ($currency === $currencyItem['code']) ? 'selected' : ''; ?>>
                                <?php echo sanitiseOutput($currencyItem['code'] . ' - ' . ($currencyItem['name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['currency'])): ?>
                        <p class="error" role="alert"><?php echo sanitiseOutput($errors['currency']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- description field -->
            <div class="form-group">
                <label for="description"><i class="fas fa-pen"></i> Description *</label>
                <input
                    type="text"
                    id="description"
                    name="description"
                    value="<?php echo sanitiseOutput($description); ?>"
                    aria-required="true"
                    placeholder="What was this transaction for?"
                    class="<?php echo isset($errors['description']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['description'])): ?>
                    <p class="error" role="alert"><?php echo sanitiseOutput($errors['description']); ?></p>
                <?php endif; ?>
            </div>

            <!-- category dropdown -->
            <div class="form-group">
                <label for="category"><i class="fas fa-tags"></i> Category *</label>
                <select
                    id="category"
                    name="category"
                    aria-required="true"
                    class="<?php echo isset($errors['category']) ? 'input-error' : ''; ?>">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $categoryItem): ?>
                        <option
                            value="<?php echo sanitiseOutput($categoryItem['name']); ?>"
                            <?php echo ($category === $categoryItem['name']) ? 'selected' : ''; ?>>
                            <?php echo sanitiseOutput($categoryItem['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?>
                    <p class="error" role="alert"><?php echo sanitiseOutput($errors['category']); ?></p>
                <?php endif; ?>
            </div>

            <!-- incoming / outgoing selector -->
            <fieldset class="form-group type-selector-group">
                <legend>Transaction Type *</legend>

                <div class="type-card-grid">
                    <label class="type-card incoming-card">
                        <input class="type-radio" type="radio" name="type" value="incoming" <?php echo ($type === 'incoming') ? 'checked' : ''; ?>>
                        <span class="type-card-icon"><i class="fas fa-arrow-trend-up"></i></span>
                        <span class="type-card-text">
                            <strong>Incoming</strong>
                            <small>Money coming in</small>
                        </span>
                    </label>

                    <label class="type-card outgoing-card">
                        <input class="type-radio" type="radio" name="type" value="outgoing" <?php echo ($type === 'outgoing') ? 'checked' : ''; ?>>
                        <span class="type-card-icon"><i class="fas fa-arrow-trend-down"></i></span>
                        <span class="type-card-text">
                            <strong>Outgoing</strong>
                            <small>Money going out</small>
                        </span>
                    </label>
                </div>

                <?php if (isset($errors['type'])): ?>
                    <p class="error" role="alert"><?php echo sanitiseOutput($errors['type']); ?></p>
                <?php endif; ?>
            </fieldset>

            <!-- save and cancel buttons -->
            <div class="form-actions modern-actions">
                <button type="submit" class="btn-primary btn-large">
                    <i class="fas fa-save"></i> Save Transaction
                </button>

                <a href="transactions.php" class="btn-secondary btn-large">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
            </div>
        </form>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
