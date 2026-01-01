<?php
// auth.php - FINAL SIMPLE VERSION
session_start();

// Always treat userID 1 as super-admin
function isAdmin() {
    if (!isset($_SESSION['UserID'])) return false;
    
    // User 1 is always admin
    if ($_SESSION['UserID'] == 1) return true;
    
    // Check session flag
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) return true;
    
    // Check role
    if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') return true;
    if (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin') return true;
    
    return false;
}

function isSuperAdmin() {
    if (!isset($_SESSION['UserID'])) return false;
    
    // User 1 is always super-admin
    if ($_SESSION['UserID'] == 1) return true;
    
    // Check session flag
    if (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin') return true;
    
    return false;
}

function requireLogin() {
    if (!isset($_SESSION['UserID'])) {
        header('Location: index_login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}

function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        header('Location: admin_panel.php?error=superadmin_required');
        exit();
    }
}

// Auto-set flags for user 1
if (isset($_SESSION['UserID']) && $_SESSION['UserID'] == 1) {
    $_SESSION['is_admin'] = 1;
    $_SESSION['role'] = 'superadmin';
}
?>