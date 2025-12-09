<?php
// Check if session is not already started before starting it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['UserID'])) {
        header('Location: index.php');
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['UserID']) && $_SESSION['is_admin'] == 1;
}
?>