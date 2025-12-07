<?php
session_start();

include('db.php');
include('logger.php');

logEvent("Accessed the main page.");

$stmt = $pdo->query("
    SELECT d.*, COUNT(r.RentalID) AS rent_count
    FROM drones d
    LEFT JOIN rentals r using (DroneID)
    GROUP BY d.DroneID
    ORDER BY rent_count DESC
    LIMIT 6
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AirErusea | Drone Rentals</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <img src="images/logo.jpg" alt="Airusea Logo" class="logo">
            <nav class="navbar">
                <a href="index.php">Home</a>
                <a href="drones.php">Rent A Drone</a>

                <?php if (isset($_SESSION['UserID'])): ?>
                    <!-- ADDED: My Rentals Button for logged-in users -->
                    <a href="chest.php" class="my-rentals-btn">My Rentals</a>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="index_login.php">Login</a>
                    <a href="register.php">Sign Up</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <section class="home-page">
        <h1>AIR</h1>
        <h1>ERUSEA</h1>
        <p style="line-height: 0px; font-size: 20px;">"A site to see the world from above."</p>
    </section>
    
    <section class="drones-section">
        <h2 class="section-title">Customer's Pick</h2>
        <div class="drones-container">
            <?php
            if ($stmt->rowCount() > 0) {
                while ($drone = $stmt->fetch()) {
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