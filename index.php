<?php
include('db.php');
include('logger.php');

logEvent("Accessed the main page.");

$stmt = $pdo->query("SELECT * FROM drones");
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
                <a href="rent.php">Rent A Drone</a>
                <a href="index_login.php">Login</a>
                <a href="register.php">Sign Up</a>
            </nav>
        </div>
    </header>

    <section class="home-page">
        <h1>AIR</h1>
        <h1>ERUSEA</h1>
        <p style="line-height: 0px; font-size: 20px;">"A site to see the world from above."</p>

    </section>
    <section class="drones-section">
        <h2 class="section-title">Available Drones</h2>
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
