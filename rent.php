<?php
session_start();
require 'db.php'; 
require 'auth.php';

// PREVENT ADMINS FROM RENTING
if (isAdmin()) {
    $_SESSION['error'] = "Administrators cannot rent drones. Please use a regular user account.";
    header("Location: dashboard.php");
    exit();
}

if (!isset($_SESSION['UserID'])) {
    header('Location: index_login.php');
    exit();
}

$DroneID = isset($_GET['DroneID']) ? intval($_GET['DroneID']) : 0;

if ($DroneID <= 0) {
    header('Location: drones.php?error=invalid_drone');
    exit();
}

// Check if drone exists, is available, and not currently rented
$query = "SELECT d.*, c.CategoryName, m.MotorTypeName, p.Capacity, ps.SourceType, w.WingTypeName 
          FROM drones d
          LEFT JOIN categories c ON d.CategoryID = c.CategoryID
          LEFT JOIN motortype m ON d.MotorTypeID = m.MotorTypeID
          LEFT JOIN payloadcapacity p ON d.PayloadCapacityID = p.PayloadCapacityID
          LEFT JOIN powersource ps ON d.PowerSourceID = ps.PowerSourceID
          LEFT JOIN wingtype w ON d.WingTypeID = w.WingTypeID
          WHERE d.DroneID = :DroneID 
          AND d.status = 'available' 
          AND d.QuantityAvailable > 0
          AND NOT EXISTS (
              SELECT 1 FROM rentals r 
              WHERE r.DroneID = d.DroneID 
              AND r.RentEnd >= NOW()
              AND r.status = 'active'
          )";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':DroneID', $DroneID);
$stmt->execute();
$drone = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$drone) {
    // Check why drone is not available
    $check_query = "SELECT d.*, 
                    (SELECT COUNT(*) FROM rentals r 
                     WHERE r.DroneID = d.DroneID 
                     AND r.RentEnd >= NOW() 
                     AND r.status = 'active') as active_rentals
                    FROM drones d WHERE d.DroneID = :DroneID";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->bindParam(':DroneID', $DroneID);
    $check_stmt->execute();
    $drone_check = $check_stmt->fetch();
    
    if (!$drone_check) {
        header('Location: drones.php?error=drone_not_found');
        exit();
    } else if ($drone_check['status'] === 'phased_out') {
        header('Location: drones.php?error=drone_phased_out');
        exit();
    } else if ($drone_check['QuantityAvailable'] <= 0) {
        header('Location: drones.php?error=drone_out_of_stock');
        exit();
    } else if ($drone_check['active_rentals'] > 0) {
        header('Location: drones.php?error=drone_already_rented');
        exit();
    } else {
        header('Location: drones.php?error=drone_unavailable');
        exit();
    }
}

$query = "SELECT * FROM paymentmethods";
$stmt = $pdo->prepare($query);
$stmt->execute();
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirm_rental']) && isset($_POST['PaymentMethodID'])) {
        $PaymentMethodID = $_POST['PaymentMethodID'];
        
        // Get dates from form
        $rent_start = $_POST['rent_start'];
        $rent_end = $_POST['rent_end'];
        
        // Validate dates
        if (empty($rent_start) || empty($rent_end)) {
            $error_message = "Error: Please select both start and end dates.";
        } else {
            // Calculate number of days
            $start_date = new DateTime($rent_start);
            $end_date = new DateTime($rent_end);
            $interval = $start_date->diff($end_date);
            $days = $interval->days;
            
            if ($days < 1) {
                $error_message = "Error: Rental must be at least 1 day.";
            } else if ($start_date < new DateTime()) {
                $error_message = "Error: Start date cannot be in the past.";
            } else {
                // Calculate total cost
                $totalCost = $drone['PricePerDay'] * $days;
                
                // Use the new process_rent.php logic by posting to it
                // We'll simulate a POST to process_rent.php
                
                // Create a form that auto-submits to process_rent.php
                echo '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <title>Processing Rental...</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            background: #f5f7fa;
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            height: 100vh;
                            margin: 0;
                        }
                        .processing-container {
                            background: white;
                            padding: 30px;
                            border-radius: 10px;
                            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                            text-align: center;
                            max-width: 400px;
                        }
                        .spinner {
                            border: 5px solid #f3f3f3;
                            border-top: 5px solid #3498db;
                            border-radius: 50%;
                            width: 50px;
                            height: 50px;
                            animation: spin 1s linear infinite;
                            margin: 0 auto 20px;
                        }
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                </head>
                <body>
                    <div class="processing-container">
                        <div class="spinner"></div>
                        <h2>Processing Your Rental...</h2>
                        <p>Please wait while we confirm your rental.</p>
                    </div>
                    <form id="rentalForm" action="process_rent.php" method="POST" style="display: none;">
                        <input type="hidden" name="rent" value="1">
                        <input type="hidden" name="drone_id" value="' . $DroneID . '">
                        <input type="hidden" name="rent_start" value="' . htmlspecialchars($rent_start) . '">
                        <input type="hidden" name="rent_end" value="' . htmlspecialchars($rent_end) . '">
                        <input type="hidden" name="PaymentMethodID" value="' . $PaymentMethodID . '">
                    </form>
                    <script>
                        // Auto-submit the form after 1 second
                        setTimeout(function() {
                            document.getElementById("rentalForm").submit();
                        }, 1000);
                    </script>
                </body>
                </html>';
                exit();
            }
        }
    }
}

// Display error message if any
if (isset($error_message)) {
    echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px auto; max-width: 600px; text-align: center;">
            ❌ ' . htmlspecialchars($error_message) . '
          </div>';
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
        
        .availability-badge {
            display: inline-block;
            background: #d4edda;
            color: #155724;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .quantity-info {
            background: #e8f4fc;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-weight: bold;
            color: #2c3e50;
            border-left: 4px solid #3498db;
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
        
        .btn-primary:disabled {
            background: #95a5a6;
            cursor: not-allowed;
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
        
        @media (max-width: 768px) {
            .date-inputs {
                grid-template-columns: 1fr;
            }
            
            .rental-form-container {
                margin: 15px;
                padding: 20px;
            }
        }
    </style>
    <script>
        function calculateCost() {
            const pricePerDay = <?php echo $drone['PricePerDay']; ?>;
            const startDate = new Date(document.getElementById('rent_start').value);
            const endDate = new Date(document.getElementById('rent_end').value);
            const now = new Date();
            
            if (startDate && endDate && startDate < endDate) {
                if (startDate < now) {
                    document.getElementById('days_count').textContent = '0';
                    document.getElementById('total_cost').textContent = '₱0.00';
                    document.getElementById('date_error').textContent = 'Start date cannot be in the past';
                    document.getElementById('date_error').style.display = 'block';
                    
                    const submitBtn = document.querySelector('button[name="confirm_rental"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '❌ Invalid Dates';
                    }
                    return;
                }
                
                const timeDiff = endDate - startDate;
                const days = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
                const totalCost = pricePerDay * days;
                
                document.getElementById('days_count').textContent = days;
                document.getElementById('total_cost').textContent = '₱' + totalCost.toLocaleString('en-PH', {minimumFractionDigits: 2});
                document.getElementById('date_error').style.display = 'none';
                
                // Enable submit button if dates are valid
                const submitBtn = document.querySelector('button[name="confirm_rental"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '✅ Confirm Rental';
                }
            } else {
                document.getElementById('days_count').textContent = '0';
                document.getElementById('total_cost').textContent = '₱0.00';
                document.getElementById('date_error').textContent = 'End date must be after start date';
                document.getElementById('date_error').style.display = 'block';
                
                const submitBtn = document.querySelector('button[name="confirm_rental"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '❌ Invalid Dates';
                }
            }
        }
        
        // Set minimum dates when page loads
        window.onload = function() {
            const now = new Date();
            const oneHourFromNow = new Date(now.getTime() + 60 * 60 * 1000);
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0);
            
            const threeDaysLater = new Date(tomorrow);
            threeDaysLater.setDate(threeDaysLater.getDate() + 3);
            
            // Format for datetime-local input
            const formatDate = (date) => {
                return date.toISOString().slice(0, 16);
            };
            
            // Set min dates
            document.getElementById('rent_start').min = formatDate(oneHourFromNow);
            document.getElementById('rent_end').min = formatDate(new Date(oneHourFromNow.getTime() + 60 * 60 * 1000));
            
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
                <?php if (!isAdmin()): ?>
                    <a href="drones.php">All Drones</a>
                    <a href="chest.php">Chest</a>
                <?php endif; ?>
                <a href="dashboard.php">Dashboard</a>
            </nav>
        </div>
    </header>

    <div class="rental-form-container">
        <?php if ($drone): ?>
            <div class="drone-summary">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2 style="margin: 0;"><?php echo htmlspecialchars($drone['Brand']) . ' ' . htmlspecialchars($drone['Model']); ?></h2>
                    <span class="availability-badge">✅ Available</span>
                </div>
                
                <div class="quantity-info">
                    ⚡ <?php echo $drone['QuantityAvailable']; ?> units available for rent
                </div>
                
                <?php if (!empty($drone['ImageURL'])): ?>
                <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" 
                     alt="Drone Image" 
                     style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px; margin: 15px 0;">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 15px 0;">
                    <?php if (!empty($drone['CategoryName'])): ?>
                    <div>
                        <strong>Category:</strong><br>
                        <?php echo htmlspecialchars($drone['CategoryName']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($drone['MotorTypeName'])): ?>
                    <div>
                        <strong>Motor Type:</strong><br>
                        <?php echo htmlspecialchars($drone['MotorTypeName']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($drone['Capacity'])): ?>
                    <div>
                        <strong>Payload:</strong><br>
                        <?php echo htmlspecialchars($drone['Capacity']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($drone['SourceType'])): ?>
                    <div>
                        <strong>Power Source:</strong><br>
                        <?php echo htmlspecialchars($drone['SourceType']); ?>
                    </div>
                    <?php endif; ?>
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
                
                <div id="date_error" style="color: #e74c3c; padding: 10px; background: #f8d7da; border-radius: 5px; display: none; margin-bottom: 15px;"></div>
                
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
                    <button type="submit" name="confirm_rental" class="btn btn-primary" style="padding: 15px 40px; font-size: 18px;" disabled>
                        ⏳ Please select dates
                    </button>
                    <br>
                    <a href="drones.php" class="btn btn-danger" style="margin-top: 15px;">
                        ❌ Cancel and Go Back
                    </a>
                </div>
            </form>
        <?php else: ?>
            <p style="color: red; text-align: center;">No drone selected or drone is not available.</p>
            <p style="text-align: center;"><a href="drones.php">Browse Available Drones</a></p>
        <?php endif; ?>
    </div>
    
    <!-- Display any error messages from URL parameters -->
    <script>
        // Check for URL error parameters and display them
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        
        if (error) {
            let errorMessage = '';
            switch(error) {
                case 'drone_phased_out':
                    errorMessage = 'This drone has been phased out and is no longer available for rent.';
                    break;
                case 'drone_out_of_stock':
                    errorMessage = 'This drone is currently out of stock.';
                    break;
                case 'drone_already_rented':
                    errorMessage = 'This drone is currently rented by another user.';
                    break;
                case 'drone_unavailable':
                    errorMessage = 'This drone is currently unavailable for rent.';
                    break;
                default:
                    errorMessage = 'An error occurred. Please try again.';
            }
            
            if (errorMessage) {
                const errorDiv = document.createElement('div');
                errorDiv.style.cssText = 'background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px auto; max-width: 600px; text-align: center;';
                errorDiv.innerHTML = '❌ ' + errorMessage;
                document.querySelector('.rental-form-container').insertAdjacentElement('beforebegin', errorDiv);
            }
        }
    </script>
</body>
</html>