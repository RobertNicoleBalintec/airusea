<?php
session_start();
include('db.php');
include('auth.php');

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$droneId = $_GET['id'];

// Fetch drone details with all specifications
$stmt = $pdo->prepare("
    SELECT d.*, c.CategoryName, m.MotorTypeName, p.Capacity, ps.SourceType, w.WingTypeName 
    FROM drones d
    LEFT JOIN categories c ON d.CategoryID = c.CategoryID
    LEFT JOIN motortype m ON d.MotorTypeID = m.MotorTypeID
    LEFT JOIN payloadcapacity p ON d.PayloadCapacityID = p.PayloadCapacityID
    LEFT JOIN powersource ps ON d.PowerSourceID = ps.PowerSourceID
    LEFT JOIN wingtype w ON d.WingTypeID = w.WingTypeID
    WHERE d.DroneID = ?
");

$stmt->execute([$droneId]);
$drone = $stmt->fetch();

if (!$drone) {
    die("Drone not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Drone Details | AirErusea</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <!-- Your header code here -->
    </header>
    
    <div class="drone-details-page">
        <h1><?= htmlspecialchars($drone['Brand'] . ' ' . $drone['Model']) ?></h1>
        <img src="images/<?= htmlspecialchars($drone['ImageURL']) ?>" alt="Drone Image">
        <p>Price/Day: ₱<?= number_format($drone['PricePerDay'], 2) ?></p>
        
        <div class="specifications">
            <h2>Specifications</h2>
            <p><strong>Category:</strong> <?= htmlspecialchars($drone['CategoryName']) ?></p>
            <p><strong>Motor Type:</strong> <?= htmlspecialchars($drone['MotorTypeName']) ?></p>
            <p><strong>Payload Capacity:</strong> <?= htmlspecialchars($drone['Capacity']) ?></p>
            <!-- Add more specs as needed -->
        </div>
        
        <div class="actions">
            <a href="index.php" class="btn">Back to Home</a>
            <?php if (!isAdmin()): ?>
                <a href="rent.php?DroneID=<?= $droneId ?>" class="btn">Rent This Drone</a>
            <?php else: ?>
                <a href="admin_panel.php" class="btn">Manage in Admin Panel</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>