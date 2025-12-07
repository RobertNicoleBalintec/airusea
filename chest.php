<?php
// chest.php - Updated with Cancellation Feature
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: index_login.php');
    exit();
}

// Display success message if cancellation was successful
$success_msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'cancelled') {
        $success_msg = '<div class="success-message">
            <h3>✅ Rental Cancelled Successfully!</h3>
            <p>Your rental has been cancelled. Any payments will be refunded within 3-5 business days.</p>
        </div>';
    } elseif ($_GET['msg'] == 'cantcancel') {
        $success_msg = '<div class="error-message">
            <h3>⚠️ Cannot Cancel Rental</h3>
            <p>This rental cannot be cancelled because it has already started or doesn\'t exist.</p>
        </div>';
    }
}

// Get logged-in user ID
$user_id = $_SESSION['UserID'];

// Get user information
$user_stmt = $pdo->prepare("SELECT Name, Email FROM users WHERE UserID = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// UPDATED SQL QUERY - Includes Status column and proper status display
$query = "SELECT 
    r.RentalID,
    r.RentStart,
    r.RentEnd,
    r.TotalCost,
    r.Status,  -- Added Status from rentals table
    d.DroneID,
    d.Brand,
    d.Model,
    d.Size,
    d.PricePerDay,
    d.ImageURL,
    d.Description,
    d.UsageCase,
    CASE 
        WHEN r.Status = 'CANCELLED' THEN 'CANCELLED'
        WHEN r.RentEnd < NOW() THEN 'OVERDUE'
        ELSE 'ACTIVE'
    END as StatusDisplay
FROM rentals r
JOIN drones d ON r.DroneID = d.DroneID
WHERE r.UserID = ?
ORDER BY 
    CASE WHEN r.Status = 'CANCELLED' THEN 2 ELSE 1 END, -- Show active rentals first
    r.RentEnd ASC";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$rentals = $stmt->fetchAll();

// Count rentals by status
$active_count = 0;
$cancelled_count = 0;
$overdue_count = 0;

foreach ($rentals as $rental) {
    if ($rental['StatusDisplay'] == 'ACTIVE') $active_count++;
    elseif ($rental['StatusDisplay'] == 'CANCELLED') $cancelled_count++;
    elseif ($rental['StatusDisplay'] == 'OVERDUE') $overdue_count++;
}
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
            text-align: center;
            padding-top: 120px; /* Added to account for fixed header */
        }
        
        .customer-box {
            display: inline-block;
            text-align: left;
            padding: 25px 50px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9) !important;
            border: 1px solid #dddddd;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            color: #222;
            margin-bottom: 30px;
        }
        
        .rental-card {
            display: inline-block;
            text-align: center;
            vertical-align: top;
            padding: 25px 50px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9) !important;
            border: 1px solid #dddddd;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            margin: 20px auto;
            color: #222;
            max-width: 700px;
            width: 100%;
        }
        
        .drone-title {
            color: #2c3e50;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .status-overdue {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .status-cancelled {
            background: #f2f2f2;
            color: #666;
            border: 2px solid #ddd;
        }
        
        .rental-info {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            margin: 15px 0;
            text-align: left;
        }
        
        .info-group {
            min-width: 150px;
        }
        
        .info-group h4 {
            color: #6c757d;
            margin-bottom: 5px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-weight: 500;
            color: #212529;
            font-size: 1rem;
        }
        
        .no-rentals {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            color: #6c757d;
            margin-top: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 5px;
            font-weight: 500;
            transition: all 0.3s;
            border: 2px solid #2980b9;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Cancel button styling */
        .btn-cancel {
            display: inline-block;
            padding: 10px 25px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 5px;
            font-weight: bold;
            transition: all 0.3s ease;
            border: 2px solid #c0392b;
        }
        
        .btn-cancel:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        /* Success/Error messages */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 600px;
            border: 1px solid #c3e6cb;
            text-align: center;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 600px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }
        
        /* Drone Information Grid */
        .drone-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .drone-info-item {
            text-align: left;
            padding: 10px;
        }
        
        .drone-info-item h4 {
            color: #7f8c8d;
            margin-bottom: 8px;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        .drone-info-item .info-value {
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        /* Header styling for chest.php */
        .page-container h1 {
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
            margin-bottom: 20px;
        }
        
        .page-container h2 {
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
            margin: 30px 0;
        }
        
        /* Rental stats */
        .rental-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: rgba(255, 255, 255, 0.9);
            padding: 15px 25px;
            border-radius: 8px;
            min-width: 150px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-active .stat-number { color: #28a745; }
        .stat-cancelled .stat-number { color: #6c757d; }
        .stat-overdue .stat-number { color: #dc3545; }
        
        .button-group {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Status indicator */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .dot-active { background: #28a745; }
        .dot-cancelled { background: #6c757d; }
        .dot-overdue { background: #dc3545; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <img src="images/logo.jpg" alt="Airusea Logo" class="logo">
            <nav class="navbar">
                <a href="index.php">Home</a>
                <a href="drones.php">Rent A Drone</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </nav>
        </div>
    </header>

    <div class="page-container">
        <h1>My Drone Rentals</h1>
        
        <!-- Display success/error messages -->
        <?php echo $success_msg; ?>
        
        <!-- Customer Information -->
        <div class="customer-box">
            <h3>Customer Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['Name'] ?? 'N/A'); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['Email'] ?? 'N/A'); ?></p>
            <p><strong>Total Rentals:</strong> <?php echo count($rentals); ?> drone(s)</p>
        </div>
        
        <!-- Rental Statistics -->
        <div class="rental-stats">
            <div class="stat-box stat-active">
                <span class="stat-number"><?php echo $active_count; ?></span>
                <span>Active Rentals</span>
            </div>
            <div class="stat-box stat-cancelled">
                <span class="stat-number"><?php echo $cancelled_count; ?></span>
                <span>Cancelled</span>
            </div>
            <div class="stat-box stat-overdue">
                <span class="stat-number"><?php echo $overdue_count; ?></span>
                <span>Overdue</span>
            </div>
        </div>
        
        <!-- Rentals List -->
        <h2>Your Current Rentals</h2>
        
        <?php if (empty($rentals)): ?>
            <div class="no-rentals">
                <h3>No Active Rentals Found</h3>
                <p>You haven't rented any drones yet.</p>
                <p><strong>To see your rentals, you need to rent a drone first:</strong></p>
                <a href="dashboard.php" class="btn">Browse Available Drones</a>
            </div>
        <?php else: ?>
            <?php foreach ($rentals as $rental): 
                // Simplified cancellation check
                $can_cancel = ($rental['StatusDisplay'] == 'ACTIVE' && strtotime($rental['RentStart']) > time());
    
                // Debug - show why can/cannot cancel
                echo "<!-- DEBUG Rental #" . $rental['RentalID'] . ": ";
                echo "StatusDisplay=" . $rental['StatusDisplay'] . ", ";
                echo "RentStart=" . $rental['RentStart'] . ", ";
                echo "CurrentTime=" . date('Y-m-d H:i:s') . ", ";
                echo "CanCancel=" . ($can_cancel ? 'YES' : 'NO') . " -->";
            ?>
                <div class="rental-card">
                    <!-- Drone Name -->
                    <div class="drone-title">
                        <?php echo htmlspecialchars($rental['Brand'] . ' ' . $rental['Model']); ?>
                    </div>
                    
                    <!-- Status with indicator -->
                    <div class="status-indicator">
                        <span class="status-dot 
                            <?php 
                            if ($rental['StatusDisplay'] == 'ACTIVE') echo 'dot-active';
                            elseif ($rental['StatusDisplay'] == 'OVERDUE') echo 'dot-overdue';
                            elseif ($rental['StatusDisplay'] == 'CANCELLED') echo 'dot-cancelled';
                            ?>"></span>
                        <div class="status-badge 
                            <?php 
                            if ($rental['StatusDisplay'] == 'ACTIVE') echo 'status-active';
                            elseif ($rental['StatusDisplay'] == 'OVERDUE') echo 'status-overdue';
                            elseif ($rental['StatusDisplay'] == 'CANCELLED') echo 'status-cancelled';
                            ?>">
                            <?php echo $rental['StatusDisplay']; ?>
                        </div>
                    </div>
                    
                    <!-- Rental Dates & Info -->
                    <div class="rental-info">
                        <div class="info-group">
                            <h4>Rental Start</h4>
                            <div class="info-value">
                                <?php echo date('F j, Y g:i A', strtotime($rental['RentStart'])); ?>
                            </div>
                        </div>
                        <div class="info-group">
                            <h4>Rental Due</h4>
                            <div class="info-value">
                                <?php echo date('F j, Y g:i A', strtotime($rental['RentEnd'])); ?>
                            </div>
                        </div>
                        <div class="info-group">
                            <h4>Total Cost</h4>
                            <div class="info-value" style="color: #27ae60;">
                                ₱<?php echo number_format($rental['TotalCost'], 2); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Drone Information Grid -->
                    <div class="drone-info-grid">
                        <div class="drone-info-item">
                            <h4>Drone Size</h4>
                            <div class="info-value"><?php echo htmlspecialchars($rental['Size'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="drone-info-item">
                            <h4>Daily Rate</h4>
                            <div class="info-value">₱<?php echo number_format($rental['PricePerDay'], 2); ?> per day</div>
                        </div>
                        <?php if (!empty($rental['UsageCase'])): ?>
                        <div class="drone-info-item">
                            <h4>Best For</h4>
                            <div class="info-value"><?php echo htmlspecialchars($rental['UsageCase']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description -->
                    <?php if (!empty($rental['Description'])): ?>
                    <div class="drone-info-item" style="margin-top: 15px; text-align: left;">
                        <h4>Description</h4>
                        <div class="info-value">
                            <?php echo htmlspecialchars(substr($rental['Description'], 0, 150)); ?>...
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="button-group">
                        <a href="rent.php?DroneID=<?php echo $rental['DroneID']; ?>" class="btn">
                            View Drone Details
                        </a>
                        
                        <?php if ($can_cancel): ?>
                        <a href="cancel_rental.php?RentalID=<?php echo $rental['RentalID']; ?>" 
                            class="btn-cancel" 
                            onclick="return confirm('Are you sure you want to cancel rental #<?php echo $rental['RentalID']; ?>?\n\nDrone: <?php echo htmlspecialchars($rental['Brand'] . ' ' . $rental['Model']); ?>\nStart Date: <?php echo date('F j, Y', strtotime($rental['RentStart'])); ?>\n\nThis action cannot be undone.');">
                            🗙 Cancel Rental
                        </a>
                        <?php elseif ($rental['StatusDisplay'] == 'CANCELLED'): ?>
                        <button class="btn-cancel" style="background: #95a5a6; border-color: #7f8c8d; cursor: not-allowed;" disabled>
                            ❌ Already Cancelled
                        </button>
                        <?php elseif ($rental['StatusDisplay'] == 'OVERDUE'): ?>
                        <button class="btn-cancel" style="background: #95a5a6; border-color: #7f8c8d; cursor: not-allowed;" disabled>
                            ⚠️ Cannot Cancel (Overdue)
                        </button>
                        <?php else: ?>
                        <button class="btn-cancel" style="background: #95a5a6; border-color: #7f8c8d; cursor: not-allowed;" disabled>
                            ⚠️ Rental Started
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Additional info for cancelled rentals -->
                    <?php if ($rental['StatusDisplay'] == 'CANCELLED'): ?>
                    <div style="margin-top: 15px; color: #666; font-size: 0.9rem;">
        <p><em>This rental was cancelled and is no longer active.</em></p>
    </div>
    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // Add confirmation for cancellation
        document.addEventListener('DOMContentLoaded', function() {
            const cancelButtons = document.querySelectorAll('.btn-cancel:not([disabled])');
            cancelButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to cancel this rental?\n\nThis action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>