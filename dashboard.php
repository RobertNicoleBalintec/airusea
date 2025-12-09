<?php
session_start();
require_once 'db.php'; 
require_once 'logger.php';
require_once 'auth.php'; // ADD THIS LINE

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: index_login.php');
    exit();
}

logEvent($_SESSION['Email'], 'Accessed the dashboard');

// Fetch user's name for the welcome message
$user_stmt = $pdo->prepare("SELECT Name FROM users WHERE UserID = ?");
$user_stmt->execute([$_SESSION['UserID']]);
$user = $user_stmt->fetch();
$display_name = !empty($user['Name']) ? $user['Name'] : $_SESSION['Email'];

// Only fetch drones if user is NOT admin
if (!isAdmin()) {
    $stmt = $pdo->query("SELECT * FROM drones");
} else {
    $stmt = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AirErusea | Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <!-- REMOVED the special admin button styles -->
</head>
<body>
    <header>
        <div class="header-content">
            <img src="images/logo.jpg" alt="Airusea Logo" class="logo">
            <nav class="navbar">
                <a href="index.php">Home</a>
                <?php if (isAdmin()): ?> <!-- ADD ADMIN PANEL BUTTON IN NAVBAR -->
                    <a href="admin_panel.php">Admin Panel</a> <!-- REMOVED: class="admin-nav-btn" -->
                <?php endif; ?>
                
                <?php if (!isAdmin()): ?> <!-- REGULAR USER LINKS -->
                    <a href="drones.php">Rent A Drone</a>
                    <!-- ADDED: My Rentals Button -->
                    <a href="chest.php">My Rentals</a> <!-- REMOVED: class="my-rentals-btn" -->
                <?php endif; ?>
                <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </nav>
        </div>
    </header>

    <!-- Added dashboard header section -->
    <div class="dashboard-header">
        <h1>Welcome to Your Dashboard</h1>
        <?php if (!isAdmin()): ?> <!-- ADD THIS CONDITION -->
            <p>Browse and rent from our available drone collection</p>
        <?php else: ?>
            <p>Business Management Dashboard</p>
        <?php endif; ?>
    </div>
    
    <!-- Welcome message with user's NAME (not email) -->
    <div class="welcome-message">
        <p>Hello, <strong><?php echo htmlspecialchars($display_name); ?></strong>! 
        
        <?php if (isAdmin()): ?>
            <br>You are logged in as an administrator. Use the admin panel to manage users and drones.
        <?php else: ?>
            You can view your current rentals by clicking the <strong>"My Rentals"</strong> button above.
        <?php endif; ?>
        </p>
    </div>

    <?php if (!isAdmin()): ?> <!-- ADD THIS CONDITION AROUND THE ENTIRE RENTAL SECTION -->
    <section class="drones-section">
        <h2 class="section-title">Available Drones</h2>
        <div class="drones-container">
            <?php
            if ($stmt && $stmt->rowCount() > 0) {
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
                echo "<p style='color: white;'>No drones available at the moment.</p>";
            }
            ?>
        </div>
    </section>
    <?php endif; ?>
</body>
</html>