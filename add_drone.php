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

// Check if logAction() exists in logger.php before calling
if (!function_exists('logAction')) {
    function logAction($userId, $message) {
        // Placeholder function to prevent fatal errors
        error_log("User $userId: $message");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate POST data to prevent undefined index warnings
    $brand = $_POST['brand'] ?? null;
    $model = $_POST['model'] ?? null;
    $categoryId = $_POST['CategoryID'] ?? null;
    $wingTypeId = $_POST['WingTypeID'] ?? null;
    $pricePerDay = $_POST['PricePerDay'] ?? null;
    $quantityAvailable = $_POST['QuantityAvailable'] ?? null;

    // Image upload handling
    $image = $_FILES['image']['name'] ?? null;
    if ($image) {
        $targetDir = "images/";
        $targetFile = $targetDir . basename($image);
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            echo "<p style='color: red;'>Error uploading image.</p>";
            exit();
        }
    } else {
        $image = "default.jpg"; // Fallback image if none is uploaded
    }

    // Insert drone into database
    $stmt = $pdo->prepare("INSERT INTO drones (Brand, Model, CategoryID, WingTypeID, PricePerDay, QuantityAvailable, ImageURL) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$brand, $model, $categoryId, $wingTypeId, $pricePerDay, $quantityAvailable, $image]);

    logAction($_SESSION['UserID'], "Added a new drone: $brand $model");

    header('Location: admin_panel.php');
    exit();
}
?>