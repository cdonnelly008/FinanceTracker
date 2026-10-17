<?php
// Fills the app with demo accounts and realistic transactions, for screenshots
// and trying the app out. Run from the project folder:
//
//     php tools/seed-demo.php
//
// Safe to run again: it removes the old demo accounts (and their transactions
// and categories) and creates fresh ones dated up to today. Other accounts are
// never touched.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../app/functions.php';

initialiseDataFiles();

$dataDir = __DIR__ . '/../data';
$usersFile = $dataDir . '/users.json';
$transactionsFile = $dataDir . '/transactions.json';
$categoriesFile = $dataDir . '/categories.json';

$demoPassword = 'demo1234';

// each demo account has its own currency and spending habits
$profiles = [
    [
        'firstName' => 'Alex',
        'lastName' => 'Morgan',
        'email' => 'alex@demo.com',
        'currency' => 'GBP',
        'seed' => 1,
        'salary' => [2650, 'Salary - Brightwave Ltd'],
        'side' => [[180, 420], ['Freelance web project', 'Sold old laptop on eBay', 'Tutoring session']],
        'monthly' => [
            [2, 'Bills', 'Rent', 850],
            [3, 'Bills', 'Council tax', 132],
            [8, 'Bills', 'Octopus Energy', 74],
            [12, 'Bills', 'Virgin Media broadband', 32],
            [15, 'Bills', 'Phone contract - EE', 24],
            [5, 'Entertainment', 'Netflix', 10.99],
            [9, 'Entertainment', 'Spotify Premium', 11.99],
            [2, 'Health & Fitness', 'PureGym membership', 24.99],
        ],
        'groceries' => [[28, 85], ['Tesco', 'Sainsbury\'s', 'Aldi', 'Lidl']],
        'eatingOut' => [[8, 45], ['Nando\'s', 'Pret A Manger', 'Wagamama', 'Greggs', 'Deliveroo', 'Local pub']],
        'transport' => [[3, 12], ['Bus fare', 'Train ticket', 'Uber', 'Oyster top-up']],
        'fun' => [[9, 60], ['Cinema - Odeon', 'Concert tickets', 'Steam game', 'Bowling', 'Football match']],
        'shopping' => [[15, 120], ['Amazon order', 'ASOS', 'Primark', 'Boots', 'Argos']],
    ],
    [
        'firstName' => 'Jordan',
        'lastName' => 'Lee',
        'email' => 'jordan@demo.com',
        'currency' => 'USD',
        'seed' => 2,
        'salary' => [3900, 'Payroll - Northside Health'],
        'side' => [[150, 600], ['Weekend photography gig', 'Tax refund', 'Sold bike on Craigslist']],
        'monthly' => [
            [2, 'Bills', 'Apartment rent', 1450],
            [7, 'Bills', 'Electric bill', 96],
            [10, 'Bills', 'Comcast internet', 65],
            [18, 'Bills', 'Verizon wireless', 70],
            [20, 'Bills', 'Car insurance', 128],
            [4, 'Entertainment', 'Hulu', 17.99],
            [11, 'Entertainment', 'Spotify Premium', 11.99],
            [2, 'Health & Fitness', 'Planet Fitness', 24.99],
        ],
        'groceries' => [[45, 140], ['Trader Joe\'s', 'Kroger', 'Whole Foods', 'Costco']],
        'eatingOut' => [[10, 60], ['Chipotle', 'Starbucks', 'Taco Bell', 'DoorDash', 'Sushi place', 'Diner brunch']],
        'transport' => [[25, 60], ['Gas station', 'Uber', 'Parking', 'Car wash']],
        'fun' => [[12, 90], ['Movie tickets', 'Baseball game', 'Bowling', 'Escape room', 'Video game']],
        'shopping' => [[20, 180], ['Amazon order', 'Target', 'Best Buy', 'Old Navy', 'CVS']],
    ],
];

$users = loadJsonData($usersFile);
$transactions = loadJsonData($transactionsFile);
$categories = loadJsonData($categoriesFile);

// remove anything left over from a previous run
$demoEmails = array_column($profiles, 'email');
$oldDemoIds = [];
foreach ($users as $user) {
    if (in_array(normaliseEmail((string)($user['email'] ?? '')), $demoEmails, true)) {
        $oldDemoIds[] = (int)$user['userId'];
    }
}

$users = array_values(array_filter($users, fn($u) => !in_array((int)($u['userId'] ?? 0), $oldDemoIds, true)));
$transactions = array_values(array_filter($transactions, fn($t) => !in_array((int)($t['userId'] ?? 0), $oldDemoIds, true)));
$categories = array_values(array_filter($categories, fn($c) => !in_array((int)($c['userId'] ?? 0), $oldDemoIds, true) || ($c['userId'] ?? null) === null));

// random amount between min and max, to the penny
function randomAmount(array $range): float
{
    return mt_rand((int)($range[0] * 100), (int)($range[1] * 100)) / 100;
}

function pick(array $items)
{
    return $items[array_rand($items)];
}

$today = new DateTimeImmutable('today');
$start = new DateTimeImmutable($today->format('Y') . '-01-01');

foreach ($profiles as $profile) {
    mt_srand($profile['seed']);

    $userId = getNextId($users, 'userId');
    $users[] = [
        'userId' => $userId,
        'firstName' => $profile['firstName'],
        'lastName' => $profile['lastName'],
        'email' => $profile['email'],
        'password' => password_hash($demoPassword, PASSWORD_DEFAULT),
        'defaultCurrency' => $profile['currency'],
    ];

    $newTransactions = [];
    $add = function (DateTimeImmutable $date, string $category, string $description, float $amount, string $type) use (&$newTransactions) {
        $newTransactions[] = [
            'date' => $date->format('Y-m-d'),
            'description' => $description,
            'category' => $category,
            'amount' => round($amount, 2),
            'type' => $type,
        ];
    };

    for ($day = $start; $day <= $today; $day = $day->modify('+1 day')) {
        $dayOfMonth = (int)$day->format('j');
        $weekday = (int)$day->format('N');

        // paid on the 1st so "this month" on the dashboard always has income,
        // with a small raise partway through the year
        if ($dayOfMonth === 1) {
            $raise = (int)$day->format('n') >= 7 ? 1.04 : 1.0;
            $add($day, 'Income', $profile['salary'][1], $profile['salary'][0] * $raise, 'incoming');
        }

        // the odd bit of extra income
        if (mt_rand(1, 100) <= 3) {
            $add($day, 'Income', pick($profile['side'][1]), randomAmount($profile['side'][0]), 'incoming');
        }

        foreach ($profile['monthly'] as [$billDay, $category, $description, $amount]) {
            if ($dayOfMonth === $billDay) {
                // utility bills go up and down a bit, subscriptions don't
                $isVariable = in_array($description, ['Octopus Energy', 'Electric bill'], true);
                $add($day, $category, $description, $isVariable ? $amount * mt_rand(80, 125) / 100 : $amount, 'outgoing');
            }
        }

        // weekly shop on saturdays, plus a top-up shop midweek sometimes
        if ($weekday === 6 || ($weekday === 3 && mt_rand(1, 100) <= 40)) {
            $add($day, 'Food', 'Groceries - ' . pick($profile['groceries'][1]), randomAmount($profile['groceries'][0]), 'outgoing');
        }

        // eating out more often at the weekend
        if (mt_rand(1, 100) <= ($weekday >= 5 ? 45 : 18)) {
            $add($day, 'Food', pick($profile['eatingOut'][1]), randomAmount($profile['eatingOut'][0]), 'outgoing');
        }

        // commuting on weekdays
        if ($weekday <= 5 && mt_rand(1, 100) <= 55) {
            $add($day, 'Transport', pick($profile['transport'][1]), randomAmount($profile['transport'][0]), 'outgoing');
        }

        if (mt_rand(1, 100) <= 8) {
            $add($day, 'Entertainment', pick($profile['fun'][1]), randomAmount($profile['fun'][0]), 'outgoing');
        }

        if (mt_rand(1, 100) <= 9) {
            $add($day, 'Shopping', pick($profile['shopping'][1]), randomAmount($profile['shopping'][0]), 'outgoing');
        }
    }

    // a couple of one-off big purchases make the charts more interesting
    $add($start->modify('+4 months +10 days'), 'Shopping', 'New phone', $profile['currency'] === 'USD' ? 899 : 749, 'outgoing');
    $add($start->modify('+6 months +3 days'), 'Entertainment', 'Summer holiday', $profile['currency'] === 'USD' ? 1250 : 980, 'outgoing');

    // any category the demo uses that isn't a shared default becomes one of their own custom categories
    $visibleNames = array_map('strtolower', array_column(getCategoriesForUser($categories, $userId), 'name'));
    foreach (array_unique(array_column($newTransactions, 'category')) as $categoryName) {
        if (!in_array(strtolower($categoryName), $visibleNames, true)) {
            $categories[] = [
                'categoryId' => getNextId($categories, 'categoryId'),
                'name' => $categoryName,
                'userId' => $userId,
            ];
            $visibleNames[] = strtolower($categoryName);
        }
    }

    usort($newTransactions, fn($a, $b) => strcmp($a['date'], $b['date']));
    foreach ($newTransactions as $transaction) {
        $transactions[] = [
            'transactionId' => getNextId($transactions, 'transactionId'),
            'userId' => $userId,
            'date' => $transaction['date'],
            'description' => $transaction['description'],
            'category' => $transaction['category'],
            'amount' => $transaction['amount'],
            'currency' => $profile['currency'],
            'type' => $transaction['type'],
        ];
    }

    printf("%-18s %-4s %d transactions\n", $profile['email'], $profile['currency'], count($newTransactions));
}

saveJsonData($usersFile, $users);
saveJsonData($transactionsFile, $transactions);
saveJsonData($categoriesFile, $categories);

echo "\nDone. Log in with any demo email above and the password: {$demoPassword}\n";
