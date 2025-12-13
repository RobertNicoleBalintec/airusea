<?php
// edit_drone.php
require 'auth.php';
requireLogin();
$isAdmin = isAdmin();

if (!$isAdmin) {
    header('Location: dashboard.php');
    exit();
}

require 'db.php';
require 'logger.php';

// Get drone ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_panel.php');
    exit();
}

$drone_id = intval($_GET['id']);

// Fetch drone details
$stmt = $pdo->prepare("
    SELECT d.*, c.CategoryName, w.WingTypeName 
    FROM drones d
    LEFT JOIN categories c ON d.CategoryID = c.CategoryID
    LEFT JOIN wingtype w ON d.WingTypeID = w.WingTypeID
    WHERE d.DroneID = ?
");
$stmt->execute([$drone_id]);
$drone = $stmt->fetch();

if (!$drone) {
    header('Location: admin_panel.php');
    exit();
}

// Fetch all categories and wing types for dropdowns
$categories = $pdo->query("SELECT * FROM categories ORDER BY CategoryName");
$wingTypes = $pdo->query("SELECT * FROM wingtype ORDER BY WingTypeName");

// Fetch user's name for header
$user_stmt = $pdo->prepare("SELECT Name FROM users WHERE UserID = ?");
$user_stmt->execute([$_SESSION['UserID']]);
$user = $user_stmt->fetch();
$display_name = !empty($user['Name']) ? $user['Name'] : $_SESSION['Email'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model']);
        $category_id = intval($_POST['category_id']);
        $wing_type_id = intval($_POST['wing_type_id']);
        $price_per_day = floatval($_POST['price_per_day']);
        $quantity_available = intval($_POST['quantity_available']);
        $description = trim($_POST['description'] ?? '');
        
        // Update drone in database
        $updateStmt = $pdo->prepare("
            UPDATE drones 
            SET Brand = ?, Model = ?, CategoryID = ?, WingTypeID = ?, 
                PricePerDay = ?, QuantityAvailable = ?, Description = ?
            WHERE DroneID = ?
        ");
        
        $updateStmt->execute([
            $brand, $model, $category_id, $wing_type_id,
            $price_per_day, $quantity_available, $description, $drone_id
        ]);
        
        // Handle image upload if provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image = $_FILES['image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (in_array($image['type'], $allowed_types) && $image['size'] <= $max_size) {
                // Generate unique filename
                $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
                $filename = 'drone_' . $drone_id . '_' . time() . '.' . $ext;
                $upload_path = 'images/' . $filename;
                
                if (move_uploaded_file($image['tmp_name'], $upload_path)) {
                    // Update image URL in database
                    $pdo->prepare("UPDATE drones SET ImageURL = ? WHERE DroneID = ?")
                        ->execute([$filename, $drone_id]);
                    
                    // Delete old image if exists
                    if (!empty($drone['ImageURL']) && file_exists('images/' . $drone['ImageURL'])) {
                        unlink('images/' . $drone['ImageURL']);
                    }
                }
            }
        }
        
        // Log the action
        logEvent($_SESSION['Email'], "Edited drone #{$drone_id}: {$brand} {$model}");
        
        // Redirect back to admin panel with success message
        header('Location: admin_panel.php?success=1');
        exit();
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Drone | Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        header { display: none !important; }
        body { background: #f5f7fa !important; padding-top: 0 !important; margin: 0 !important; }
        
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .edit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }
        
        .back-link {
            color: #3498db;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .form-preview {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .current-image {
            flex: 1;
            max-width: 300px;
        }
        
        .current-image img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .image-placeholder {
            width: 100%;
            height: 200px;
            background: #ecf0f1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7f8c8d;
            font-size: 3rem;
        }
        
        .edit-form {
            flex: 2;
        }
        
        @media (max-width: 768px) {
            .form-preview {
                flex-direction: column;
            }
            
            .current-image {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <div class="edit-header">
            <div>
                <h1>Edit Drone</h1>
                <p>Editing: <strong><?php echo htmlspecialchars($drone['Brand'] . ' ' . $drone['Model']); ?></strong></p>
            </div>
            <div>
                <a href="admin_panel.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Admin Panel
                </a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-preview">
            <div class="current-image">
                <h3>Current Image</h3>
                <?php if (!empty($drone['ImageURL']) && file_exists('images/' . $drone['ImageURL'])): ?>
                    <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" 
                         alt="<?php echo htmlspecialchars($drone['Brand'] . ' ' . $drone['Model']); ?>">
                <?php else: ?>
                    <div class="image-placeholder">
                        <i class="fas fa-helicopter"></i>
                    </div>
                <?php endif; ?>
                <p style="text-align: center; margin-top: 10px; color: #7f8c8d; font-size: 0.9rem;">
                    Current drone image
                </p>
            </div>
            
            <div class="edit-form">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Brand:</label>
                            <input type="text" name="brand" value="<?php echo htmlspecialchars($drone['Brand']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Model:</label>
                            <input type="text" name="model" value="<?php echo htmlspecialchars($drone['Model']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Category:</label>
                            <select name="category_id" required>
                                <?php
                                $categories->execute(); // Reset pointer
                                while ($category = $categories->fetch()) {
                                    $selected = ($category['CategoryID'] == $drone['CategoryID']) ? 'selected' : '';
                                    echo "<option value='{$category['CategoryID']}' {$selected}>{$category['CategoryName']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Wing Type:</label>
                            <select name="wing_type_id" required>
                                <?php
                                $wingTypes->execute(); // Reset pointer
                                while ($wing = $wingTypes->fetch()) {
                                    $selected = ($wing['WingTypeID'] == $drone['WingTypeID']) ? 'selected' : '';
                                    echo "<option value='{$wing['WingTypeID']}' {$selected}>{$wing['WingTypeName']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Price per Day ($):</label>
                            <input type="number" step="0.01" name="price_per_day" 
                                   value="<?php echo htmlspecialchars($drone['PricePerDay']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Quantity Available:</label>
                            <input type="number" name="quantity_available" min="1" 
                                   value="<?php echo htmlspecialchars($drone['QuantityAvailable']); ?>" required>
                        </div>
                        
                        <div class="form-group form-full">
                            <label>Update Image (Optional):</label>
                            <input type="file" name="image" accept="image/*">
                            <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                                Leave empty to keep current image
                            </small>
                        </div>
                        
                        <div class="form-group form-full">
                            <label>Description:</label>
                            <textarea name="description" rows="4" 
                                      placeholder="Enter drone description..."><?php 
                                echo htmlspecialchars($drone['Description'] ?? ''); 
                            ?></textarea>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="admin_panel.php" class="btn" style="background: #95a5a6;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- REMOVED THE REDUNDANT DRONE INFORMATION SECTION -->
        
    </div>
</body>
</html>