<?php
session_start();
require 'db.php'; 


if (!isset($_SESSION['UserID'])) {
    header('Location: index_login.php');
    exit();
}

if (!isset($_GET['DroneID']) || empty($_GET['DroneID'])) {
    echo "<p style='color: red;'>Error: No drone selected.</p>";
    exit();
}

$DroneID = $_GET['DroneID']; 
$UserID = $_SESSION['UserID']; 

$query = "SELECT d.*, c.CategoryName, m.MotorTypeName, p.Capacity, ps.SourceType, w.WingTypeName 
          FROM drones d
          LEFT JOIN categories c ON d.CategoryID = c.CategoryID
          LEFT JOIN motortype m ON d.MotorTypeID = m.MotorTypeID
          LEFT JOIN payloadcapacity p ON d.PayloadCapacityID = p.PayloadCapacityID
          LEFT JOIN powersource ps ON d.PowerSourceID = ps.PowerSourceID
          LEFT JOIN wingtype w ON d.WingTypeID = w.WingTypeID
          WHERE d.DroneID = :DroneID";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':DroneID', $DroneID);
$stmt->execute();
$drone = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$drone) {
    echo "<p style='color: red;'>Error: Drone not found.</p>";
    exit();
}

$query = "SELECT * FROM paymentmethods";
$stmt = $pdo->prepare($query);
$stmt->execute();
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirm_rental']) && isset($_POST['PaymentMethodID'])) {
        $PaymentMethodID = $_POST['PaymentMethodID'];
        $totalCost = $drone['PricePerDay'] * 3;

        $query = "INSERT INTO rentals (UserID, DroneID, RentStart, RentEnd, TotalCost) VALUES (:UserID, :DroneID, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), :totalCost)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':UserID', $UserID);
        $stmt->bindParam(':DroneID', $DroneID);
        $stmt->bindParam(':totalCost', $totalCost);
        $stmt->execute();

        $rental_id = $pdo->lastInsertId();

        $query = "INSERT INTO payments (UserID, RentalID, PaymentMethodID, PaymentDate, AmountPaid) VALUES (:UserID, :RentalID, :PaymentMethodID, NOW(), :totalCost)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':UserID', $UserID);
        $stmt->bindParam(':RentalID', $rental_id);
        $stmt->bindParam(':PaymentMethodID', $PaymentMethodID);
        $stmt->bindParam(':totalCost', $totalCost);
        $stmt->execute();

        echo "Rental processed successfully with payment!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AirErusea | Rent Drone</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Rent A Drone</h1>
        <div class="header-content">
            <nav class="navbar">
                <a href="index.php">Home</a>
                <a href="drones.php">All Drones</a>
            </nav>
        </div>
    </header>

    <div>
        <?php if ($drone): ?>
            <h2><?php echo htmlspecialchars($drone['Brand']) . ' ' . htmlspecialchars($drone['Model']); ?></h2>
            <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" alt="Drone Image" style="width: 30%; height: auto; object-fit: cover;">
            <p><strong>Category:</strong> <?php echo htmlspecialchars($drone['CategoryName']); ?></p>
            <p><strong>Motor Type:</strong> <?php echo htmlspecialchars($drone['MotorTypeName']); ?></p>
            <p><strong>Payload Capacity:</strong> <?php echo htmlspecialchars($drone['Capacity']); ?></p>
            <p><strong>Power Source:</strong> <?php echo htmlspecialchars($drone['SourceType']); ?></p>
            <p><strong>Wing Type:</strong> <?php echo htmlspecialchars($drone['WingTypeName']); ?></p>
            <p><strong>Price Per Day:</strong> ₱<?php echo number_format($drone['PricePerDay'], 2); ?></p>

            <form method="POST" action="">
                <label>Select Payment Method:</label>
                <select name="PaymentMethodID" required>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?php echo $method['PaymentMethodID']; ?>"><?php echo htmlspecialchars($method['Name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="confirm_rental" class="btn btn-primary">Confirm Rental</button>
                <button type="submit" name="cancel_rental" class="btn btn-danger">Cancel Rental</button>
            </form>
        <?php else: ?>
            <p>No drone selected.</p>
        <?php endif; ?>
    </div>
</body>
</html>