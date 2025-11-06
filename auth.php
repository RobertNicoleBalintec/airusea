<?php
session_start();

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