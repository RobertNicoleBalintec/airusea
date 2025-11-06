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
    <p>Welcome, <?= htmlspecialchars($_SESSION['Email']) ?> | <a href="logout.php">Logout</a></p>

    <h3>Add a New Drone</h3>
    <form action="add_drone.php" method="POST" enctype="multipart/form-data">
        <label>Brand:</label><br>
        <input type="text" name="brand" required><br><br>
        
        <label>Model:</label><br>
        <input type="text" name="model" required><br><br>
        
        <label>Category:</label><br>
        <select name="category_id" required>
            <?php
            $categories = $pdo->query("SELECT * FROM categories");
            while ($category = $categories->fetch()) {
                echo "<option value='{$category['CategoryID']}'>{$category['CategoryName']}</option>";
            }
            ?>
        </select><br><br>
        
        <label>Wing Type:</label><br>
        <select name="wing_type_id" required>
            <?php
            $wingTypes = $pdo->query("SELECT * FROM wingtype");
            while ($wing = $wingTypes->fetch()) {
                echo "<option value='{$wing['WingTypeID']}'>{$wing['WingTypeName']}</option>";
            }
            ?>
        </select><br><br>

        <label>Price per Day:</label><br>
        <input type="number" step="0.01" name="price_per_day" required><br><br>

        <label>Quantity Available:</label><br>
        <input type="number" name="quantity_available" required><br><br>

        <label>Image URL (for demo purposes):</label><br>
        <input type="file" name="image" required><br><br>

        <button type="submit">Add Drone</button>
    </form>

    <h3>Remove a Drone</h3>
    <table>
        <tr>
            <th>Drone ID</th>
            <th>Brand</th>
            <th>Model</th>
            <th>Action</th>
        </tr>
        <?php
        $stmt = $pdo->query("SELECT * FROM drones");
        while ($drone = $stmt->fetch()) {
            echo "<tr>
                <td>{$drone['DroneID']}</td>
                <td>{$drone['Brand']}</td>
                <td>{$drone['Model']}</td>
                <td><a href='remove_drone.php?id={$drone['DroneID']}'>Remove</a></td>
            </tr>";
        }
        ?>
    </table>
</body>
</html>
