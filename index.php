<?php
session_start();
require_once __DIR__ . '/app/functions.php';

initialiseDataFiles();
authenticateRememberedUser();

if (isset($_SESSION['user_id'])) {
    header('Location: app/dashboard.php');
    exit();
} else {
    header('Location: app/login.php');
    exit();
}
