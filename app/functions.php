<?php

// Makes sure the json files are there
function initialiseDataFiles(): void
{
    $dataDir = __DIR__ . '/../data';

    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0750, true);
    }

    // stop the web server handing out the json files directly (Apache)
    $htaccessFile = $dataDir . '/.htaccess';
    if (!file_exists($htaccessFile)) {
        file_put_contents($htaccessFile, "Require all denied\n");
    }

    $usersFile = $dataDir . '/users.json';
    $currenciesFile = $dataDir . '/currencies.json';
    $transactionsFile = $dataDir . '/transactions.json';
    $categoriesFile = $dataDir . '/categories.json';

    if (!file_exists($usersFile)) {
        file_put_contents($usersFile, json_encode([], JSON_PRETTY_PRINT));
    }

    if (!file_exists($currenciesFile)) {
        $defaultCurrencies = [
            ['code' => 'GBP', 'name' => 'British Pound'],
            ['code' => 'USD', 'name' => 'US Dollar'],
            ['code' => 'EUR', 'name' => 'Euro'],
            ['code' => 'JPY', 'name' => 'Japanese Yen'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar']
        ];

        file_put_contents($currenciesFile, json_encode($defaultCurrencies, JSON_PRETTY_PRINT));
    }

    if (!file_exists($transactionsFile)) {
        file_put_contents($transactionsFile, json_encode([], JSON_PRETTY_PRINT));
    }

    if (!file_exists($categoriesFile)) {
        $defaultCategories = [
            ['categoryId' => 1, 'name' => 'Food'],
            ['categoryId' => 2, 'name' => 'Transport'],
            ['categoryId' => 3, 'name' => 'Bills'],
            ['categoryId' => 4, 'name' => 'Entertainment'],
            ['categoryId' => 5, 'name' => 'Salary']
        ];

        file_put_contents($categoriesFile, json_encode($defaultCategories, JSON_PRETTY_PRINT));
    }
}

// Reads a json file
function loadJsonData(string $filePath): array
{
    if (!file_exists($filePath)) {
        return [];
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return [];
    }

    // shared lock so we never read a file halfway through being written
    flock($handle, LOCK_SH);
    $json = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    $data = json_decode((string)$json, true);

    return is_array($data) ? $data : [];
}

// Saves a json file
// writes to a temp file first then swaps it in, so a crash or two requests
// at once can't leave a half-written or corrupted file behind
function saveJsonData(string $filePath, array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    $tempFile = $filePath . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tempFile, $json, LOCK_EX) === false) {
        return false;
    }

    if (!rename($tempFile, $filePath)) {
        @unlink($tempFile);
        return false;
    }

    return true;
}

// Grab the next id
function getNextId(array $items, string $idKey): int
{
    if (empty($items)) {
        return 1;
    }

    $ids = array_column($items, $idKey);
    return max($ids) + 1;
}

// Safe output for the page
function sanitiseOutput(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Keep email checks consistent
function normaliseEmail(string $email): string
{
    return strtolower(trim($email));
}

// Quick user lookup by email
function findUserByEmail(array $users, string $email): ?array
{
    $normalisedEmail = normaliseEmail($email);

    foreach ($users as $user) {
        if (normaliseEmail((string)($user['email'] ?? '')) === $normalisedEmail) {
            return $user;
        }
    }

    return null;
}

// Shared users file path
function getUsersFilePath(): string
{
    return __DIR__ . '/../data/users.json';
}

// Remember me cookie name
function getRememberMeCookieName(): string
{
    return 'remember_token';
}

// 30 days
function getRememberMeDuration(): int
{
    return 60 * 60 * 24 * 30;
}

// Cookie settings in one place
function buildRememberMeCookieOptions(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ];
}

// Sets the remember me cookie
function setRememberMeCookie(string $token): void
{
    setcookie(
        getRememberMeCookieName(),
        $token,
        buildRememberMeCookieOptions(time() + getRememberMeDuration())
    );
}

// Clears the remember me cookie
function clearRememberMeCookie(): void
{
    setcookie(
        getRememberMeCookieName(),
        '',
        buildRememberMeCookieOptions(time() - 3600)
    );
}

// Removes saved token for one user
function clearRememberMeTokenForUserId(int $userId): void
{
    $usersFile = getUsersFilePath();
    $users = loadJsonData($usersFile);
    $updated = false;

    foreach ($users as &$user) {
        if ((int)($user['userId'] ?? 0) !== $userId) {
            continue;
        }

        unset($user['rememberTokenHash'], $user['rememberTokenExpiresAt']);
        $updated = true;
        break;
    }
    unset($user);

    if ($updated) {
        saveJsonData($usersFile, $users);
    }
}

// Saves a fresh token when remember me is ticked
function persistRememberMeToken(array $user): void
{
    $usersFile = getUsersFilePath();
    $users = loadJsonData($usersFile);
    $token = bin2hex(random_bytes(32));
    $expiresAt = time() + getRememberMeDuration();

    foreach ($users as &$storedUser) {
        if ((int)($storedUser['userId'] ?? 0) !== (int)($user['userId'] ?? 0)) {
            continue;
        }

        $storedUser['rememberTokenHash'] = password_hash($token, PASSWORD_DEFAULT);
        $storedUser['rememberTokenExpiresAt'] = date('c', $expiresAt);
        break;
    }
    unset($storedUser);

    saveJsonData($usersFile, $users);
    setRememberMeCookie($token);
}

// Clears remember me for the current user
function clearRememberMeForCurrentUser(): void
{
    if (!empty($_SESSION['user_id'])) {
        clearRememberMeTokenForUserId((int)$_SESSION['user_id']);
    }

    clearRememberMeCookie();
}

// Starts the normal logged in session
function startAuthenticatedSession(array $user): void
{
    $_SESSION['user_id'] = (int)$user['userId'];
    $_SESSION['user_name'] = trim(((string)($user['firstName'] ?? '')) . ' ' . ((string)($user['lastName'] ?? '')));
    $_SESSION['user_email'] = (string)($user['email'] ?? '');
}

// Tries auto login from the remember me cookie
function authenticateRememberedUser(): bool
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    $token = $_COOKIE[getRememberMeCookieName()] ?? '';
    if ($token === '') {
        return false;
    }

    $usersFile = getUsersFilePath();
    $users = loadJsonData($usersFile);
    $matchedUser = null;
    $usersUpdated = false;

    foreach ($users as &$user) {
        $hash = (string)($user['rememberTokenHash'] ?? '');
        $expiresAt = (string)($user['rememberTokenExpiresAt'] ?? '');

        if ($hash === '' || $expiresAt === '') {
            continue;
        }

        $expiryTimestamp = strtotime($expiresAt);
        if ($expiryTimestamp === false || $expiryTimestamp < time()) {
            unset($user['rememberTokenHash'], $user['rememberTokenExpiresAt']);
            $usersUpdated = true;
            continue;
        }

        if (!password_verify($token, $hash)) {
            continue;
        }

        $matchedUser = $user;
        $newToken = bin2hex(random_bytes(32));
        $user['rememberTokenHash'] = password_hash($newToken, PASSWORD_DEFAULT);
        $user['rememberTokenExpiresAt'] = date('c', time() + getRememberMeDuration());
        setRememberMeCookie($newToken);
        $usersUpdated = true;
        break;
    }
    unset($user);

    if ($usersUpdated) {
        saveJsonData($usersFile, $users);
    }

    if ($matchedUser === null) {
        clearRememberMeCookie();
        return false;
    }

    startAuthenticatedSession($matchedUser);
    return true;
}
