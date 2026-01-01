<?php
session_start();
require_once 'db.php';

// Check if user is super admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['is_superadmin']) || $_SESSION['is_superadmin'] != 1) {
    header("Location: index_login.php");
    exit();
}

// Set default date range (last 30 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

// Handle date filter
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['filter'])) {
    $startDate = $_POST['start_date'] ?? $startDate;
    $endDate = $_POST['end_date'] ?? $endDate;
}

// Initialize variables
$financial = ['total_rentals' => 0, 'total_revenue' => 0, 'collected_revenue' => 0, 
              'overdue_count' => 0, 'potential_penalties' => 0];
$recentRentals = [];
$droneStats = ['total_drones' => 0, 'avg_rental_price' => 0, 
               'available_drones' => 0, 'rented_drones' => 0];

// Check if rentals table exists
try {
    $rentalsExist = $pdo->query("SHOW TABLES LIKE 'rentals'")->fetch();
    
    if ($rentalsExist) {
        // Check what columns exist in rentals table
        $stmt = $pdo->query("SHOW COLUMNS FROM rentals");
        $rentalColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Determine which price column exists
        if (in_array('totalprice', $rentalColumns)) {
            $priceColumn = 'totalprice';
        } else {
            $priceColumn = '0'; // Default if no price column
        }
        
        // Determine which date column exists
        if (in_array('rentstart', $rentalColumns)) {
            $dateColumn = 'rentstart';
        } elseif (in_array('created_at', $rentalColumns)) {
            $dateColumn = 'created_at';
        } else {
            $dateColumn = 'NOW()'; // Default if no date column
        }
        
        // Financial Summary - SAFE: Use determined column names
        $sql = "
            SELECT 
                COUNT(*) as total_rentals,
                SUM($priceColumn) as total_revenue,
                SUM(CASE WHEN status = 'completed' THEN $priceColumn ELSE 0 END) as collected_revenue,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN status = 'overdue' THEN $priceColumn * 1.5 ELSE 0 END) as potential_penalties
            FROM rentals 
            WHERE $dateColumn BETWEEN ? AND ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        $financial = $stmt->fetch() ?: $financial;
        
        // Recent rentals for police reports (simplified to avoid column errors)
        try {
            $sql = "
                SELECT r.*, u.name as user_name, u.Email as user_email
                FROM rentals r
                JOIN users u ON r.userID = u.UserID
                WHERE r.$dateColumn BETWEEN ? AND ?
                ORDER BY r.$dateColumn DESC
                LIMIT 50
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            $recentRentals = $stmt->fetchAll();
        } catch (Exception $e) {
            // If there's an error in the join, just get rentals
            $sql = "SELECT * FROM rentals WHERE $dateColumn BETWEEN ? AND ? ORDER BY $dateColumn DESC LIMIT 50";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            $recentRentals = $stmt->fetchAll();
        }
    }
} catch (Exception $e) {
    // Silently continue with defaults
}

// User Activity
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as new_users,
            SUM(CASE WHEN last_login IS NOT NULL AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_users_7d,
            SUM(CASE WHEN last_login IS NOT NULL AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_users_30d
        FROM users
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $userActivity = $stmt->fetch();
} catch (Exception $e) {
    $userActivity = ['new_users' => 0, 'active_users_7d' => 0, 'active_users_30d' => 0];
}

// Drone Statistics - FIXED: Check if drones table has rental_price column
$dronesExist = $pdo->query("SHOW TABLES LIKE 'drones'")->fetch();
if ($dronesExist) {
    try {
        // Check what columns exist in drones table
        $stmt = $pdo->query("SHOW COLUMNS FROM drones");
        $droneColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Determine which price column exists
        if (in_array('rental_price', $droneColumns)) {
            $dronePriceColumn = 'AVG(rental_price) as avg_rental_price';
        } elseif (in_array('price', $droneColumns)) {
            $dronePriceColumn = 'AVG(price) as avg_rental_price';
        } else {
            $dronePriceColumn = '0 as avg_rental_price';
        }
        
        $sql = "
            SELECT 
                COUNT(*) as total_drones,
                $dronePriceColumn,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_drones,
                SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented_drones
            FROM drones
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $droneStats = $stmt->fetch() ?: $droneStats;
    } catch (Exception $e) {
        // Silently continue with defaults
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Reports | Super Admin</title>
    <style>
        /* KEEP ALL YOUR EXISTING STYLES - THEY ARE CORRECT */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(to right, #2c3e50, #34495e);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 4px solid #f39c12;
        }
        
        .header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .super-admin-badge {
            display: inline-block;
            background: #8e44ad;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .page-title {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-description {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.6;
            max-width: 800px;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .filter-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-filter {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .date-group {
            display: flex;
            flex-direction: column;
        }
        
        .date-group label {
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .date-group input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            min-width: 200px;
        }
        
        .filter-btn {
            padding: 12px 30px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
        
        .filter-btn:hover {
            background: #0056b3;
        }
        
        .report-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .report-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
        }
        
        .report-card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid;
        }
        
        .card-financial h3 { border-color: #2ecc71; }
        .card-users h3 { border-color: #3498db; }
        .card-drones h3 { border-color: #9b59b6; }
        .card-rentals h3 { border-color: #f39c12; }
        
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .metric-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: transform 0.3s;
        }
        
        .metric-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        
        .metric-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-value.currency:before {
            content: '$';
        }
        
        .export-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .export-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-pdf { background: #e74c3c; color: white; }
        .btn-excel { background: #2ecc71; color: white; }
        .btn-csv { background: #3498db; color: white; }
        .btn-print { background: #f39c12; color: white; }
        
        .export-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .recent-rentals {
            margin-top: 40px;
        }
        
        .recent-rentals h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .rental-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .rental-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }
        
        .rental-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .rental-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-completed { background: #d4edda; color: #155724; }
        .status-active { background: #cce5ff; color: #004085; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #f8f9fa; color: #6c757d; }
        
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: bold;
        }
        
        .back-btn:hover {
            background: #545b62;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .quick-stat {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid;
        }
        
        .quick-stat.revenue { border-color: #2ecc71; }
        .quick-stat.users { border-color: #3498db; }
        .quick-stat.drones { border-color: #9b59b6; }
        .quick-stat.rentals { border-color: #f39c12; }
        
        .quick-stat .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        
        .quick-stat .label {
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .report-type {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .report-type-btn {
            padding: 12px 25px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .report-type-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .report-type-btn:hover {
            background: #e9ecef;
            border-color: #ced4da;
        }
        
        .report-type-btn.active:hover {
            background: #0069d9;
            border-color: #0062cc;
        }
        
        .report-content {
            display: none;
        }
        
        .report-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📈 Generate Reports <span class="super-admin-badge">SUPER ADMIN</span></h1>
            <div style="color: white;">
                <p><strong><?php echo htmlspecialchars($_SESSION['Name'] ?? 'Admin'); ?></strong></p>
                <p><small><?php echo htmlspecialchars($_SESSION['Email'] ?? ''); ?></small></p>
            </div>
        </div>
        
        <div class="content">
            <div class="page-title">
                📈 Generate Reports
            </div>
            
            <div class="page-description">
                Create system reports, financial summaries, police reports, and activity analytics.
            </div>
            
            <div class="quick-stats">
                <div class="quick-stat revenue">
                    <div class="value currency"><?php echo number_format($financial['total_revenue'] ?? 0, 2); ?></div>
                    <div class="label">Total Revenue</div>
                </div>
                <div class="quick-stat users">
                    <div class="value"><?php echo $userActivity['new_users'] ?? 0; ?></div>
                    <div class="label">New Users</div>
                </div>
                <div class="quick-stat drones">
                    <div class="value"><?php echo $droneStats['total_drones'] ?? 0; ?></div>
                    <div class="label">Total Drones</div>
                </div>
                <div class="quick-stat rentals">
                    <div class="value"><?php echo $financial['total_rentals'] ?? 0; ?></div>
                    <div class="label">Total Rentals</div>
                </div>
            </div>
            
            <div class="filter-section">
                <h3>📅 Filter Reports by Date</h3>
                <form method="POST" class="date-filter">
                    <div class="date-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $startDate; ?>" required>
                    </div>
                    <div class="date-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?php echo $endDate; ?>" required>
                    </div>
                    <div>
                        <button type="submit" name="filter" class="filter-btn">🔍 Apply Filter</button>
                    </div>
                </form>
                <p style="color: #7f8c8d; margin-top: 15px; font-size: 14px;">
                    Showing data from <?php echo date('F j, Y', strtotime($startDate)); ?> to <?php echo date('F j, Y', strtotime($endDate)); ?>
                </p>
            </div>
            
            <div class="report-type">
                <button class="report-type-btn active" onclick="showReport('financial')">💰 Financial Summary</button>
                <button class="report-type-btn" onclick="showReport('users')">👥 User Analytics</button>
                <button class="report-type-btn" onclick="showReport('drones')">🚁 Drone Statistics</button>
                <button class="report-type-btn" onclick="showReport('police')">👮 Police Reports</button>
            </div>
            
            <!-- Financial Summary Report -->
            <div id="financial-report" class="report-content active">
                <div class="report-card card-financial">
                    <h3>💰 Financial Summary Report</h3>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value currency"><?php echo number_format($financial['total_revenue'] ?? 0, 2); ?></div>
                            <div class="metric-label">Total Revenue</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value currency"><?php echo number_format($financial['collected_revenue'] ?? 0, 2); ?></div>
                            <div class="metric-label">Collected Revenue</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value currency"><?php echo number_format($financial['potential_penalties'] ?? 0, 2); ?></div>
                            <div class="metric-label">Potential Penalties</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value"><?php echo $financial['total_rentals'] ?? 0; ?></div>
                            <div class="metric-label">Total Rentals</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value"><?php echo $financial['overdue_count'] ?? 0; ?></div>
                            <div class="metric-label">Overdue Rentals</div>
                        </div>
                        <div class="metric-item">
                            <?php 
                            $avgRental = ($financial['total_rentals'] > 0) ? ($financial['total_revenue'] / $financial['total_rentals']) : 0;
                            ?>
                            <div class="metric-value currency"><?php echo number_format($avgRental, 2); ?></div>
                            <div class="metric-label">Avg. Rental Value</div>
                        </div>
                    </div>
                    
                    <div class="export-buttons">
                        <button class="export-btn btn-pdf" onclick="exportReport('financial', 'pdf')">📄 Export as PDF</button>
                        <button class="export-btn btn-excel" onclick="exportReport('financial', 'excel')">📊 Export as Excel</button>
                        <button class="export-btn btn-print" onclick="window.print()">🖨️ Print Report</button>
                    </div>
                </div>
            </div>
            
            <!-- User Analytics Report -->
            <div id="users-report" class="report-content">
                <div class="report-card card-users">
                    <h3>👥 User Analytics Report</h3>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value"><?php echo $userActivity['new_users'] ?? 0; ?></div>
                            <div class="metric-label">New Users</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value"><?php echo $userActivity['active_users_7d'] ?? 0; ?></div>
                            <div class="metric-label">Active (7 Days)</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value"><?php echo $userActivity['active_users_30d'] ?? 0; ?></div>
                            <div class="metric-label">Active (30 Days)</div>
                        </div>
                        <div class="metric-item">
                            <?php
                            // Get total users count
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
                            $totalUsers = $stmt->fetch();
                            ?>
                            <div class="metric-value"><?php echo $totalUsers['total'] ?? 0; ?></div>
                            <div class="metric-label">Total Users</div>
                        </div>
                        <div class="metric-item">
                            <?php
                            // Count user roles
                            $stmt = $pdo->query("SELECT COUNT(*) as admins FROM users WHERE role = 'admin' OR role = 'superadmin'");
                            $admins = $stmt->fetch();
                            ?>
                            <div class="metric-value"><?php echo $admins['admins'] ?? 0; ?></div>
                            <div class="metric-label">Admins</div>
                        </div>
                        <div class="metric-item">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) as owners FROM users WHERE role LIKE '%owner%'");
                            $owners = $stmt->fetch();
                            ?>
                            <div class="metric-value"><?php echo $owners['owners'] ?? 0; ?></div>
                            <div class="metric-label">Drone Owners</div>
                        </div>
                    </div>
                    
                    <div class="export-buttons">
                        <button class="export-btn btn-pdf" onclick="exportReport('users', 'pdf')">📄 Export as PDF</button>
                        <button class="export-btn btn-excel" onclick="exportReport('users', 'excel')">📊 Export as Excel</button>
                        <button class="export-btn btn-csv" onclick="exportReport('users', 'csv')">📋 Export as CSV</button>
                    </div>
                </div>
            </div>
            
            <!-- Drone Statistics Report -->
            <div id="drones-report" class="report-content">
                <div class="report-card card-drones">
                    <h3>🚁 Drone Statistics Report</h3>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value"><?php echo $droneStats['total_drones'] ?? 0; ?></div>
                            <div class="metric-label">Total Drones</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value"><?php echo $droneStats['available_drones'] ?? 0; ?></div>
                            <div class="metric-label">Available</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value"><?php echo $droneStats['rented_drones'] ?? 0; ?></div>
                            <div class="metric-label">Currently Rented</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value currency"><?php echo number_format($droneStats['avg_rental_price'] ?? 0, 2); ?></div>
                            <div class="metric-label">Avg. Rental Price</div>
                        </div>
                        <div class="metric-item">
                            <?php
                            $utilization = ($droneStats['total_drones'] > 0) ? (($droneStats['rented_drones'] / $droneStats['total_drones']) * 100) : 0;
                            ?>
                            <div class="metric-value"><?php echo number_format($utilization, 1); ?>%</div>
                            <div class="metric-label">Utilization Rate</div>
                        </div>
                        <div class="metric-item">
                            <?php
                            $pending = 0;
                            if ($dronesExist) {
                                $stmt = $pdo->query("SELECT COUNT(*) as pending FROM drones WHERE status = 'pending'");
                                $result = $stmt->fetch();
                                $pending = $result['pending'] ?? 0;
                            }
                            ?>
                            <div class="metric-value"><?php echo $pending; ?></div>
                            <div class="metric-label">Pending Approval</div>
                        </div>
                    </div>
                    
                    <div class="export-buttons">
                        <button class="export-btn btn-pdf" onclick="exportReport('drones', 'pdf')">📄 Export as PDF</button>
                        <button class="export-btn btn-excel" onclick="exportReport('drones', 'excel')">📊 Export as Excel</button>
                        <button class="export-btn btn-csv" onclick="exportReport('drones', 'csv')">📋 Export as CSV</button>
                    </div>
                </div>
            </div>
            
            <!-- Police Reports -->
            <div id="police-report" class="report-content">
                <div class="recent-rentals">
                    <h3>👮 Police Reports - Recent Rental Activity</h3>
                    <p style="color: #666; margin-bottom: 20px;">Detailed rental records for law enforcement purposes.</p>
                    
                    <?php if (empty($recentRentals)): ?>
                        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                            <h3>No Rental Data Found</h3>
                            <p>No rental records available for the selected date range.</p>
                            <p>If rentals table doesn't exist, it will be empty.</p>
                        </div>
                    <?php else: ?>
                        <table class="rental-table">
                            <thead>
                                <tr>
                                    <th>Rental ID</th>
                                    <th>User</th>
                                    <th>Rental Start</th>
                                    <th>Rental End</th>
                                    <th>Total Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRentals as $rental): 
                                    $status = $rental['status'] ?? 'active';
                                    $statusClass = "status-" . $status;
                                ?>
                                <tr>
                                    <td>#<?php echo $rental['rentD'] ?? $rental['rentalID'] ?? 'N/A'; ?></td>
                                    <td>
                                        <?php if (isset($rental['user_name'])): ?>
                                        <div><strong><?php echo htmlspecialchars($rental['user_name']); ?></strong></div>
                                        <div style="font-size: 12px; color: #7f8c8d;"><?php echo htmlspecialchars($rental['user_email'] ?? ''); ?></div>
                                        <?php else: ?>
                                        User #<?php echo $rental['userID'] ?? 'N/A'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $rentalDate = $rental['rentstart'] ?? $rental['rental_date'] ?? 'N/A';
                                        echo $rentalDate !== 'N/A' ? date('Y-m-d', strtotime($rentalDate)) : 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (isset($rental['rendue']) && $rental['rendue']): ?>
                                            <?php echo date('Y-m-d', strtotime($rental['rendue'])); ?>
                                        <?php else: ?>
                                            <em>Not returned</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>$<?php echo number_format($rental['totalprice'] ?? 0, 2); ?></strong></td>
                                    <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span></td>
                                    <td>
                                        <button onclick="generatePoliceReport(<?php echo $rental['rentD'] ?? $rental['rentalID'] ?? 0; ?>)" 
                                                class="export-btn btn-pdf" style="padding: 5px 10px; font-size: 12px;">
                                            👮 Generate Report
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <div class="export-buttons" style="margin-top: 20px;">
                        <button class="export-btn btn-pdf" onclick="exportReport('police', 'pdf')">📄 Export All as PDF</button>
                        <button class="export-btn btn-excel" onclick="exportReport('police', 'excel')">📊 Export as Excel</button>
                        <button class="export-btn btn-print" onclick="window.print()">🖨️ Print Report</button>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 40px; padding: 20px; background: #e8f4f8; border-radius: 10px; border-left: 4px solid #3498db;">
                <h4 style="color: #004085; margin-bottom: 10px;">📊 Report Generation Notes</h4>
                <p style="color: #004085; margin-bottom: 10px;">• Financial reports include all rental transactions within selected date range.</p>
                <p style="color: #004085; margin-bottom: 10px;">• Police reports provide detailed rental records for law enforcement purposes.</p>
                <p style="color: #004085;">• All reports can be exported in multiple formats (PDF, Excel, CSV).</p>
            </div>
            
            <a href="superadmin_panel.php" class="back-btn">← Back to Super Admin Panel</a>
        </div>
    </div>
    
    <script>
        function showReport(reportType) {
            // Update active button
            document.querySelectorAll('.report-type-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.includes(reportType === 'financial' ? 'Financial' : 
                                           reportType === 'users' ? 'User' : 
                                           reportType === 'drones' ? 'Drone' : 'Police')) {
                    btn.classList.add('active');
                }
            });
            
            // Show selected report
            document.querySelectorAll('.report-content').forEach(content => {
                content.style.display = 'none';
            });
            document.getElementById(`${reportType}-report`).style.display = 'block';
        }
        
        function exportReport(reportType, format) {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            
            alert(`Exporting ${reportType} report in ${format.toUpperCase()} format\nDate Range: ${startDate} to ${endDate}\n\nThis would generate and download the report file.`);
            
            // In a real system, this would make an AJAX call to generate the report
            // window.location.href = `export_report.php?type=${reportType}&format=${format}&start=${startDate}&end=${endDate}`;
        }
        
        function generatePoliceReport(rentalID) {
            alert(`Generating police report for Rental ID: ${rentalID}\n\nThis would create a detailed PDF report including:\n• Rental information\n• User details\n• Drone specifications\n• Timestamps and locations\n• Payment information\n\nReport would be saved to police_reports/ folder.`);
        }
    </script>
</body>
</html>