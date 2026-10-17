<?php
session_start();
require_once __DIR__ . '/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

initialiseDataFiles();

// reject any form post that doesn't carry this session's csrf token
verifyCsrfTokenOrFail();

$currencies = loadJsonData(__DIR__ . '/../data/currencies.json');

$firstName = '';
$lastName = '';
$email = '';
$defaultCurrency = '';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = normaliseEmail($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $defaultCurrency = trim($_POST['default_currency'] ?? '');

    // basic checks before saving anything
    if ($firstName === '') {
        $errors['first_name'] = 'First name is required.';
    }

    if ($lastName === '') {
        $errors['last_name'] = 'Last name is required.';
    }

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if ($defaultCurrency === '') {
        $errors['default_currency'] = 'Please select a default currency.';
    }

    $validCurrencyCodes = array_column($currencies, 'code');
    if ($defaultCurrency !== '' && !in_array($defaultCurrency, $validCurrencyCodes, true)) {
        $errors['default_currency'] = 'Invalid currency selected.';
    }

    $usersFile = __DIR__ . '/../data/users.json';
    $users = loadJsonData($usersFile);

    // stops duplicate accounts using the same email
    if (findUserByEmail($users, $email) !== null) {
        $errors['email'] = 'An account with this email already exists.';
    }

    if (empty($errors)) {
        $newUserId = getNextId($users, 'userId');

        $newUser = [
            'userId' => $newUserId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'defaultCurrency' => $defaultCurrency
        ];

        $users[] = $newUser;
        saveJsonData($usersFile, $users);

        // signs them in straight away after registering
        startAuthenticatedSession($newUser);
        header('Location: dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | FinanceTracker</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body class="auth-page-body">
    <div class="auth-page-shell">
        <!-- left side intro panel -->
        <div class="auth-hero-panel">
            <div class="auth-hero-badge">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1>Join FinanceTracker</h1>
            <p>Create an account to start tracking your money.</p>

            <div class="auth-hero-features">
                <div class="auth-feature-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Track income & expenses</span>
                </div>
                <div class="auth-feature-item">
                    <i class="fas fa-tags"></i>
                    <span>Organise by categories</span>
                </div>
                <div class="auth-feature-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Visual financial reports</span>
                </div>
                <div class="auth-feature-item">
                    <i class="fas fa-globe"></i>
                    <span>Multi-currency support</span>
                </div>
            </div>
        </div>

        <!-- main register card -->
        <div class="auth-card auth-card-modern auth-card-wide">
            <div class="auth-card-header">
                <h2>Create Account</h2>
                <p>Fill in your details below.</p>
            </div>

            <!-- show any validation errors at the top -->
            <?php if (!empty($errors)): ?>
                <div class="auth-error-box">
                    <?php foreach ($errors as $error): ?>
                        <p class="error" style="margin-bottom: 5px;">⚠️ <?php echo sanitiseOutput($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- register form -->
            <form action="register.php" method="post" novalidate>
                <?php echo csrfField(); ?>
                <!-- first and last name row -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">
                            <i class="fas fa-user"></i> First Name *
                        </label>
                        <div class="input-icon-wrap">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input 
                                type="text" 
                                id="first_name" 
                                name="first_name" 
                                value="<?php echo sanitiseOutput($firstName); ?>" 
                                placeholder="Enter your first name"
                                aria-required="true"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="last_name">
                            <i class="fas fa-user"></i> Last Name *
                        </label>
                        <div class="input-icon-wrap">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input 
                                type="text" 
                                id="last_name" 
                                name="last_name" 
                                value="<?php echo sanitiseOutput($lastName); ?>" 
                                placeholder="Enter your last name"
                                aria-required="true"
                                required>
                        </div>
                    </div>
                </div>

                <!-- email field -->
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email Address *
                    </label>
                    <div class="input-icon-wrap">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo sanitiseOutput($email); ?>" 
                            placeholder="Enter your email address"
                            autocomplete="email"
                            aria-required="true"
                            required>
                    </div>
                </div>

                <!-- password row -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password *
                        </label>
                        <div class="input-icon-wrap">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Minimum 8 characters"
                                autocomplete="new-password"
                                aria-required="true"
                                required>
                        </div>
                        <small style="color: #64748b; font-size: 0.75rem; margin-top: 4px; display: block;">
                            <i class="fas fa-info-circle"></i> At least 8 characters
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-check-circle"></i> Confirm Password *
                        </label>
                        <div class="input-icon-wrap">
                            <span class="input-icon"><i class="fas fa-check-circle"></i></span>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                placeholder="Confirm your password"
                                autocomplete="new-password"
                                aria-required="true"
                                required>
                        </div>
                    </div>
                </div>

                <!-- currency dropdown -->
                <div class="form-group">
                    <label for="default_currency">
                        <i class="fas fa-dollar-sign"></i> Default Currency *
                    </label>
                    <div class="input-icon-wrap">
                        <span class="input-icon"><i class="fas fa-globe"></i></span>
                        <select id="default_currency" name="default_currency" aria-required="true" required>
                            <option value="">-- Select Currency --</option>
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?php echo sanitiseOutput($currency['code']); ?>" <?php echo ($defaultCurrency === $currency['code']) ? 'selected' : ''; ?>>
                                    <?php echo sanitiseOutput($currency['code'] . ' - ' . $currency['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- create account button -->
                <button type="submit" class="btn-primary auth-main-button">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>

                <!-- link back to login -->
                <p class="auth-link">
                    Already have an account?
                    <a href="login.php">Sign in here</a>
                </p>
            </form>
        </div>
    </div>

    <script>
        // just gives a tiny visual hint while they type
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const strength = this.value.length;
                if (strength > 0 && strength < 8) {
                    this.style.borderColor = '#dc2626';
                } else if (strength >= 8) {
                    this.style.borderColor = '#16a34a';
                } else {
                    this.style.borderColor = '#d1d5db';
                }
            });
        }
    </script>
</body>

</html>
