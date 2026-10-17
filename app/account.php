<?php
// pull in auth check and helper functions - these are needed before anything else runs
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// makes sure the data files exist before we try to read them
initialiseDataFiles();

// paths to the json files we need for this page
$usersFile = __DIR__ . '/../data/users.json';
$currenciesFile = __DIR__ . '/../data/currencies.json';

// loads the data from both files into arrays
$users = loadJsonData($usersFile);
$currencies = loadJsonData($currenciesFile);

// gets the logged in users id from the session
$userId = $_SESSION['user_id'] ?? null;

// these will be filled in once we find the matching user below
$currentUser = null;
$currentUserIndex = null;

// check if this request came from ajax so we know whether to include the full page layout
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// loop through all users to find the one that matches the session user id
foreach ($users as $index => $user) {
    if (isset($user['userId']) && (int)$user['userId'] === (int)$userId) {
        $currentUser = $user;
        $currentUserIndex = $index; // save the index so we can update this user later
        break;
    }
}

// if we couldnt find the user, destroy the session and send them to login
if ($currentUser === null) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}
// pull the current users details into variables for use in the form
$firstName = $currentUser['firstName'] ?? '';
$lastName = $currentUser['lastName'] ?? '';
$email = $currentUser['email'] ?? '';
$defaultCurrency = $currentUser['defaultCurrency'] ?? '';

// separate error arrays for the two forms on this page
$profileErrors = [];
$passwordErrors = [];
$successMessage = '';
$passwordSuccessMessage = '';

// builds an array of valid currency codes from the loaded currencies file for validation
$validCurrencyCodes = array_column($currencies, 'code');

// handles the profile update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $defaultCurrency = trim($_POST['default_currency'] ?? '');

    // validates each field and store any errors
    if ($firstName === '') {
        $profileErrors['first_name'] = 'First name is required.';
    }

    if ($lastName === '') {
        $profileErrors['last_name'] = 'Last name is required.';
    }

    if ($email === '') {
        $profileErrors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // filter_var checks if its a properly formatted email address
        $profileErrors['email'] = 'Please enter a valid email address.';
    }

    if ($defaultCurrency === '') {
        $profileErrors['default_currency'] = 'Please select a default currency.';
    } elseif (!in_array($defaultCurrency, $validCurrencyCodes, true)) {
        // makea sure the currency code actually exists in our list, not just anything submitted
        $profileErrors['default_currency'] = 'Invalid currency selected.';
    }

    // checka no other account is already using this email address
    foreach ($users as $user) {
        if (
            isset($user['email'], $user['userId']) &&
            (int)$user['userId'] !== (int)$userId &&
            strtolower((string)$user['email']) === strtolower($email)
        ) {
            $profileErrors['email'] = 'Another account already uses this email address.';
            break;
        }
    }

    // only save if there are no errors
    if (empty($profileErrors)) {
        // update the user array with the new values
        $users[$currentUserIndex]['firstName'] = $firstName;
        $users[$currentUserIndex]['lastName'] = $lastName;
        $users[$currentUserIndex]['email'] = strtolower($email);
        $users[$currentUserIndex]['defaultCurrency'] = $defaultCurrency;

        // writes the updated users array back to the json file
        saveJsonData($usersFile, $users);

        // updates the session so the navbar reflects the new name/email straight away
        $_SESSION['user_name'] = $firstName . ' ' . $lastName;
        $_SESSION['user_email'] = strtolower($email);

        $currentUser = $users[$currentUserIndex];
        $successMessage = 'Account details updated successfully.';
    }
}

// Save password changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '') {
        $passwordErrors['current_password'] = 'Current password is required.';
    } elseif (!password_verify($currentPassword, $currentUser['password'] ?? '')) {
        $passwordErrors['current_password'] = 'Current password is incorrect.';
    }

    if ($newPassword === '') {
        $passwordErrors['new_password'] = 'New password is required.';
    } elseif (strlen($newPassword) < 8) {
        $passwordErrors['new_password'] = 'New password must be at least 8 characters long.';
    } elseif (password_verify($newPassword, $currentUser['password'] ?? '')) {
        $passwordErrors['new_password'] = 'New password must be different from your current password.';
    }

    if ($confirmPassword === '') {
        $passwordErrors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordErrors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($passwordErrors)) {
        $users[$currentUserIndex]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        saveJsonData($usersFile, $users);

        $currentUser = $users[$currentUserIndex];
        $passwordSuccessMessage = 'Password updated successfully.';
    }
}
$pageTitle = 'Account';
$currentPage = 'Account';
$pageClass = 'theme-light';
?>

<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<?php endif; ?>

<main class="dashboard-main" id="main-content">
    <header class="dashboard-header page-header-clean">
        <div>
            <h2>Account Settings</h2>
            <p>View and update your profile and security settings.</p>
        </div>
    </header>

    <section class="panel account-panel account-panel-modern">
        <div class="form-intro">
            <div class="form-intro-icon">
                <i class="fas fa-user" aria-hidden="true"></i>
            </div>
            <div>
                <h3>Profile Information</h3>
                <p>Update your personal details and preferred currency.</p>
            </div>
        </div>

        <?php if ($successMessage !== ''): ?>
            <p class="success-message" role="status"><?php echo sanitiseOutput($successMessage); ?></p>
        <?php endif; ?>

        <?php if (!empty($profileErrors)): ?>
            <div class="error-summary" role="alert">
                <h3>Please fix the following errors:</h3>
                <ul>
                    <?php foreach ($profileErrors as $error): ?>
                        <li><?php echo sanitiseOutput($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="account.php" method="post" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name"><i class="fas fa-user"></i> First Name *</label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        value="<?php echo sanitiseOutput($firstName); ?>"
                        required
                        aria-required="true"
                        aria-describedby="<?php echo isset($profileErrors['first_name']) ? 'first_name_error' : ''; ?>"
                        class="<?php echo isset($profileErrors['first_name']) ? 'input-error' : ''; ?>">
                    <?php if (isset($profileErrors['first_name'])): ?>
                        <p class="error" id="first_name_error" role="alert"><?php echo sanitiseOutput($profileErrors['first_name']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="last_name"><i class="fas fa-user"></i> Last Name *</label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        value="<?php echo sanitiseOutput($lastName); ?>"
                        required
                        aria-required="true"
                        aria-describedby="<?php echo isset($profileErrors['last_name']) ? 'last_name_error' : ''; ?>"
                        class="<?php echo isset($profileErrors['last_name']) ? 'input-error' : ''; ?>">
                    <?php if (isset($profileErrors['last_name'])): ?>
                        <p class="error" id="last_name_error" role="alert"><?php echo sanitiseOutput($profileErrors['last_name']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo sanitiseOutput($email); ?>"
                    required
                    aria-required="true"
                    aria-describedby="<?php echo isset($profileErrors['email']) ? 'email_error' : ''; ?>"
                    class="<?php echo isset($profileErrors['email']) ? 'input-error' : ''; ?>">
                <?php if (isset($profileErrors['email'])): ?>
                    <p class="error" id="email_error" role="alert"><?php echo sanitiseOutput($profileErrors['email']); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="default_currency"><i class="fas fa-coins"></i> Default Currency *</label>
                <select
                    id="default_currency"
                    name="default_currency"
                    required
                    aria-required="true"
                    aria-describedby="<?php echo isset($profileErrors['default_currency']) ? 'default_currency_error' : ''; ?>"
                    class="<?php echo isset($profileErrors['default_currency']) ? 'input-error' : ''; ?>">
                    <option value="">-- Select Currency --</option>
                    <?php foreach ($currencies as $currency): ?>
                        <option value="<?php echo sanitiseOutput($currency['code']); ?>" <?php echo ($defaultCurrency === $currency['code']) ? 'selected' : ''; ?>>
                            <?php echo sanitiseOutput($currency['code'] . ' - ' . $currency['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($profileErrors['default_currency'])): ?>
                    <p class="error" id="default_currency_error" role="alert"><?php echo sanitiseOutput($profileErrors['default_currency']); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-actions modern-actions">
                <button type="submit" name="update_profile" class="btn-primary btn-large">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </section>

    <!-- password change section - separate form so the two dont interfere with each other -->
    <section class="panel account-panel account-panel-modern">
        <div class="form-intro">
            <div class="form-intro-icon">
                <i class="fas fa-lock" aria-hidden="true"></i>
            </div>
            <div>
                <h3>Change Password</h3>
                <p>Keep your account secure by updating your password regularly.</p>
            </div>
        </div>
        
        <!-- show success message if password was changed successfully -->
        <?php if ($passwordSuccessMessage !== ''): ?>
            <p class="success-message" role="status"><?php echo sanitiseOutput($passwordSuccessMessage); ?></p>
        <?php endif; ?>

        <?php if (!empty($passwordErrors)): ?>
            <div class="error-summary" role="alert">
                <h3>Please fix the following errors:</h3>
                <ul>
                    <?php foreach ($passwordErrors as $error): ?>
                        <li><?php echo sanitiseOutput($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="account.php" method="post" novalidate>
            <div class="form-group">
                <label for="current_password"><i class="fas fa-key"></i> Current Password *</label>
                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    required
                    aria-required="true"
                    aria-describedby="<?php echo isset($passwordErrors['current_password']) ? 'current_password_error' : ''; ?>"
                    class="<?php echo isset($passwordErrors['current_password']) ? 'input-error' : ''; ?>">
                <?php if (isset($passwordErrors['current_password'])): ?>
                    <p class="error" id="current_password_error" role="alert"><?php echo sanitiseOutput($passwordErrors['current_password']); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="new_password"><i class="fas fa-lock"></i> New Password *</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                        aria-required="true"
                        aria-describedby="<?php echo isset($passwordErrors['new_password']) ? 'new_password_error' : 'new_password_hint'; ?>"
                        class="<?php echo isset($passwordErrors['new_password']) ? 'input-error' : ''; ?>">
                    <small id="new_password_hint" class="field-hint">Use at least 8 characters.</small>
                    <?php if (isset($passwordErrors['new_password'])): ?>
                        <p class="error" id="new_password_error" role="alert"><?php echo sanitiseOutput($passwordErrors['new_password']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm New Password *</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        aria-required="true"
                        aria-describedby="<?php echo isset($passwordErrors['confirm_password']) ? 'confirm_password_error' : ''; ?>"
                        class="<?php echo isset($passwordErrors['confirm_password']) ? 'input-error' : ''; ?>">
                    <?php if (isset($passwordErrors['confirm_password'])): ?>
                        <p class="error" id="confirm_password_error" role="alert"><?php echo sanitiseOutput($passwordErrors['confirm_password']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions modern-actions">
                <button type="submit" name="update_password" class="btn-primary btn-large">
                    <i class="fas fa-shield-alt"></i> Update Password
                </button>
            </div>
        </form>
    </section>
</main>


<?php if (!$isAjax): ?>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>