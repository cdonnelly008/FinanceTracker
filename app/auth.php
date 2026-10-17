<?php
require_once __DIR__ . '/functions.php';
startSecureSession();

// makes sure the json data files exist before anything else tries to use them
initialiseDataFiles();

// if they arent logged in and dont have a valid remember me cookie, send them back to login
if (!authenticateRememberedUser()) {
    header('Location: login.php');
    exit();
}
