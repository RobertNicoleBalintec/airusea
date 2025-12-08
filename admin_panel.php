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
    <link rel="stylesheet" href="media.css">
</head>
<body>
    <h2>Admin Panel</h2>
    <p>Welcome, <?= htmlspecialchars($_SESSION['Email']) ?> (Admin) | 
       <a href="dashboard.php">Dashboard</a> | 
       <a href="logout.php">Logout</a>
    </p>

    <!-- Add to admin_panel.php -->
<h3>Manage Users</h3>
<table border="1" cellpadding="10">
    <tr>
        <th>User ID</th>
        <th>Email</th>
        <th>Admin?</th>
        <th>Registration Date</th>
        <th>Actions</th>
    </tr>
    <?php
    $users = $pdo->query("SELECT * FROM users ORDER BY UserID");
    while ($user = $users->fetch()) {
        echo "<tr>";
        echo "<td>{$user['UserID']}</td>";
        echo "<td>{$user['Email']}</td>";
        echo "<td>" . ($user['is_admin'] ? '✅ Yes' : '❌ No') . "</td>";
        echo "<td>{$user['RegistrationDate']}</td>";
        echo "<td>
                <a href='safe_delete_user.php?user_id={$user['UserID']}' 
                   onclick='return confirm(\"Manage user {$user['Email']}?\")'>
                   🔧 Manage
                </a>
              </td>";
        echo "</tr>";
    }
    ?>
</table>

    <h3>Add a New Drone</h3>
    <form action="add_drone.php" method="POST" enctype="multipart/form-data">
        <label>Brand:</label><br>
        <input type="text" name="brand" required><br><br>
        
        <label>Model:</label><br>
        <input type="text" name="model" required><br><br>
        
        <label>Category:</label><br>
        <select name="category_id" required>
            <?php
            if ($categories->rowCount() > 0) {
                while ($category = $categories->fetch()) {
                    echo "<option value='{$category['CategoryID']}'>{$category['CategoryName']}</option>";
                }
            } else {
                echo "<option value=''>No categories found</option>";
            }
            ?>
        </select><br><br>
        
        <label>Wing Type:</label><br>
        <select name="wing_type_id" required>
            <?php
            if ($wingTypes->rowCount() > 0) {
                while ($wing = $wingTypes->fetch()) {
                    echo "<option value='{$wing['WingTypeID']}'>{$wing['WingTypeName']}</option>";
                }
            } else {
                echo "<option value=''>No wing types found</option>";
            }
            ?>
        </select><br><br>

        <label>Price per Day:</label><br>
        <input type="number" step="0.01" name="price_per_day" required><br><br>

        <label>Quantity Available:</label><br>
        <input type="number" name="quantity_available" min="1" required><br><br>

        <label>Image:</label><br>
        <input type="file" name="image" accept="image/*" required><br><br>

        <button type="submit">Add Drone</button>
    </form>

    <h3>Current Drones</h3>
    <table border="1" cellpadding="10">
        <tr>
            <th>Drone ID</th>
            <th>Brand</th>
            <th>Model</th>
            <th>Category</th>
            <th>Price/Day</th>
            <th>Quantity</th>
            <th>Action</th>
        </tr>
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
                        <td>{$drone['DroneID']}</td>
                        <td>{$drone['Brand']}</td>
                        <td>{$drone['Model']}</td>
                        <td>{$drone['CategoryName']}</td>
                        <td>\${$drone['PricePerDay']}</td>
                        <td>{$drone['QuantityAvailable']}</td>
                        <td>
                            <a href='remove_drone.php?id={$drone['DroneID']}' 
                               onclick='return confirm(\"Are you sure you want to remove this drone?\")'>
                               Remove
                            </a>
                        </td>
                    </tr>";
                }
            } else {
                echo "<tr><td colspan='7'>No drones found</td></tr>";
            }
        } catch (PDOException $e) {
            echo "<tr><td colspan='7'>Error loading drones: " . $e->getMessage() . "</td></tr>";
        }
        ?>
    </table>
</body>
</html>