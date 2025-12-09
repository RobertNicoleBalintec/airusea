<?php
require 'auth.php';
requireLogin();

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

require 'db.php';
require 'logger.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model']);
        $category_id = $_POST['category_id'];
        $wing_type_id = $_POST['wing_type_id'];
        $price_per_day = floatval($_POST['price_per_day']);
        $quantity_available = intval($_POST['quantity_available']);
        
        // Handle image upload
        $image_name = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                die("Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.");
            }
            
            $image_name = uniqid() . '_' . basename($_FILES['image']['name']);
            $upload_dir = 'images/';
            $upload_path = $upload_dir . $image_name;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                die("Failed to upload image.");
            }
        } else {
            die("Image upload failed.");
        }
        
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO drones (Brand, Model, CategoryID, WingTypeID, PricePerDay, QuantityAvailable, ImageURL) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $brand, 
            $model, 
            $category_id, 
            $wing_type_id, 
            $price_per_day, 
            $quantity_available, 
            $image_name
        ]);
        
        logEvent($_SESSION['UserID'], "Added new drone: $brand $model");
        
        header('Location: admin_panel.php?success=1');
        exit();
        
    } catch (Exception $e) {
        die("Error adding drone: " . $e->getMessage());
    }
} else {
    header('Location: admin_panel.php');
    exit();
}
?>