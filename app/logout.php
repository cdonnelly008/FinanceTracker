<?php
session_start();
require_once __DIR__ . '/functions.php';

// clears the saved remember me token as well so logout fully signs them out
initialiseDataFiles();
clearRememberMeForCurrentUser();

// clear the normal php session too
session_unset();
session_destroy();

header('Location: login.php');
exit();
