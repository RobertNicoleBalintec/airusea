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
        
        // Get dates from form
        $rent_start = $_POST['rent_start'];
        $rent_end = $_POST['rent_end'];
        
        // Calculate number of days
        $start_date = new DateTime($rent_start);
        $end_date = new DateTime($rent_end);
        $interval = $start_date->diff($end_date);
        $days = $interval->days;
        
        if ($days < 1) {
            echo "<p style='color: red;'>Error: Rental must be at least 1 day.</p>";
        } else {
            // Calculate total cost
            $totalCost = $drone['PricePerDay'] * $days;
            
            // Insert rental with selected dates
            $query = "INSERT INTO rentals (UserID, DroneID, RentStart, RentEnd, TotalCost) 
                      VALUES (:UserID, :DroneID, :rent_start, :rent_end, :totalCost)";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':UserID', $UserID);
            $stmt->bindParam(':DroneID', $DroneID);
            $stmt->bindParam(':rent_start', $rent_start);
            $stmt->bindParam(':rent_end', $rent_end);
            $stmt->bindParam(':totalCost', $totalCost);
            
            if ($stmt->execute()) {
                $rental_id = $pdo->lastInsertId();
                
                // Insert payment
                $query = "INSERT INTO payments (UserID, RentalID, PaymentMethodID, PaymentDate, AmountPaid) 
                          VALUES (:UserID, :RentalID, :PaymentMethodID, NOW(), :totalCost)";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':UserID', $UserID);
                $stmt->bindParam(':RentalID', $rental_id);
                $stmt->bindParam(':PaymentMethodID', $PaymentMethodID);
                $stmt->bindParam(':totalCost', $totalCost);
                $stmt->execute();
                
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <h3>✅ Rental Confirmed!</h3>
                        <p>Rental ID: <strong>#$rental_id</strong></p>
                        <p>Start Date: " . date('F j, Y g:i A', strtotime($rent_start)) . "</p>
                        <p>End Date: " . date('F j, Y g:i A', strtotime($rent_end)) . "</p>
                        <p>Total Days: $days days</p>
                        <p>Total Cost: <strong>₱" . number_format($totalCost, 2) . "</strong></p>
                        <p><a href='chest.php' style='color: #155724; font-weight: bold;'>View Your Rentals</a></p>
                      </div>";
            } else {
                echo "<p style='color: red;'>Error creating rental. Please try again.</p>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AirErusea | Rent Drone</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .rental-form-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .date-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .calculation-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 10px 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .drone-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
    </style>
    <script>
        function calculateCost() {
            const pricePerDay = <?php echo $drone['PricePerDay']; ?>;
            const startDate = new Date(document.getElementById('rent_start').value);
            const endDate = new Date(document.getElementById('rent_end').value);
            
            if (startDate && endDate && startDate < endDate) {
                const timeDiff = endDate - startDate;
                const days = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
                const totalCost = pricePerDay * days;
                
                document.getElementById('days_count').textContent = days;
                document.getElementById('total_cost').textContent = '₱' + totalCost.toLocaleString('en-PH', {minimumFractionDigits: 2});
                
                // Enable submit button if dates are valid
                document.querySelector('button[name="confirm_rental"]').disabled = false;
            } else {
                document.getElementById('days_count').textContent = '0';
                document.getElementById('total_cost').textContent = '₱0.00';
                document.querySelector('button[name="confirm_rental"]').disabled = true;
            }
        }
        
        // Set minimum dates when page loads
        window.onload = function() {
            const now = new Date();
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0); // Set to 9 AM tomorrow
            
            const threeDaysLater = new Date(tomorrow);
            threeDaysLater.setDate(threeDaysLater.getDate() + 3);
            
            // Format for datetime-local input
            const formatDate = (date) => {
                return date.toISOString().slice(0, 16);
            };
            
            document.getElementById('rent_start').min = formatDate(now);
            document.getElementById('rent_end').min = formatDate(now);
            
            // Set default values
            document.getElementById('rent_start').value = formatDate(tomorrow);
            document.getElementById('rent_end').value = formatDate(threeDaysLater);
            
            // Calculate initial cost
            calculateCost();
        };
    </script>
</head>
<body>
    <header>
        <div class="header-content">
            <nav class="navbar">
                <img src="images/logo.jpg" alt="Airusea Logo" class="logo">
                <a href="index.php">Home</a>
                <a href="drones.php">All Drones</a>
                <a href="chest.php">Chest</a>
            </nav>
        </div>
    </header>

    <div class="rental-form-container">
        <?php if ($drone): ?>
            <div class="drone-summary">
                <h2><?php echo htmlspecialchars($drone['Brand']) . ' ' . htmlspecialchars($drone['Model']); ?></h2>
                <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" 
                     alt="Drone Image" 
                     style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px; margin: 15px 0;">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 15px 0;">
                    <div>
                        <strong>Category:</strong><br>
                        <?php echo htmlspecialchars($drone['CategoryName']); ?>
                    </div>
                    <div>
                        <strong>Motor Type:</strong><br>
                        <?php echo htmlspecialchars($drone['MotorTypeName']); ?>
                    </div>
                    <div>
                        <strong>Payload:</strong><br>
                        <?php echo htmlspecialchars($drone['Capacity']); ?>
                    </div>
                    <div>
                        <strong>Power Source:</strong><br>
                        <?php echo htmlspecialchars($drone['SourceType']); ?>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 15px; padding: 15px; background: #e8f4fc; border-radius: 5px;">
                    <h3 style="color: #2c3e50; margin: 0;">Price: ₱<?php echo number_format($drone['PricePerDay'], 2); ?> per day</h3>
                </div>
            </div>
            
            <form method="POST" action="">
                <h3>📅 Select Rental Period</h3>
                
                <div class="date-inputs">
                    <div class="form-group">
                        <label for="rent_start">Start Date & Time</label>
                        <input type="datetime-local" 
                               id="rent_start" 
                               name="rent_start" 
                               class="form-control" 
                               required
                               onchange="calculateCost()">
                        <small style="color: #666;">Rentals must start at least 1 hour from now</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="rent_end">End Date & Time</label>
                        <input type="datetime-local" 
                               id="rent_end" 
                               name="rent_end" 
                               class="form-control" 
                               required
                               onchange="calculateCost()">
                        <small style="color: #666;">Must be after start date</small>
                    </div>
                </div>
                
                <div class="calculation-box">
                    <h4>📋 Rental Calculation</h4>
                    <p>Days: <span id="days_count" style="font-weight: bold;">0</span> days</p>
                    <p>Daily Rate: ₱<?php echo number_format($drone['PricePerDay'], 2); ?></p>
                    <p style="font-size: 1.2rem; font-weight: bold; color: #27ae60;">
                        Total Cost: <span id="total_cost">₱0.00</span>
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="PaymentMethodID">💳 Select Payment Method</label>
                    <select name="PaymentMethodID" id="PaymentMethodID" class="form-control" required>
                        <?php foreach ($paymentMethods as $method): ?>
                            <option value="<?php echo $method['PaymentMethodID']; ?>">
                                <?php echo htmlspecialchars($method['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" name="confirm_rental" class="btn btn-primary" style="padding: 15px 40px; font-size: 18px;">
                        ✅ Confirm Rental
                    </button>
                    <br>
                    <a href="drones.php" class="btn btn-danger" style="margin-top: 15px;">
                        ❌ Cancel and Go Back
                    </a>
                </div>
            </form>
        <?php else: ?>
            <p style="color: red; text-align: center;">No drone selected.</p>
            <p style="text-align: center;"><a href="drones.php">Browse Available Drones</a></p>
        <?php endif; ?>
    </div>
</body>
</html>