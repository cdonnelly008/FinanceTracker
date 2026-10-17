<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

initialiseDataFiles();

// reject any form post that doesn't carry this session's csrf token
verifyCsrfTokenOrFail();

$categoriesFile = __DIR__ . '/../data/categories.json';
$transactionsFile = __DIR__ . '/../data/transactions.json';

$categories = loadJsonData($categoriesFile);
$transactions = loadJsonData($transactionsFile);

$userId = $_SESSION['user_id'] ?? null;

$categoryName = '';
$errors = [];
$editId = null;
$editName = '';

$search = trim($_GET['search'] ?? '');
$sort = trim($_GET['sort'] ?? 'alpha');

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// older starter categories might not have a userid saved on them
foreach ($categories as &$category) {
    if (!array_key_exists('userId', $category)) {
        $category['userId'] = null;
    }
}
unset($category);

// show the shared default categories as well as any ones this user made
$userCategories = array_values(array_filter($categories, function ($category) use ($userId) {
    $ownerId = $category['userId'] ?? null;
    return $ownerId === null || (int)$ownerId === (int)$userId;
}));

// add a category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $categoryName = trim($_POST['category_name'] ?? '');

    if ($categoryName === '') {
        $errors['category_name'] = 'Category name cannot be empty.';
    }

    foreach ($userCategories as $category) {
        if (
            isset($category['name']) &&
            strtolower((string)$category['name']) === strtolower($categoryName)
        ) {
            $errors['category_name'] = 'A category with this name already exists.';
            break;
        }
    }

    if (empty($errors)) {
        $newCategory = [
            'categoryId' => getNextId($categories, 'categoryId'),
            'name' => $categoryName,
            'userId' => $userId
        ];

        $categories[] = $newCategory;
        saveJsonData($categoriesFile, $categories);

        header('Location: categories.php');
        exit();
    }
}

// loads the edit form if theres an edit id in the url
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    foreach ($userCategories as $category) {
        $categoryOwnerId = $category['userId'] ?? null;

        // shared default categories cant be edited
        if (
            (int)($category['categoryId'] ?? 0) === $editId &&
            $categoryOwnerId !== null &&
            (int)$categoryOwnerId === (int)$userId
        ) {
            $editName = (string)($category['name'] ?? '');
            break;
        }
    }

    if ($editId !== null && $editName === '') {
        $editId = null;
    }
}

// save a category rename
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $editName = trim($_POST['edit_name'] ?? '');

    if ($editName === '') {
        $errors['edit_name'] = 'Category name cannot be empty.';
    }

    foreach ($userCategories as $category) {
        if (
            (int)($category['categoryId'] ?? 0) !== $editId &&
            strtolower((string)($category['name'] ?? '')) === strtolower($editName)
        ) {
            $errors['edit_name'] = 'A category with this name already exists.';
            break;
        }
    }

    if (empty($errors)) {
        $updated = false;

        foreach ($categories as &$category) {
            $categoryOwnerId = $category['userId'] ?? null;

            if (
                (int)($category['categoryId'] ?? 0) === $editId &&
                $categoryOwnerId !== null &&
                (int)$categoryOwnerId === (int)$userId
            ) {
                $oldName = (string)($category['name'] ?? '');
                $category['name'] = $editName;
                $updated = true;

                // updates old transactions too so they still match the renamed category
                foreach ($transactions as &$transaction) {
                    if (
                        isset($transaction['userId'], $transaction['category']) &&
                        (int)$transaction['userId'] === (int)$userId &&
                        strtolower((string)$transaction['category']) === strtolower($oldName)
                    ) {
                        $transaction['category'] = $editName;
                    }
                }
                unset($transaction);

                break;
            }
        }
        unset($category);

        if ($updated) {
            saveJsonData($categoriesFile, $categories);
            saveJsonData($transactionsFile, $transactions);
            header('Location: categories.php');
            exit();
        } else {
            $errors['edit_name'] = 'You can only edit your own custom categories.';
        }
    }
}

// delete a category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $deleteId = (int)($_POST['delete_id'] ?? 0);
    $deletedCategoryName = '';

    foreach ($categories as $category) {
        $categoryOwnerId = $category['userId'] ?? null;

        if (
            (int)($category['categoryId'] ?? 0) === $deleteId &&
            $categoryOwnerId !== null &&
            (int)$categoryOwnerId === (int)$userId
        ) {
            $deletedCategoryName = (string)($category['name'] ?? '');
            break;
        }
    }

    if ($deletedCategoryName !== '') {
        $categories = array_values(array_filter($categories, function ($category) use ($deleteId, $userId) {
            $categoryOwnerId = $category['userId'] ?? null;

            if (
                (int)($category['categoryId'] ?? 0) === $deleteId &&
                $categoryOwnerId !== null &&
                (int)$categoryOwnerId === (int)$userId
            ) {
                return false;
            }

            return true;
        }));

        // old transactions get moved to uncategorised instead of being left broken
        foreach ($transactions as &$transaction) {
            if (
                isset($transaction['userId'], $transaction['category']) &&
                (int)$transaction['userId'] === (int)$userId &&
                strtolower((string)$transaction['category']) === strtolower($deletedCategoryName)
            ) {
                $transaction['category'] = 'Uncategorised';
            }
        }
        unset($transaction);

        saveJsonData($categoriesFile, $categories);
        saveJsonData($transactionsFile, $transactions);

        header('Location: categories.php');
        exit();
    } else {
        $errors['delete'] = 'You can only delete your own custom categories.';
    }
}

// refreshes the list after any add, edit or delete
$userCategories = array_values(array_filter($categories, function ($category) use ($userId) {
    $ownerId = $category['userId'] ?? null;
    return $ownerId === null || (int)$ownerId === (int)$userId;
}));

// search filter
$filteredCategories = array_values(array_filter($userCategories, function ($category) use ($search) {
    if ($search === '') {
        return true;
    }

    return strpos(
        strtolower((string)($category['name'] ?? '')),
        strtolower($search)
    ) !== false;
}));

if ($sort === 'alpha') {
    usort($filteredCategories, function ($a, $b) {
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
} elseif ($sort === 'alpha-desc') {
    usort($filteredCategories, function ($a, $b) {
        return strcasecmp((string)($b['name'] ?? ''), (string)($a['name'] ?? ''));
    });
}

// counts how often each category is used
$categoryUsageCounts = [];
$recentCustomCategoryCount = 0;

foreach ($userCategories as $category) {
    $name = (string)($category['name'] ?? '');
    if ($name !== '') {
        $categoryUsageCounts[$name] = 0;
    }

    if (($category['userId'] ?? null) !== null) {
        $recentCustomCategoryCount++;
    }
}

foreach ($transactions as $transaction) {
    if (
        isset($transaction['userId'], $transaction['category']) &&
        (int)$transaction['userId'] === (int)$userId
    ) {
        $transactionCategory = (string)$transaction['category'];
        if (isset($categoryUsageCounts[$transactionCategory])) {
            $categoryUsageCounts[$transactionCategory]++;
        }
    }
}

$totalCategories = count($userCategories);
$activeCategories = 0;

foreach ($categoryUsageCounts as $count) {
    if ($count > 0) {
        $activeCategories++;
    }
}

// picks an icon and colour based on the category name
function getCategoryDisplayMeta(string $name): array
{
    $lower = strtolower($name);

    if (strpos($lower, 'food') !== false || strpos($lower, 'dining') !== false) {
        return ['icon' => 'fa-utensils', 'badge' => 'category-badge-red'];
    }
    if (strpos($lower, 'transport') !== false || strpos($lower, 'travel') !== false) {
        return ['icon' => 'fa-car', 'badge' => 'category-badge-green'];
    }
    if (strpos($lower, 'housing') !== false || strpos($lower, 'home') !== false || strpos($lower, 'rent') !== false) {
        return ['icon' => 'fa-house', 'badge' => 'category-badge-blue'];
    }
    if (strpos($lower, 'health') !== false || strpos($lower, 'fitness') !== false) {
        return ['icon' => 'fa-heart-pulse', 'badge' => 'category-badge-purple'];
    }
    if (strpos($lower, 'entertainment') !== false || strpos($lower, 'movie') !== false) {
        return ['icon' => 'fa-film', 'badge' => 'category-badge-amber'];
    }
    if (strpos($lower, 'salary') !== false || strpos($lower, 'income') !== false) {
        return ['icon' => 'fa-money-bill-wave', 'badge' => 'category-badge-green'];
    }

    return ['icon' => 'fa-folder', 'badge' => 'category-badge-default'];
}

$pageTitle = 'Categories';
$currentPage = 'Categories';
?>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php endif; ?>

<main class="dashboard-main" id="main-content">
    <!-- page header -->
    <header class="dashboard-header page-header-clean categories-header">
        <div>
            <h2>Category Management</h2>
            <p>Organise your transaction categories and keep everything tidy.</p>
        </div>

        <button type="button" class="btn-primary btn-primary-sm category-header-btn" id="openCategoryModal">
            <i class="fas fa-circle-plus"></i> Add Category
        </button>
    </header>

    <!-- top stats -->
    <section class="summary-cards" aria-label="Category statistics">
        <div class="summary-card stat-card">
            <div class="stat-card-top">
                <h3>Total Categories</h3>
                <div class="stat-icon stat-icon-tx"><i class="fas fa-tags" aria-hidden="true"></i></div>
            </div>
            <p><?php echo $totalCategories; ?></p>
            <span>Available to use</span>
        </div>

        <div class="summary-card stat-card">
            <div class="stat-card-top">
                <h3>Active Categories</h3>
                <div class="stat-icon stat-icon-income"><i class="fas fa-layer-group" aria-hidden="true"></i></div>
            </div>
            <p><?php echo $activeCategories; ?></p>
            <span>Used in your transactions</span>
        </div>

        <div class="summary-card stat-card">
            <div class="stat-card-top">
                <h3>Custom Categories</h3>
                <div class="stat-icon stat-icon-balance"><i class="fas fa-bolt" aria-hidden="true"></i></div>
            </div>
            <p><?php echo $recentCustomCategoryCount; ?></p>
            <span>Created by you</span>
        </div>
    </section>

    <!-- Add category modal -->
    <div class="category-modal-overlay" id="categoryModalOverlay"></div>

    <!-- add category modal -->
    <section class="category-modal" id="categoryModal" aria-hidden="true">
        <div class="category-modal-card">
            <div class="category-modal-header">
                <div>
                    <h3>Add New Category</h3>
                    <p>Create a category to classify your transactions.</p>
                </div>
                <button type="button" class="category-modal-close" id="closeCategoryModal" aria-label="Close add category form">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="categories.php" method="post" novalidate>
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label for="category_name"><i class="fas fa-pen"></i> Category Name *</label>
                    <input
                        type="text"
                        id="category_name"
                        name="category_name"
                        value="<?php echo sanitiseOutput($categoryName); ?>"
                        aria-required="true"
                        aria-describedby="<?php echo isset($errors['category_name']) ? 'category_name_error' : ''; ?>"
                        class="<?php echo isset($errors['category_name']) ? 'input-error' : ''; ?>"
                        required
                        placeholder="Enter category name">
                    <?php if (isset($errors['category_name'])): ?>
                        <p class="error" id="category_name_error" role="alert"><?php echo sanitiseOutput($errors['category_name']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions modern-actions">
                    <button type="submit" name="add_category" class="btn-primary btn-large">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                </div>
            </form>
        </div>
    </section>

    <?php if ($editId !== null): ?>
        <!-- edit category form -->
        <section class="panel category-form-modern category-form-panel">
            <div class="form-intro">
                <div class="form-intro-icon">
                    <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                </div>
                <div>
                    <h3>Edit Category</h3>
                    <p>Update the category name below.</p>
                </div>
            </div>

            <form action="categories.php" method="post" novalidate>
                <?php echo csrfField(); ?>
                <input type="hidden" name="edit_id" value="<?php echo (int)$editId; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name"><i class="fas fa-tag"></i> Category Name *</label>
                        <input
                            type="text"
                            id="edit_name"
                            name="edit_name"
                            value="<?php echo sanitiseOutput($editName); ?>"
                            aria-required="true"
                            aria-describedby="<?php echo isset($errors['edit_name']) ? 'edit_name_error' : ''; ?>"
                            class="<?php echo isset($errors['edit_name']) ? 'input-error' : ''; ?>"
                            required>
                        <?php if (isset($errors['edit_name'])): ?>
                            <p class="error" id="edit_name_error" role="alert"><?php echo sanitiseOutput($errors['edit_name']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group category-form-button">
                        <button type="submit" name="update_category" class="btn-primary btn-large">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </div>

                <p class="auth-link left-align-link">
                    <a href="categories.php"><i class="fas fa-xmark"></i> Cancel Edit</a>
                </p>
            </form>
        </section>
    <?php endif; ?>

    <?php if (isset($errors['delete'])): ?>
        <div class="error-summary" role="alert">
            <h3>Please fix the following error:</h3>
            <ul>
                <li><?php echo sanitiseOutput($errors['delete']); ?></li>
            </ul>
        </div>
    <?php endif; ?>

    <!-- category list panel -->
    <section class="panel category-table-panel">
        <div class="category-toolbar">
            <form method="get" action="categories.php" class="category-search-form ajax-form">
                <div class="category-search-wrap">
                    <label for="category-search" class="visually-hidden">Search categories</label>
                    <i class="fas fa-search category-search-icon" aria-hidden="true"></i>
                    <input
                        type="text"
                        id="category-search"
                        name="search"
                        class="category-search-input"
                        placeholder="Search categories..."
                        value="<?php echo sanitiseOutput($search); ?>">
                </div>
            </form>

            <?php
            $nextSort = ($sort === 'alpha') ? 'alpha-desc' : 'alpha';
            $sortLabel = ($sort === 'alpha') ? 'Sort: A → Z' : 'Sort: Z → A';
            $sortIcon = ($sort === 'alpha') ? 'fa-arrow-down-a-z' : 'fa-arrow-up-z-a';
            $sortHref = 'categories.php?sort=' . urlencode($nextSort) . ($search !== '' ? '&search=' . urlencode($search) : '');
            ?>
            <a href="<?php echo sanitiseOutput($sortHref); ?>" class="category-sort-box ajax-link" aria-label="Toggle sort order">
                <i class="fas <?php echo $sortIcon; ?>" aria-hidden="true"></i>
                <span><?php echo $sortLabel; ?></span>
            </a>
        </div>

        <?php if (!empty($filteredCategories)): ?>
            <!-- desktop table -->
            <div class="table-wrapper category-table-wrapper">
                <table class="data-table category-modern-table">
                    <thead>
                        <tr>
                            <th scope="col">Category</th>
                            <th scope="col">Code</th>
                            <th scope="col">Usage</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="category-actions-heading">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $maxUsage = !empty($categoryUsageCounts) ? max($categoryUsageCounts) : 0;
                        ?>

                        <?php foreach ($filteredCategories as $category): ?>
                            <?php
                            $categoryId = (int)($category['categoryId'] ?? 0);
                            $categoryNameText = (string)($category['name'] ?? '');
                            $usageCount = $categoryUsageCounts[$categoryNameText] ?? 0;
                            $usagePercent = $maxUsage > 0 ? (int)round(($usageCount / $maxUsage) * 100) : 0;

                            $statusText = $usageCount > 0 ? 'Active' : 'Unused';
                            $statusClass = $usageCount > 0 ? 'category-status-active' : 'category-status-normal';

                            $meta = getCategoryDisplayMeta($categoryNameText);
                            $isCustom = (($category['userId'] ?? null) !== null);
                            ?>
                            <tr>
                                <td>
                                    <div class="category-main-cell">
                                        <div class="category-table-icon <?php echo $meta['badge']; ?>">
                                            <i class="fas <?php echo $meta['icon']; ?>" aria-hidden="true"></i>
                                        </div>
                                        <span class="category-table-name"><?php echo sanitiseOutput($categoryNameText); ?></span>
                                    </div>
                                </td>

                                <td>
                                    <span class="category-code-pill">
                                        #<?php echo str_pad((string)$categoryId, 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="category-usage-wrap">
                                        <div class="category-usage-bar" aria-hidden="true">
                                            <div class="category-usage-fill" style="width: <?php echo $usagePercent; ?>%;"></div>
                                        </div>
                                        <span class="category-usage-text"><?php echo $usageCount; ?></span>
                                    </div>
                                </td>

                                <td>
                                    <span class="category-status-pill <?php echo $statusClass; ?>">
                                        <?php echo sanitiseOutput($statusText); ?>
                                    </span>
                                </td>

                                <td class="category-actions-cell">
                                    <?php if ($isCustom): ?>
                                        <div class="category-row-actions">
                                            <a
                                                href="categories.php?edit=<?php echo $categoryId; ?>"
                                                class="category-icon-action edit-link"
                                                title="Edit category"
                                                aria-label="Edit <?php echo sanitiseOutput($categoryNameText); ?>">
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                            </a>

                                            <form action="categories.php" method="post" style="display:inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="delete_id" value="<?php echo $categoryId; ?>">
                                                <button
                                                    type="submit"
                                                    name="delete_category"
                                                    class="category-icon-action delete-link"
                                                    title="Delete category"
                                                    aria-label="Delete <?php echo sanitiseOutput($categoryNameText); ?>"
                                                    onclick="return confirm('Are you sure you want to delete this category?');">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="category-status-pill category-status-normal">Default</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- mobile card view -->
            <div class="mobile-category-cards" aria-label="Categories list">
                <?php
                $mobileMaxUsage = !empty($categoryUsageCounts) ? max($categoryUsageCounts) : 0;
                foreach ($filteredCategories as $category):
                    $catId       = (int)($category['categoryId'] ?? 0);
                    $catName     = (string)($category['name'] ?? '');
                    $usageCount  = $categoryUsageCounts[$catName] ?? 0;
                    $usagePct    = $mobileMaxUsage > 0 ? (int)round(($usageCount / $mobileMaxUsage) * 100) : 0;
                    $isActive    = $usageCount > 0;
                    $isCustom    = (($category['userId'] ?? null) !== null);
                    $meta        = getCategoryDisplayMeta($catName);
                ?>
                    <div class="mobile-cat-card">
                        <div class="category-table-icon <?php echo $meta['badge']; ?>">
                            <i class="fas <?php echo $meta['icon']; ?>" aria-hidden="true"></i>
                        </div>
                        <div class="mobile-cat-body">
                            <div class="mobile-cat-header-row">
                                <div class="mobile-cat-name"><?php echo sanitiseOutput($catName); ?></div>
                                <?php if ($isCustom): ?>
                                    <div class="mobile-cat-actions">
                                        <a href="categories.php?edit=<?php echo $catId; ?>"
                                            class="category-icon-action edit-link"
                                            aria-label="Edit <?php echo sanitiseOutput($catName); ?>">
                                            <i class="fas fa-pen" aria-hidden="true"></i>
                                        </a>
                                        <form action="categories.php" method="post" style="display:inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="delete_id" value="<?php echo $catId; ?>">
                                            <button type="submit" name="delete_category"
                                                class="category-icon-action delete-link"
                                                aria-label="Delete <?php echo sanitiseOutput($catName); ?>"
                                                onclick="return confirm('Delete this category?');">
                                                <i class="fas fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mobile-cat-progress-row">
                                <div class="category-usage-bar" style="flex:1;" aria-hidden="true">
                                    <div class="category-usage-fill" style="width:<?php echo $usagePct; ?>%;"></div>
                                </div>
                                <span class="category-usage-text"><?php echo $usageCount; ?></span>
                            </div>
                            <div class="mobile-cat-meta-row">
                                <span class="category-status-pill <?php echo $isActive ? 'category-status-active' : 'category-status-normal'; ?>">
                                    <?php echo $isActive ? 'Active' : 'Unused'; ?>
                                </span>
                                <?php if (!$isCustom): ?>
                                    <span class="category-status-pill category-status-normal">Default</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- simple footer for the category list -->
            <div class="category-pagination">
                <p>Showing <strong><?php echo count($filteredCategories); ?></strong> of <strong><?php echo count($userCategories); ?></strong> categories</p>
            </div>
        <?php else: ?>
            <!-- empty state -->
            <div class="tx-empty">
                <p>No categories found.</p>
                <p>Try a different search or add a new category.</p>
                <?php if ($search !== ''): ?>
                    <a href="categories.php" class="btn-secondary-sm ajax-link">Clear search</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php if (isset($errors['category_name'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('categoryModal');
            var overlay = document.getElementById('categoryModalOverlay');

            if (modal && overlay) {
                modal.classList.add('active');
                overlay.classList.add('active');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }
        });
    </script>
<?php endif; ?>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
