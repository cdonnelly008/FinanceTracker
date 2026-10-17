<?php
// give the page a fallback title if one hasnt been set already
$pageTitle = $pageTitle ?? 'FinanceTracker';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitiseOutput($pageTitle); ?> | FinanceTracker</title>

    <!-- set the theme early so the page doesnt flash the wrong colours -->
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('financeTrackerTheme');
                var theme = savedTheme === 'dark' ? 'theme-dark' : 'theme-light';
                document.documentElement.className = theme;
            } catch (e) {
                document.documentElement.className = 'theme-light';
            }
        })();
    </script>

    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/dark-theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>

<body>
    <div id="dashboard-wrapper">
