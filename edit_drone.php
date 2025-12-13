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
    header('Location: admin_panel.php?error=drone_not_found');
    exit();
}

// Check if drone is already phased out
if ($drone['status'] === 'phased_out') {
    header('Location: admin_panel.php?error=drone_already_phased_out');
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

// Initialize variables
$error = '';
$success = '';
$price_changed_notice = '';

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
        
        // Check if price changed (allow small floating point differences)
        $old_price = floatval($drone['PricePerDay']);
        $price_changed = abs($old_price - $price_per_day) > 0.001;
        
        // Check if drone has active rentals
        $rental_check = $pdo->prepare("
            SELECT * FROM rentals 
            WHERE DroneID = ? 
            AND RentEnd >= NOW()
            AND status = 'active'
        ");
        $rental_check->execute([$drone_id]);
        $has_active_rentals = $rental_check->rowCount() > 0;
        
        if ($price_changed && $has_active_rentals) {
            $error = "Cannot change price for this drone because it has active rentals. Please wait until all rentals are completed or cancel them first.";
        } else if ($price_changed) {
            // Price changed - create new drone and phase out old one
            
            // 1. Start transaction
            $pdo->beginTransaction();
            
            try {
                // 2. Create new drone with new price
                $insertStmt = $pdo->prepare("
                    INSERT INTO drones 
                    (Brand, Model, CategoryID, WingTypeID, Size, PricePerDay, 
                     QuantityAvailable, PayloadCapacityID, PowerSourceID, MotorTypeID,
                     UsageCase, ReleaseDate, ImageURL, Description, status, phased_from_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?)
                ");
                
                $insertStmt->execute([
                    $brand, 
                    $model, 
                    $category_id, 
                    $wing_type_id,
                    $drone['Size'],
                    $price_per_day, 
                    $quantity_available,
                    $drone['PayloadCapacityID'],
                    $drone['PowerSourceID'],
                    $drone['MotorTypeID'],
                    $drone['UsageCase'],
                    $drone['ReleaseDate'],
                    $drone['ImageURL'], // Copy image URL initially
                    $description, 
                    $drone_id  // Set phased_from_id to the old drone ID
                ]);
                
                $new_drone_id = $pdo->lastInsertId();
                
                // 3. Copy image file with new name (if exists)
                if (!empty($drone['ImageURL'])) {
                    $old_image_path = 'images/' . $drone['ImageURL'];
                    if (file_exists($old_image_path)) {
                        $ext = pathinfo($old_image_path, PATHINFO_EXTENSION);
                        $new_filename = 'drone_' . $new_drone_id . '.' . $ext;
                        $new_image_path = 'images/' . $new_filename;
                        
                        if (copy($old_image_path, $new_image_path)) {
                            // Update new drone with new image filename
                            $pdo->prepare("UPDATE drones SET ImageURL = ? WHERE DroneID = ?")
                                ->execute([$new_filename, $new_drone_id]);
                        }
                    }
                }
                
                // 4. Handle uploaded image (if provided) - apply to new drone
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $image = $_FILES['image'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (in_array($image['type'], $allowed_types) && $image['size'] <= $max_size) {
                        // Generate unique filename for new drone
                        $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
                        $filename = 'drone_' . $new_drone_id . '_' . time() . '.' . $ext;
                        $upload_path = 'images/' . $filename;
                        
                        if (move_uploaded_file($image['tmp_name'], $upload_path)) {
                            // Update new drone with uploaded image
                            $pdo->prepare("UPDATE drones SET ImageURL = ? WHERE DroneID = ?")
                                ->execute([$filename, $new_drone_id]);
                            
                            // Delete the copied image if we created one
                            if (isset($new_image_path) && file_exists($new_image_path)) {
                                unlink($new_image_path);
                            }
                        }
                    }
                }
                
                // 5. Phase out the old drone
                $updateStmt = $pdo->prepare("
                    UPDATE drones 
                    SET status = 'phased_out', 
                        QuantityAvailable = 0,
                        phased_from_id = CASE 
                            WHEN phased_from_id IS NULL OR phased_from_id = 0 
                            THEN DroneID 
                            ELSE phased_from_id 
                        END
                    WHERE DroneID = ?
                ");
                
                $updateStmt->execute([$drone_id]);
                
                // 6. Commit transaction
                $pdo->commit();
                
                // 7. Log the action
                logEvent($_SESSION['Email'], 
                    "Price changed for drone #{$drone_id} ({$drone['Brand']} {$drone['Model']}). " .
                    "Created new drone #{$new_drone_id} with price \${$price_per_day} and phased out old drone.");
                
                // 8. Redirect to show new drone with success message
                header('Location: admin_panel.php?success=price_updated&new_id=' . $new_drone_id . '&old_id=' . $drone_id);
                exit();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                throw $e;
            }
            
        } else {
            // Price didn't change - normal update
            
            // 1. Update drone in database
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
            
            // 2. Handle image upload if provided
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
            
            // 3. Log the action
            logEvent($_SESSION['Email'], "Edited drone #{$drone_id}: {$brand} {$model}");
            
            // 4. Redirect back to admin panel with success message
            header('Location: admin_panel.php?success=1');
            exit();
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
} else {
    // On initial load, check if price change would trigger phasing out
    // This is just for informational display
    if (isset($_POST['price_per_day'])) {
        $new_price = floatval($_POST['price_per_day']);
        $old_price = floatval($drone['PricePerDay']);
        if (abs($old_price - $new_price) > 0.001) {
            $price_changed_notice = "⚠️ Changing the price will create a new drone entry and phase out this current drone.";
        }
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
        body { 
            background: #f5f7fa !important; 
            padding-top: 0 !important; 
            margin: 0 !important;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .edit-container {
            max-width: 1000px;
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
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .back-link {
            color: #3498db;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 4px;
            background: #e8f4fc;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: #3498db;
            color: white;
            text-decoration: none;
        }
        
        .drone-info-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .drone-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .form-preview {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .current-image {
            flex: 1;
            max-width: 300px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .price-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-warning i {
            font-size: 1.2em;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .form-full {
            grid-column: 1 / -1;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn-submit {
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s ease;
        }
        
        .btn-submit:hover {
            background: #2980b9;
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: #7f8c8d;
            color: white;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .form-preview {
                flex-direction: column;
            }
            
            .current-image {
                max-width: 100%;
            }
            
            .edit-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn-submit, .btn-cancel {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <div class="edit-header">
            <div>
                <h1 style="margin: 0 0 5px 0; color: #2c3e50;">Edit Drone</h1>
                <p style="margin: 0; color: #7f8c8d;">
                    Editing: <strong><?php echo htmlspecialchars($drone['Brand'] . ' ' . $drone['Model']); ?></strong>
                    (ID: #<?php echo $drone_id; ?>)
                </p>
            </div>
            <div>
                <a href="admin_panel.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Admin Panel
                </a>
            </div>
        </div>
        
        <!-- Current Drone Information -->
        <div class="drone-info-card">
            <h3 style="margin-top: 0; color: #2c3e50;">Current Drone Information</h3>
            <div class="drone-info-grid">
                <div class="info-item">
                    <span class="info-label">Current Price/Day:</span>
                    <span class="info-value">$<?php echo number_format($drone['PricePerDay'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value" style="color: #27ae60;"><?php echo ucfirst($drone['status']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Quantity Available:</span>
                    <span class="info-value"><?php echo $drone['QuantityAvailable']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Category:</span>
                    <span class="info-value"><?php echo htmlspecialchars($drone['CategoryName']); ?></span>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message" style="
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
                border: 1px solid #f5c6cb;
                display: flex;
                align-items: center;
                gap: 10px;
            ">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($price_changed_notice): ?>
            <div class="price-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Important Notice:</strong> <?php echo $price_changed_notice; ?>
                    <div style="margin-top: 5px; font-size: 0.9em;">
                        The new drone will inherit all other details from this drone.
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="form-preview">
            <div class="current-image">
                <h3 style="margin-top: 0; color: #2c3e50;">Current Image</h3>
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
                <form action="" method="POST" enctype="multipart/form-data" id="editDroneForm">
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
                                   value="<?php echo htmlspecialchars($drone['PricePerDay']); ?>" required
                                   id="priceInput"
                                   onchange="checkPriceChange()">
                            <small style="color: #7f8c8d; font-size: 0.85em; display: block; margin-top: 5px;">
                                Current: $<?php echo number_format($drone['PricePerDay'], 2); ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>Quantity Available:</label>
                            <input type="number" name="quantity_available" min="0" 
                                   value="<?php echo htmlspecialchars($drone['QuantityAvailable']); ?>" required>
                        </div>
                        
                        <div class="form-group form-full">
                            <label>Update Image (Optional):</label>
                            <input type="file" name="image" accept="image/*" id="imageInput">
                            <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                                Leave empty to keep current image. Max size: 5MB (JPG, PNG, GIF)
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
                    
                    <div class="button-group">
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="admin_panel.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function checkPriceChange() {
            const priceInput = document.getElementById('priceInput');
            const currentPrice = <?php echo $drone['PricePerDay']; ?>;
            const newPrice = parseFloat(priceInput.value);
            
            if (Math.abs(currentPrice - newPrice) > 0.001) {
                // Show warning
                let warning = document.querySelector('.price-warning');
                if (!warning) {
                    warning = document.createElement('div');
                    warning.className = 'price-warning';
                    warning.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Important Notice:</strong> Changing the price will create a new drone entry and phase out this current drone.
                            <div style="margin-top: 5px; font-size: 0.9em;">
                                The new drone will inherit all other details from this drone.
                            </div>
                        </div>
                    `;
                    
                    const form = document.querySelector('.edit-form');
                    form.insertBefore(warning, form.firstChild);
                }
                
                // Update submit button text
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '<i class="fas fa-exchange-alt"></i> Update Price (Create New Drone)';
                submitBtn.style.background = '#e67e22';
            } else {
                // Remove warning if present
                const warning = document.querySelector('.price-warning');
                if (warning) {
                    warning.remove();
                }
                
                // Reset submit button
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                submitBtn.style.background = '';
            }
        }
        
        // Check on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkPriceChange();
        });
        
        // Add confirmation for price changes
        document.getElementById('editDroneForm').addEventListener('submit', function(e) {
            const priceInput = document.getElementById('priceInput');
            const currentPrice = <?php echo $drone['PricePerDay']; ?>;
            const newPrice = parseFloat(priceInput.value);
            
            if (Math.abs(currentPrice - newPrice) > 0.001) {
                if (!confirm('⚠️ Changing the price will create a new drone and phase out this current drone. Are you sure you want to continue?')) {
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        });
    </script>
</body>
</html>