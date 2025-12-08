<?php
require 'auth.php';
requireLogin();

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

require 'db.php';
require 'logger.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_panel.php?error=invalid_id');
    exit();
}

$drone_id = intval($_GET['id']);

try {
    // Check if drone exists
    $stmt = $pdo->prepare("SELECT * FROM drones WHERE DroneID = ?");
    $stmt->execute([$drone_id]);
    $drone = $stmt->fetch();
    
    if (!$drone) {
        header('Location: admin_panel.php?error=drone_not_found');
        exit();
    }
    
    // Check if drone has active rentals
    $rental_check = $pdo->prepare("SELECT * FROM rentals WHERE DroneID = ? AND RentEnd >= NOW()");
    $rental_check->execute([$drone_id]);
    
    if ($rental_check->rowCount() > 0) {
        header('Location: admin_panel.php?error=drone_has_active_rentals');
        exit();
    }
    
    // Delete drone
    $delete_stmt = $pdo->prepare("DELETE FROM drones WHERE DroneID = ?");
    $delete_stmt->execute([$drone_id]);
    
    logAction($_SESSION['UserID'], "Removed drone ID: $drone_id ({$drone['Brand']} {$drone['Model']})");
    
    header('Location: admin_panel.php?success=removed');
    exit();
    
} catch (PDOException $e) {
    die("Error removing drone: " . $e->getMessage());
}
?>