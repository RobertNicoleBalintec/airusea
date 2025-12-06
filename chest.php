<?php
// chest.php - CORRECTED VERSION (No HTML in SQL)
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: index_login.php');
    exit();
}

// Get logged-in user ID
$user_id = $_SESSION['UserID'];

// Get user information
$user_stmt = $pdo->prepare("SELECT Name, Email FROM users WHERE UserID = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// CORRECT SQL QUERY - No HTML comments!
$query = "SELECT 
    r.RentalID,
    r.RentStart,
    r.RentEnd,
    r.TotalCost,
    d.DroneID,
    d.Brand,
    d.Model,
    d.Size,
    d.PricePerDay,
    d.ImageURL,
    d.Description,
    d.UsageCase,
    CASE 
        WHEN r.RentEnd < NOW() THEN 'OVERDUE'
        ELSE 'ACTIVE'
    END as Status
FROM rentals r
JOIN drones d ON r.DroneID = d.DroneID
WHERE r.UserID = ?
ORDER BY r.RentEnd ASC";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$rentals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Drone Rentals - AirErusea</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .page-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .customer-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .rental-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .drone-title {
            color: #2c3e50;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }
        
        .rental-info {
            display: flex;
            gap: 30px;
            margin: 15px 0;
        }
        
        .info-group h4 {
            color: #6c757d;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .info-value {
            font-weight: 500;
            color: #212529;
        }
        
        .no-rentals {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <!-- Same header as dashboard.php -->
    <header>
        <div class="header-content">
            <img src="images/logo.jpg" alt="Airusea Logo" class="logo">
            <nav class="navbar">
                <a href="index.php">Home</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </nav>
        </div>
    </header>

    <div class="page-container">
        <h1>My Drone Rentals</h1>
        
        <!-- Customer Information -->
        <div class="customer-box">
            <h3>Customer Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['Name'] ?? 'N/A'); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['Email'] ?? 'N/A'); ?></p>
            <p><strong>Total Rentals:</strong> <?php echo count($rentals); ?> drone(s)</p>
        </div>
        
        <!-- Rentals List -->
        <h2>Your Current Rentals</h2>
        
        <?php if (empty($rentals)): ?>
            <div class="no-rentals">
                <h3>No Active Rentals Found</h3>
                <p>You haven't rented any drones yet.</p>
                <p><strong>To test chest.php, you need to rent a drone first:</strong></p>
                <a href="dashboard.php" class="btn">Browse Available Drones</a>
            </div>
        <?php else: ?>
            <?php foreach ($rentals as $rental): ?>
                <div class="rental-card">
                    <!-- Drone Name -->
                    <div class="drone-title">
                        <?php echo htmlspecialchars($rental['Brand'] . ' ' . $rental['Model']); ?>
                    </div>
                    
                    <!-- Status -->
                    <div class="status-badge <?php echo $rental['Status'] == 'ACTIVE' ? 'status-active' : 'status-overdue'; ?>">
                        <?php echo $rental['Status']; ?>
                    </div>
                    
                    <!-- Rental Dates & Info -->
                    <div class="rental-info">
                        <div class="info-group">
                            <h4>Rental Start</h4>
                            <div class="info-value">
                                <?php echo date('F j, Y', strtotime($rental['RentStart'])); ?>
                            </div>
                        </div>
                        <div class="info-group">
                            <h4>Rental Due</h4>
                            <div class="info-value">
                                <?php echo date('F j, Y', strtotime($rental['RentEnd'])); ?>
                            </div>
                        </div>
                        <div class="info-group">
                            <h4>Total Cost</h4>
                            <div class="info-value" style="color: #27ae60;">
                                ₱<?php echo number_format($rental['TotalCost'], 2); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Drone Information -->
                    <div style="margin-top: 15px;">
                        <p><strong>Drone Size:</strong> <?php echo htmlspecialchars($rental['Size'] ?? 'N/A'); ?></p>
                        <p><strong>Daily Rate:</strong> ₱<?php echo number_format($rental['PricePerDay'], 2); ?> per day</p>
                        
                        <?php if (!empty($rental['UsageCase'])): ?>
                            <p><strong>Best For:</strong> <?php echo htmlspecialchars($rental['UsageCase']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($rental['Description'])): ?>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars(substr($rental['Description'], 0, 150)); ?>...</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Button -->
                    <a href="rent.php?DroneID=<?php echo $rental['DroneID']; ?>" class="btn">View Drone Details</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>