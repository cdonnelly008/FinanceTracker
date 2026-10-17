<?php
require_once __DIR__ . '/functions.php';
startSecureSession();
initialiseDataFiles();

// reject any form post that doesn't carry this session's csrf token
verifyCsrfTokenOrFail();
authenticateRememberedUser();

// no point showing login again if they are already signed in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$email = normaliseEmail($_GET['email'] ?? '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = normaliseEmail($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    // basic email check
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address (e.g., name@example.com).';
    }

    // basic password check
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }

    // only check the saved users if the form looks ok first
    if (empty($errors)) {
        $usersFile = __DIR__ . '/../data/users.json';
        $users = loadJsonData($usersFile);

        // tries to find the user by email in the json file
        $matchedUser = findUserByEmail($users, $email);

        // same message whether the email exists or not, so the form can't be
        // used to find out who has an account. we still run password_verify
        // against a dummy hash so both cases take about the same time
        $storedHash = (string)($matchedUser['password'] ?? '');
        if ($storedHash === '') {
            $storedHash = '$2y$10$iIjGJcp1iAyDOqDdKw405.GO5a8PWo1Tc9dOJ5A16DSM5S6CXz9TS';
        }
        $passwordOk = password_verify($password, $storedHash);

        if ($matchedUser === null || !$passwordOk) {
            $errors['login'] = 'Invalid email or password. Please try again.';
        } else {
            startAuthenticatedSession($matchedUser);

            if ($remember_me) {
                persistRememberMeToken($matchedUser);
            } else {
                clearRememberMeForCurrentUser();
            }

            header('Location: dashboard.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | FinanceTracker</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body class="auth-page-body">
    <div class="auth-page-shell">
        <!-- left side intro panel -->
        <div class="auth-hero-panel">
            <div class="auth-hero-badge">
                <i class="fas fa-wallet"></i>
            </div>
            <h1>FinanceTracker</h1>
            <p>Keep track of your money, categories and reports in one place.</p>

            <div class="auth-hero-features">
                <div class="auth-feature-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Interactive reports</span>
                </div>
                <div class="auth-feature-item">
                    <i class="fas fa-receipt"></i>
                    <span>Simple transaction tracking</span>
                </div>
                <div class="auth-feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure account access</span>
                </div>
            </div>
        </div>

        <!-- actual login card -->
        <div class="auth-card auth-card-modern">
            <div class="auth-card-header">
                <h2>Sign In</h2>
                <p>Sign in to see your dashboard.</p>
            </div>

            <!-- Success message for newly registered users -->
            <?php if (isset($_GET['registered']) && $_GET['registered'] === 'success'): ?>
                <div class="success-message" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <span>Account created successfully! Please log in with your credentials.</span>
                </div>
            <?php endif; ?>

            <!-- Error summary -->
            <?php if (!empty($errors)): ?>
                <div class="error-summary" role="alert">
                    <h3>
                        <i class="fas fa-exclamation-triangle"></i>
                        Please fix the following errors:
                    </h3>
                    <ul>
                        <?php foreach ($errors as $key => $error): ?>
                            <li><?php echo sanitiseOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- login form -->
            <form action="login.php" method="post" novalidate id="loginForm">
                <?php echo csrfField(); ?>
                <!-- email input -->
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
                            value="<?php echo htmlspecialchars($email); ?>"
                            autocomplete="email"
                            aria-required="true"
                            placeholder="Enter your email"
                            class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>"
                            required>
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <p class="error" role="alert"><?php echo sanitiseOutput($errors['email']); ?></p>
                    <?php else: ?>
                        <small class="field-hint"><i class="fas fa-info-circle"></i> Enter the email you used to register</small>
                    <?php endif; ?>
                </div>

                <!-- password input and show/hide button -->
                <div class="form-group">
                    <div class="label-row">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password *
                        </label>
                    </div>
                    <div class="input-icon-wrap" style="position: relative;">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            aria-required="true"
                            placeholder="Enter your password"
                            class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>"
                            required>
                        <button
                            type="button"
                            id="togglePassword"
                            style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748b; min-height: auto; padding: 0;"
                            aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <p class="error" role="alert"><?php echo sanitiseOutput($errors['password']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- remember me checkbox -->
                <div class="checkbox-group auth-checkbox-row">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Keep me logged in</label>
                </div>

                <!-- main sign in button -->
                <button type="submit" class="btn-primary auth-main-button" id="submitBtn">
                    <i class="fas fa-right-to-bracket"></i> Sign In
                </button>

                <!-- link to the register page -->
                <p class="auth-link">
                    Don't have an account?
                    <a href="register.php">Create one here</a>
                </p>
            </form>
        </div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const emailInput = document.getElementById('email');

        window.addEventListener('DOMContentLoaded', function() {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Sign In';
            }
        });

        if (form) {
            form.addEventListener('submit', function(e) {
                const email = document.getElementById('email');
                const password = document.getElementById('password');
                let hasError = false;

                document.querySelectorAll('.inline-error').forEach(function(el) {
                    el.remove();
                });

                if (email) {
                    email.classList.remove('input-error');
                }

                if (password) {
                    password.classList.remove('input-error');
                }

                if (!email.value.trim()) {
                    showError(email, 'Email address is required');
                    hasError = true;
                } else if (!isValidEmail(email.value.trim())) {
                    showError(email, 'Please enter a valid email address');
                    hasError = true;
                }

                if (!password.value) {
                    showError(password, 'Password is required');
                    hasError = true;
                }

                if (hasError) {
                    e.preventDefault();

                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Sign In';
                    }

                    return;
                }

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
                }
            });
        }

        function showError(field, message) {
            field.classList.add('input-error');

            const errorDiv = document.createElement('p');
            errorDiv.className = 'error inline-error';
            errorDiv.style.marginTop = '4px';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;

            field.parentElement.parentElement.appendChild(errorDiv);

            field.addEventListener('input', function() {
                field.classList.remove('input-error');

                const existingError = field.parentElement.parentElement.querySelector('.inline-error');
                if (existingError) {
                    existingError.remove();
                }
            }, {
                once: true
            });
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            return emailRegex.test(email);
        }

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }

        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                if (this.value.trim() && !isValidEmail(this.value.trim())) {
                    if (!this.parentElement.parentElement.querySelector('.inline-error')) {
                        showError(this, 'Please enter a valid email address');
                    }
                }
            });
        }
    </script>
</body>

</html>
