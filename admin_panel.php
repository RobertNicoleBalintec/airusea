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

// ADD THIS: Fetch user's name from database
$user_stmt = $pdo->prepare("SELECT Name FROM users WHERE UserID = ?");
$user_stmt->execute([$_SESSION['UserID']]);
$user = $user_stmt->fetch();
$display_name = !empty($user['Name']) ? $user['Name'] : $_SESSION['Email'];

// Check if database tables exist
try {
    $categories = $pdo->query("SELECT * FROM categories");
    $wingTypes = $pdo->query("SELECT * FROM wingtype");
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel | Airusea</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_styles.css"> <!-- NEW STYLESHEET -->
    <link rel="stylesheet" href="media.css">
    <style>
        /* Hide main site header on admin pages */
        header {
            display: none !important;
        }
        
        /* Force admin page styling */
        body {
            background: #f5f7fa !important;
            padding-top: 0 !important;
            margin: 0 !important;
        }
    </style>
</head>
<body class="admin-page">
    <div class="admin-container">
        <!-- Admin Header -->
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <div class="admin-user-info">
                <div>
                    Welcome, <strong><?= htmlspecialchars($display_name) ?></strong> (Administrator) <!-- CHANGED HERE -->
                </div>
                <div class="admin-links">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>

        <!-- Manage Users Section -->
        <div class="admin-section">
            <h2 class="section-title">👥 Manage Users</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Email</th>
                        <th>Admin Status</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $pdo->query("SELECT * FROM users ORDER BY UserID");
                    while ($user = $users->fetch()) {
                        echo "<tr>";
                        echo "<td><strong>#{$user['UserID']}</strong></td>";
                        echo "<td>{$user['Email']}</td>";
                        echo "<td>";
                        echo "<span class='status-badge " . ($user['is_admin'] ? 'status-yes' : 'status-no') . "'>";
                        echo $user['is_admin'] ? 'Yes' : 'No';
                        echo "</span>";
                        echo "</td>";
                        echo "<td>";
                        if (isset($user['RegistrationDate']) && !empty($user['RegistrationDate'])) {
                            echo date('M d, Y', strtotime($user['RegistrationDate']));
                        } else {
                            echo '<span style="color: #95a5a6; font-style: italic;">Not set</span>';
                        }
                        echo "</td>";
                        echo "<td>
                                <a href='manage_user.php?user_id={$user['UserID']}' 
                                class='btn btn-manage'>
                                🔧 Manage
                                </a>
                            </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Add New Drone Section -->
        <div class="admin-section">
            <h2 class="section-title">🚁 Add New Drone</h2>
            <form action="add_drone.php" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Brand:</label>
                        <input type="text" name="brand" required placeholder="e.g., DJI">
                    </div>
                    
                    <div class="form-group">
                        <label>Model:</label>
                        <input type="text" name="model" required placeholder="e.g., Mavic 3">
                    </div>
                    
                    <div class="form-group">
                        <label>Category:</label>
                        <select name="category_id" required>
                            <?php
                            if ($categories->rowCount() > 0) {
                                $categories->execute(); // Reset pointer
                                while ($category = $categories->fetch()) {
                                    echo "<option value='{$category['CategoryID']}'>{$category['CategoryName']}</option>";
                                }
                            } else {
                                echo "<option value=''>No categories found</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Wing Type:</label>
                        <select name="wing_type_id" required>
                            <?php
                            if ($wingTypes->rowCount() > 0) {
                                $wingTypes->execute(); // Reset pointer
                                while ($wing = $wingTypes->fetch()) {
                                    echo "<option value='{$wing['WingTypeID']}'>{$wing['WingTypeName']}</option>";
                                }
                            } else {
                                echo "<option value=''>No wing types found</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Price per Day ($):</label>
                        <input type="number" step="0.01" name="price_per_day" required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity Available:</label>
                        <input type="number" name="quantity_available" min="1" required placeholder="1">
                    </div>
                    
                    <div class="form-group form-full">
                        <label>Drone Image:</label>
                        <input type="file" name="image" accept="image/*" required>
                        <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                            Recommended: JPG, PNG, or GIF format
                        </small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-submit">➕ Add Drone</button>
            </form>
        </div>

        <!-- Current Drones Section -->
        <div class="admin-section">
            <h2 class="section-title">📋 Current Drones Inventory</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Drone ID</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Category</th>
                        <th>Price/Day</th>
                        <th>Quantity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT d.*, c.CategoryName, w.WingTypeName 
                            FROM drones d
                            LEFT JOIN categories c ON d.CategoryID = c.CategoryID
                            LEFT JOIN wingtype w ON d.WingTypeID = w.WingTypeID
                            ORDER BY d.DroneID
                        ");
                        
                        if ($stmt->rowCount() > 0) {
                            while ($drone = $stmt->fetch()) {
                                echo "<tr>
                                    <td><strong>#{$drone['DroneID']}</strong></td>
                                    <td><strong>{$drone['Brand']}</strong></td>
                                    <td>{$drone['Model']}</td>
                                    <td>{$drone['CategoryName']}</td>
                                    <td><strong>$" . number_format($drone['PricePerDay'], 2) . "</strong></td>
                                    <td>
                                        <span style='padding: 5px 10px; background: #e8f4fc; border-radius: 4px; font-weight: 500;'>
                                            {$drone['QuantityAvailable']}
                                        </span>
                                    </td>
                                    <td>
                                        <a href='remove_drone.php?id={$drone['DroneID']}' 
                                           class='btn btn-remove'
                                           onclick='return confirm(\"Are you sure you want to remove \\\"{$drone['Brand']} {$drone['Model']}\\\"?\")'>
                                           🗑️ Remove
                                        </a>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align: center; padding: 30px; color: #7f8c8d;'>
                                    No drones in inventory. Add your first drone above.
                                  </td></tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='7' style='text-align: center; padding: 20px; color: #e74c3c;'>
                                Error loading drones: " . htmlspecialchars($e->getMessage()) . "
                              </td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>