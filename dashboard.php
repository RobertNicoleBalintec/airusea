<?php
session_start();
require_once 'db.php'; 
require_once 'logger.php';
require_once 'auth.php';

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

// FIRST: Check if 'status' column exists in rentals table
$checkStatusColumn = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'status'");
$hasStatusColumn = $checkStatusColumn->rowCount() > 0;

// Query for available drones (not currently rented AND not phased_out)
$availableQuery = "
    SELECT d.* 
    FROM drones d
    WHERE d.status = 'available' 
    AND d.QuantityAvailable > 0
    AND NOT EXISTS (
        SELECT 1 FROM rentals r 
        WHERE r.DroneID = d.DroneID 
        AND r.RentEnd >= NOW()
        " . ($hasStatusColumn ? "AND r.status = 'active'" : "") . "
    )
    ORDER BY DroneID DESC
";
$availableStmt = $pdo->prepare($availableQuery);
$availableStmt->execute();

// Query for rented drones - only show active rentals
if ($hasStatusColumn) {
    $rentedQuery = "
        SELECT d.*, r.RentalID, r.RentStart, r.RentEnd, u.Email, u.Name as RenterName
        FROM drones d
        JOIN rentals r ON d.DroneID = r.DroneID
        JOIN users u ON r.UserID = u.UserID
        WHERE r.RentEnd >= NOW()
        AND u.UserID IS NOT NULL
        AND r.status = 'active'
        AND d.status = 'available'
        ORDER BY r.RentEnd ASC
    ";
} else {
    $rentedQuery = "
        SELECT d.*, r.RentalID, r.RentStart, r.RentEnd, u.Email, u.Name as RenterName
        FROM drones d
        JOIN rentals r ON d.DroneID = r.DroneID
        JOIN users u ON r.UserID = u.UserID
        WHERE r.RentEnd >= NOW()
        AND u.UserID IS NOT NULL
        ORDER BY r.RentEnd ASC
    ";
}
$rentedStmt = $pdo->prepare($rentedQuery);
$rentedStmt->execute();

// Also get cancelled rentals for admin review
if (isAdmin() && $hasStatusColumn) {
    $cancelledQuery = "
        SELECT d.*, r.RentalID, r.RentStart, r.RentEnd, u.Email, u.Name as RenterName, r.status
        FROM drones d
        JOIN rentals r ON d.DroneID = r.DroneID
        JOIN users u ON r.UserID = u.UserID
        WHERE r.RentEnd >= NOW()
        AND r.status = 'cancelled'
        ORDER BY r.RentEnd ASC
    ";
    $cancelledStmt = $pdo->prepare($cancelledQuery);
    $cancelledStmt->execute();
    $cancelled_count = $cancelledStmt->rowCount();
} else {
    $cancelledStmt = null;
    $cancelled_count = 0;
}

// Get total revenue (last 30 days) - exclude cancelled and expired
if ($hasStatusColumn) {
    $revenueQuery = "
        SELECT SUM(TotalCost) as total_revenue 
        FROM rentals 
        WHERE RentStart >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status = 'active'
    ";
} else {
    $revenueQuery = "
        SELECT SUM(TotalCost) as total_revenue 
        FROM rentals 
        WHERE RentStart >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
}
$revenueStmt = $pdo->query($revenueQuery);
$revenue = $revenueStmt->fetch();
$total_revenue = $revenue['total_revenue'] ?? 0;

// Get active rentals count (only active rentals, not cancelled or expired)
if ($hasStatusColumn) {
    $activeRentalsQuery = "
        SELECT COUNT(*) as active_count 
        FROM rentals r
        JOIN users u ON r.UserID = u.UserID
        WHERE r.RentEnd >= NOW()
        AND u.UserID IS NOT NULL
        AND r.status = 'active'
    ";
} else {
    $activeRentalsQuery = "
        SELECT COUNT(*) as active_count 
        FROM rentals r
        JOIN users u ON r.UserID = u.UserID
        WHERE r.RentEnd >= NOW()
        AND u.UserID IS NOT NULL
    ";
}
$activeRentalsStmt = $pdo->query($activeRentalsQuery);
$activeRentals = $activeRentalsStmt->fetch();
$active_rentals = $activeRentals['active_count'] ?? 0;

// Only fetch ALL drones if user is NOT admin (for regular users)
if (!isAdmin()) {
    $stmt = $pdo->query("SELECT * FROM drones WHERE status = 'available'");
} else {
    $stmt = null; // Admins don't need this query
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
        }

        /* Navigation - Centered */
        .navbar {
            background-color: #2c3e50;
            padding: 15px 30px;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
        }

        .nav-links a:hover {
            color: #3498db;
        }

        .nav-links a.active {
            color: #3498db;
            font-weight: 600;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .user-info {
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Welcome */
        .welcome {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .welcome h1 {
            color: #2c3e50;
            font-size: 1.8rem;
        }

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* Warning Box for Database Structure */
        .db-warning-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #28a745;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .db-warning-icon {
            color: #28a745;
            font-size: 1.5rem;
        }

        .db-warning-text {
            color: #155724;
            font-weight: 500;
        }

        .fix-db-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            margin-left: auto;
            text-decoration: none;
            display: inline-block;
        }

        .fix-db-btn:hover {
            background: #218838;
        }

        /* Cancelled Rental Box */
        .cancelled-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }

        .cancelled-icon {
            color: #dc3545;
            font-size: 1.5rem;
            margin-right: 10px;
        }

        /* Drone Cards */
        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .drone-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .drone-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .drone-card.cancelled {
            border: 2px solid #dc3545;
            opacity: 0.8;
        }

        .cancelled-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #dc3545;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            z-index: 10;
        }

        .drone-image {
            height: 180px;
            background-color: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7f8c8d;
            font-size: 3rem;
            position: relative;
        }

        .drone-info {
            padding: 20px;
        }

        .drone-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .drone-price {
            font-size: 1.2rem;
            color: #e74c3c;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            text-align: center;
            width: 100%;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #2980b9;
        }

        .btn-cancelled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .btn-cancelled:hover {
            background: #5a6268;
        }

        /* Renter Info */
        .renter-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 0.9rem;
        }

        .renter-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .cancelled-info {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .cancelled-label {
            color: #721c24;
            font-weight: 700;
        }

        /* Status Indicator */
        .status-indicator {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .status-available {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rented {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-icon {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .user-info {
                width: 100%;
                justify-content: center;
            }
            
            .drone-grid {
                grid-template-columns: 1fr;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .db-warning-box, .cancelled-box {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .fix-db-btn {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation - Centered -->
    <div class="navbar">
        <div class="nav-container">
            <div class="nav-links">
                <a href="index.php">Home</a>
                <?php if (isAdmin()): ?>
                    <a href="admin_panel.php">Admin Panel</a>
                <?php endif; ?>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
            <div class="user-info">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($display_name); ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome -->
        <div class="welcome">
            <h1>Welcome, <?php echo htmlspecialchars($display_name); ?>!</h1>
            <p>Manage your drone rentals and view available drones.</p>
        </div>

        <!-- Database Structure Warning -->
        <?php if (isAdmin() && !$hasStatusColumn): ?>
            <div class="db-warning-box">
                <div class="db-warning-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="db-warning-text">
                    <strong>Database Update Recommended:</strong> Your rentals table doesn't have a 'status' column. 
                    This prevents proper tracking of cancelled rentals. Cancelled rentals will still appear as active.
                </div>
                <button class="fix-db-btn" onclick="addStatusColumn()">
                    <i class="fas fa-wrench"></i> Fix Database
                </button>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo $availableStmt->rowCount(); ?></div>
                <div class="stat-label">Available Drones</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $rentedStmt->rowCount(); ?></div>
                <div class="stat-label">Active Rentals</div>
            </div>
            <div class="stat-box">
                <div class="stat-number">₱<?php echo number_format($total_revenue, 0); ?></div>
                <div class="stat-label">30-Day Revenue</div>
            </div>
        </div>

        <?php if (isAdmin()): ?>
            <!-- ADMIN VIEW -->
            
            <!-- Available Drones -->
            <h2 class="section-title">Available Drones (<?php echo $availableStmt->rowCount(); ?>)</h2>
            <?php if ($availableStmt->rowCount() > 0): ?>
                <div class="drone-grid">
                    <?php
                    $availableStmt->execute(); // Reset pointer
                    while ($drone = $availableStmt->fetch()):
                        $imageUrl = !empty($drone['ImageURL']) ? "images/" . $drone['ImageURL'] : '';
                    ?>
                    <div class="drone-card">
                        <div class="drone-image">
                            <?php if (!empty($imageUrl) && file_exists($imageUrl)): ?>
                                <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($drone['Brand'] . ' ' . $drone['Model']); ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-helicopter"></i>
                            <?php endif; ?>
                        </div>
                        <div class="drone-info">
                            <h3 class="drone-name"><?php echo htmlspecialchars($drone['Brand']) . ' ' . htmlspecialchars($drone['Model']); ?></h3>
                            <div class="drone-price">Price/Day: ₱<?php echo number_format($drone['PricePerDay'], 2); ?></div>
                            <div class="status-indicator status-available">
                                <i class="fas fa-check-circle"></i> Available for Rent
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h3>No Available Drones</h3>
                    <p>All drones are currently rented.</p>
                </div>
            <?php endif; ?>

            <!-- Active Rentals -->
            <?php if ($rentedStmt->rowCount() > 0): ?>
                <h2 class="section-title" style="margin-top: 40px;">Active Rentals (<?php echo $rentedStmt->rowCount(); ?>)</h2>
                <div class="drone-grid">
                    <?php
                    $rentedStmt->execute(); // Reset pointer
                    while ($drone = $rentedStmt->fetch()):
                        $imageUrl = !empty($drone['ImageURL']) ? "images/" . $drone['ImageURL'] : '';
                        $daysLeft = ceil((strtotime($drone['RentEnd']) - time()) / (60 * 60 * 24));
                    ?>
                    <div class="drone-card">
                        <div class="drone-image">
                            <?php if (!empty($imageUrl) && file_exists($imageUrl)): ?>
                                <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($drone['Brand'] . ' ' . $drone['Model']); ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-helicopter"></i>
                            <?php endif; ?>
                        </div>
                        <div class="drone-info">
                            <h3 class="drone-name"><?php echo htmlspecialchars($drone['Brand']) . ' ' . htmlspecialchars($drone['Model']); ?></h3>
                            <div class="drone-price">Price/Day: ₱<?php echo number_format($drone['PricePerDay'], 2); ?></div>
                            
                            <div class="renter-info">
                                <div class="renter-label">Rented by:</div>
                                <?php if (!empty($drone['RenterName'])): ?>
                                    <div><?php echo htmlspecialchars($drone['RenterName']); ?></div>
                                <?php elseif (!empty($drone['Email'])): ?>
                                    <div><?php echo htmlspecialchars($drone['Email']); ?></div>
                                <?php else: ?>
                                    <div style="color: #e74c3c; font-weight: 600;">
                                        <i class="fas fa-user-slash"></i> User Account No Longer Exists
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 10px; font-size: 0.8rem; color: #7f8c8d;">
                                    <div>From: <?php echo date('M d, Y', strtotime($drone['RentStart'])); ?></div>
                                    <div>To: <?php echo date('M d, Y', strtotime($drone['RentEnd'])); ?></div>
                                    <?php if ($daysLeft > 0): ?>
                                        <div style="color: #e74c3c; font-weight: 600; margin-top: 5px;">
                                            <?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?> left
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (isset($drone['RentalID'])): ?>
                                <a href="manage_rental.php?rental_id=<?php echo $drone['RentalID']; ?>" class="btn">View Rental</a>
                            <?php else: ?>
                                <a href="admin_panel.php" class="btn">View Details</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Cancelled Rentals Section -->
            <?php if ($cancelledStmt && $cancelled_count > 0): ?>
                <h2 class="section-title" style="margin-top: 40px; color: #dc3545;">
                    <i class="fas fa-ban"></i> Cancelled Rentals (<?php echo $cancelled_count; ?>)
                </h2>
                
                <div class="cancelled-box">
                    <div>
                        <i class="fas fa-info-circle cancelled-icon"></i>
                        These rentals were cancelled by users but still have future dates. 
                        They are not counted as active rentals.
                    </div>
                </div>
                
                <div class="drone-grid">
                    <?php
                    $cancelledStmt->execute(); // Reset pointer
                    while ($drone = $cancelledStmt->fetch()):
                        $imageUrl = !empty($drone['ImageURL']) ? "images/" . $drone['ImageURL'] : '';
                        $daysLeft = ceil((strtotime($drone['RentEnd']) - time()) / (60 * 60 * 24));
                    ?>
                    <div class="drone-card cancelled">
                        <div class="drone-image">
                            <?php if (!empty($imageUrl) && file_exists($imageUrl)): ?>
                                <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($drone['Brand'] . ' ' . $drone['Model']); ?>" style="width:100%; height:100%; object-fit:cover; opacity: 0.7;">
                            <?php else: ?>
                                <i class="fas fa-helicopter"></i>
                            <?php endif; ?>
                            <div class="cancelled-badge">CANCELLED</div>
                        </div>
                        <div class="drone-info">
                            <h3 class="drone-name"><?php echo htmlspecialchars($drone['Brand']) . ' ' . htmlspecialchars($drone['Model']); ?></h3>
                            <div class="drone-price" style="color: #6c757d; text-decoration: line-through;">Price/Day: ₱<?php echo number_format($drone['PricePerDay'], 2); ?></div>
                            
                            <div class="renter-info cancelled-info">
                                <div class="renter-label cancelled-label">⚠️ CANCELLED RENTAL</div>
                                <div>Originally rented by: <?php echo htmlspecialchars($drone['RenterName'] ?? $drone['Email']); ?></div>
                                
                                <div style="margin-top: 10px; font-size: 0.8rem; color: #721c24;">
                                    <div>Original Dates:</div>
                                    <div>From: <?php echo date('M d, Y', strtotime($drone['RentStart'])); ?></div>
                                    <div>To: <?php echo date('M d, Y', strtotime($drone['RentEnd'])); ?></div>
                                    <?php if ($daysLeft > 0): ?>
                                        <div style="color: #721c24; font-weight: 600; margin-top: 5px;">
                                            Would have <?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?> left
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <button class="btn btn-cancelled" disabled>
                                <i class="fas fa-times-circle"></i> Rental Cancelled
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php elseif (isAdmin() && $hasStatusColumn): ?>
                <!-- Show empty state if no cancelled rentals -->
                <div style="text-align: center; padding: 20px; color: #28a745; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> No cancelled rentals found. All active rentals are valid.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- USER VIEW -->
            <h2 class="section-title">Available Drones for Rent</h2>
            <?php if ($stmt && $stmt->rowCount() > 0): ?>
                <div class="drone-grid">
                    <?php
                    while ($drone = $stmt->fetch()):
                        $imageUrl = !empty($drone['ImageURL']) ? "images/" . $drone['ImageURL'] : '';
                    ?>
                    <div class="drone-card">
                        <div class="drone-image">
                            <?php if (!empty($imageUrl) && file_exists($imageUrl)): ?>
                                <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($drone['Brand'] . ' ' . $drone['Model']); ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-helicopter"></i>
                            <?php endif; ?>
                        </div>
                        <div class="drone-info">
                            <h3 class="drone-name"><?php echo htmlspecialchars($drone['Brand']) . ' ' . htmlspecialchars($drone['Model']); ?></h3>
                            <div class="drone-price">Price/Day: ₱<?php echo number_format($drone['PricePerDay'], 2); ?></div>
                            <a href="rent.php?DroneID=<?php echo $drone['DroneID']; ?>" class="btn">Rent This Drone</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>No Drones Available</h3>
                    <p>Check back later for new arrivals.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Function to add status column to database
        function addStatusColumn() {
            if (confirm('This will add a "status" column and "CancelledAt" column to your rentals table. This is required to track cancelled rentals properly. Continue?')) {
                // Show loading message
                const container = document.querySelector('.container');
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'db-warning-box';
                loadingDiv.innerHTML = `
                    <div class="db-warning-icon">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <div class="db-warning-text">
                        <strong>Updating database...</strong> Please wait while we add the required columns.
                    </div>
                `;
                container.insertBefore(loadingDiv, container.firstChild.nextSibling);
                
                // Create a form to submit the database update
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'update_database.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'action';
                input.value = 'add_status_column';
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>