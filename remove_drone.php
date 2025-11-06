<?php
require 'auth.php';
requireLogin();
$isAdmin = isAdmin();

if (!$isAdmin) {
    header('Location: dashboard.php');
    exit();
}

require 'db.php';
require 'logger.php';

// Ensure `logAction()` exists before calling it
if (!function_exists('logAction')) {
    function logAction($userId, $message) {
        // Placeholder function to prevent fatal errors
        error_log("User $userId: $message");
    }
}

if (isset($_GET['id'])) {
    $droneId = $_GET['id'];

    $stmt = $pdo->prepare("DELETE FROM drones WHERE DroneID = ?");
    $stmt->execute([$droneId]);

    logAction($_SESSION['UserID'], "Removed drone ID: $droneId");

    header('Location: admin_panel.php');
    exit();
} else {
    echo "<p style='color: red;'>Error: Drone ID not specified!</p>";
}
?>