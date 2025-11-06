<?php
require 'db.php';

$query = "SELECT * FROM drones";
$stmt = $pdo->prepare($query);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Available Drones</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
   
    <header>
        <h1>All Drones</h1>
        <div class="header-content">
            <nav class="navbar">
                <a href="dashboard.php">Home</a>
                <a href="drones.php">All Drones</a>
            </nav>
        </div>
    </header>

    <main>
        <?php while ($drone = $stmt->fetch()): ?>
            <div class="drone">
                <h2><?php echo htmlspecialchars($drone['Model']); ?></h2>
                <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" alt="<?php echo htmlspecialchars($drone['Model']); ?>" style="width: 30%; height: auto; object-fit: cover;" /> />
                <p>Price/Day: $<?php echo htmlspecialchars($drone['PricePerDay']); ?></p>
                <a href="rent.php?DroneID=<?php echo $drone['DroneID']; ?>" class="btn btn-primary">Rent This Drone</a>
            </div>
        <?php endwhile; ?>
    </main>
</body>
</html>
