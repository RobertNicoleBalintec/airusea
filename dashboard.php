<?php
// Start session and include necessary files
session_start();
require_once 'db.php'; // Database connection
require_once 'logger.php'; // Logger functionality

// Log page visit
logEvent($_SESSION['Email'], 'Accessed the dashboard');

// If the user is not logged in, redirect to the login page
if (!isset($_SESSION['UserID'])) {
    header("Location: index_login.php");
    exit();
}

// Query to get all drones
$stmt = $pdo->query("SELECT * FROM drones");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AirErusea | Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <img src="images/logo.png" alt="Airusea Logo" class="logo">
            <nav class="navbar">
                <a href="dashboard.php">Home</a>
                <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </nav>
        </div>
    </header>

    <section class="drones-section">
        <h2 class="section-title">Available Drones</h2>
        <div class="drones-container">
            <?php
            // Check if there are any drones available in the database
            if ($stmt->rowCount() > 0) {
                while ($drone = $stmt->fetch()) {
                    // Get the drone image URL from the database
                    $imageUrl = !empty($drone['ImageURL']) ? "images/" . $drone['ImageURL'] : 'images/default_image.jpg';
                    echo '<div class="drone-card">';
                    echo '<img src="' . $imageUrl . '" alt="Drone Image" class="drone-image">';
                    echo '<h3>' . htmlspecialchars($drone['Brand']) . ' ' . htmlspecialchars($drone['Model']) . '</h3>';
                    echo '<p>Price/Day: ₱' . number_format($drone['PricePerDay'], 2) . '</p>';
                    echo '<a href="rent.php?DroneID=' . $drone['DroneID'] . '" class="btn">Rent This Drone</a>';
                    echo '</div>';
                }
            } else {
                echo "<p>No drones available at the moment.</p>";
            }
            ?>
        </div>
    </section>
</body>
</html>
