<?php
session_start();
require_once 'db.php';
require_once 'logger.php';

logEvent($_SESSION['Email'], 'Accessed the drones');

if (!isset($_SESSION['UserID'])) {
    header("Location: index_login.php");
    exit();
}

$query = "SELECT * FROM drones WHERE 1";

$params = [];

if (!empty($_GET['query'])) {
    $search = '%' . $_GET['query'] . '%';
    $query .= " AND (Model LIKE :search OR Brand LIKE :search OR PricePerDay LIKE :search)";
    $params[':search'] = $search;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);


$query = "
    SELECT * FROM drones
    WHERE DroneID NOT IN (
        SELECT DroneID
        FROM rentals
        WHERE RentEnd >= NOW()
    )
";
$stmt = $pdo->prepare($query);
$stmt->execute();


$rentedQuery = "
    SELECT d.*, r.RentStart, r.RentEnd, u.Email
    FROM drones d
    JOIN rentals r ON d.DroneID = r.DroneID
    JOIN users u ON r.UserID = u.UserID
    WHERE r.RentEnd >= NOW()
    ORDER BY r.RentEnd ASC
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
        <div class="header-search">
            <form method="GET" action="drones.php" class="search-bar">
                <input type="text" name="query" placeholder="Search by model, price, brand, motor type..." />`<button type="submit">Search</button>
            </form>
        </div>

        <section id="available-drones">
            <?php while ($drone = $stmt->fetch()): ?>
                <div class="drone">
                    <h2><?php echo htmlspecialchars($drone['Model']); ?></h2>
                    <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" alt="<?php echo htmlspecialchars($drone['Model']); ?>"/>
                    <p>Price/Day: $<?php echo htmlspecialchars($drone['PricePerDay']); ?></p>
                    <a href="rent.php?DroneID=<?php echo $drone['DroneID']; ?>" class="btn btn-primary">Rent This Drone</a>
                </div>
            <?php endwhile; ?>
        </section>

        <h2>Out for Rental Drones</h2>
        <section id="rented-drones">
                <?php if ($rentedStmt->rowCount() === 0): ?>
                    <p>No drones currently rented out.</p>
                <?php endif; ?>

                <?php while ($r = $rentedStmt->fetch()): ?>
                    <div class="drone rented">
                        <h3><?= htmlspecialchars($r['Model']) ?></h3>
                        <img src="images/<?= htmlspecialchars($r['ImageURL']) ?>" 
                            alt="<?= htmlspecialchars($r['Model']) ?>">
                        <p><strong>Rent Start:</strong> <?= $r['RentStart'] ?></p>
                        <p><strong>Rent End:</strong> <?= $r['RentEnd'] ?></p>
                        <p style="color: red;"><strong>Status:</strong> OUT FOR RENTAL</p>
                    </div>
                <?php endwhile; ?>
        </section>
    </main>
</body>
</html>
