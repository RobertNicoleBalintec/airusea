<?php
session_start();
require_once 'db.php';
require_once 'logger.php';

logEvent($_SESSION['Email'], 'Accessed the drones');

if (!isset($_SESSION['UserID'])) {
    header("Location: index_login.php");
    exit();
}

$query = "SELECT * FROM drones";
$stmt = $pdo->prepare($query);
$stmt->execute();

# Fetch RENTED drones (currently out for rental)
$rentedQuery = "
    SELECT d.*, r.RentStart, r.RentEnd, u.Email
    FROM drones d
    JOIN rentals r ON d.DroneID = r.DroneID
    JOIN users u ON r.UserID = u.UserID
    WHERE r.RentEnd >= NOW()
";
$rentedStmt = $pdo->prepare($rentedQuery);
$rentedStmt->execute();
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
        <div class="header-content">
            <nav class="navbar">
                <img src="images/logo.jpg" alt="Airusea Logo" class="logo">
                <a href="index.php">Home</a>
                <a href="#available-drones">Available</a>
                <a href="#rented-drones">Deployed</a>
            </nav>
        </div>
    </header>

    <main>
        <section id="available-drones">
            <?php while ($drone = $stmt->fetch()): ?>
                <div class="drone">
                    <h2><?php echo htmlspecialchars($drone['Model']); ?></h2>
                    <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" alt="<?php echo htmlspecialchars($drone['Model']); ?>" style="width: 30%; height: auto; object-fit: cover; border-radius: 20px;" />
                    <p>Price/Day: $<?php echo htmlspecialchars($drone['PricePerDay']); ?></p>
                    <a href="rent.php?DroneID=<?php echo $drone['DroneID']; ?>" class="btn btn-primary">Rent This Drone</a>
                </div>
            <?php endwhile; ?>
        </section>
        <section id="rented-drones">
                <h2>Out for Rental Drones</h2>
                <?php if ($rentedStmt->rowCount() === 0): ?>
                    <p>No drones currently rented out.</p>
                <?php endif; ?>

                <?php while ($r = $rentedStmt->fetch()): ?>
                    <div class="drone rented">
                        <h3><?= htmlspecialchars($r['Model']) ?></h3>
                        <img src="images/<?= htmlspecialchars($r['ImageURL']) ?>" 
                            alt="<?= htmlspecialchars($r['Model']) ?>" 
                            style="width: 30%; height: auto;">
                        <p><strong>Rent Start:</strong> <?= $r['RentStart'] ?></p>
                        <p><strong>Rent End:</strong> <?= $r['RentEnd'] ?></p>
                        <p style="color: red;"><strong>Status:</strong> OUT FOR RENTAL</p>
                    </div>
                <?php endwhile; ?>
        </section>
    </main>
</body>
</html>
