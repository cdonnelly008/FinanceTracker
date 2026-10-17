# FinanceTracker

A personal finance web app for tracking income and spending. Users create an account, log transactions in different currencies, organise them into categories and see where their money goes through interactive charts and reports.

<!-- Add 2–3 screenshots here, e.g. dashboard, reports and the mobile view:
![Dashboard](docs/screenshots/dashboard.png)
-->

## Features

- **Accounts:** register, log in, "remember me" for 30 days, and update your profile, password and default currency
- **Transactions:** add incoming and outgoing transactions, then search and filter by type and category
- **Categories:** built-in defaults plus your own custom categories, which you can rename or delete
- **Dashboard:** monthly income, spending and net savings, with a chart you can switch between week, month and year
- **Reports:** filter by date range, type and category, see spending breakdowns and income vs expense charts, and export to CSV
- **Multi-currency:** GBP, USD, EUR, JPY and CAD
- **Dark mode** and a responsive layout for mobile
- **AJAX navigation:** pages load without a full refresh

## Tech stack

| Area     | Technology                                     |
| -------- | ---------------------------------------------- |
| Backend  | PHP 8 (no framework)                           |
| Frontend | HTML, CSS, JavaScript, jQuery 3.7, Chart.js 4  |
| Storage  | JSON files in `data/`                          |
| Testing  | PHPUnit 11                                     |

## Security

Security was a focus throughout:

- **Passwords** are hashed with bcrypt (`password_hash` / `password_verify`).
- **Persistent login** uses the *selector:validator* pattern. The cookie holds a random lookup ID and a secret, and only a SHA-256 hash of the secret is stored. Tokens expire after 30 days, rotate every time they're used, and are wiped if a wrong secret is presented, so a stolen old cookie stops working.
- **Session fixation** is prevented by regenerating the session ID at login. Session and remember-me cookies are `HttpOnly` and `SameSite=Lax`, and `Secure` over HTTPS, with PHP strict session mode on.
- **CSRF protection:** every form that changes data includes a per-session token, checked with a constant-time comparison. Requests without a valid token are rejected with a 403.
- **XSS:** all user-supplied output is escaped with `htmlspecialchars`.
- **Access control:** every page only reads and changes the logged-in user's own transactions and categories.
- **No user enumeration:** login gives the same error whether or not an email is registered, and runs a dummy password check so response times match.
- **CSV injection:** exported cells that start with `=`, `+`, `-` or `@` are escaped so spreadsheet apps don't run them as formulas.
- **Data files:** writes go to a temp file and are then atomically renamed, so a crash can't leave a half-written file. Direct web access to `data/` is blocked with `.htaccess`.

## Getting started

### Requirements

- PHP 8.1 or newer
- Apache (for example Uniform Server, XAMPP or MAMP), or PHP's built-in server for local testing
- [Composer](https://getcomposer.org/), only needed to run the tests

### Run it

**With Uniform Server (UniServer Zero):** copy the project folder into `UniServerZ\www`, open `UniController.exe`, click **Start Apache**, then go to `http://localhost/<folder-name>/`. Make sure PHP 8.1+ is selected in UniController.

**With XAMPP or MAMP:** copy the project folder into `htdocs`, start Apache, then go to `http://localhost/<folder-name>/`.

**With PHP's built-in server:**

```bash
php -S localhost:8000
```

Then open <http://localhost:8000> and register an account. The JSON data files are created automatically on first run.

> **Note:** PHP's built-in server ignores `.htaccess`, so it doesn't block access to `data/`. Use it for local development only. On nginx, add `location /data/ { deny all; }` to the server config.

### Load demo data (optional)

To try the app with realistic data, run:

```bash
php tools/seed-demo.php
```

This creates two demo accounts, `alex@demo.com` (GBP) and `jordan@demo.com` (USD), both with the password `demo1234`, filled with a year's worth of transactions up to today. Running it again replaces the demo accounts with fresh data and leaves other accounts alone.

### Run the tests

```bash
composer install
composer test
```

## Project structure

```
├── index.php            # entry point - sends you to the dashboard or login
├── app/
│   ├── functions.php    # shared helpers: storage, auth, CSRF, CSV
│   ├── auth.php         # included by every logged-in page
│   ├── login.php, register.php, logout.php
│   ├── dashboard.php, transactions.php, add-transaction.php
│   ├── categories.php, reports.php, account.php
│   └── includes/        # header, sidebar, footer
├── css/                 # main styles and dark theme
├── js/                  # charts and AJAX navigation
├── data/                # JSON storage (user data is git-ignored)
└── tests/               # PHPUnit tests
```

## Possible improvements

- Move storage from JSON files to a relational database (MySQL or SQLite via PDO) for proper concurrency and querying
- Edit and delete existing transactions
- Budgets and spending alerts per category
- Live exchange rates, so totals across currencies can be converted
- Login rate limiting

## License

[MIT](LICENSE)