<?php
// superadmin_dashboard.php
require_once 'admin_auth.php';
$adminAuth->requireLogin();

// Get dashboard statistics
$stats = [
    'total_users' => 0,
    'total_owners' => 0,
    'total_drones' => 0,
    'active_rentals' => 0,
    'pending_owner_requests' => 0,
    'pending_reports' => 0
];

// Fetch statistics
$queries = [
    'total_users' => "SELECT COUNT(*) FROM USERS",
    'total_owners' => "SELECT COUNT(*) FROM USERS WHERE role = 'owner'",
    'total_drones' => "SELECT COUNT(*) FROM DRONES",
    'active_rentals' => "SELECT COUNT(*) FROM RENTALS WHERE status IN ('approved', 'overdue')",
    'pending_owner_requests' => "SELECT COUNT(*) FROM OWNER_REQUESTS WHERE status = 'pending'",
    'pending_reports' => "SELECT COUNT(*) FROM USER_REPORTS WHERE status = 'pending'"
];

foreach ($queries as $key => $query) {
    $result = $conn->query($query);
    if ($result) {
        $stats[$key] = $result->fetch_row()[0];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super-Admin Dashboard - AirUsea</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        
        .sidebar {
            background: #2c3e50;
            color: white;
            padding: 20px;
        }
        
        .main-content {
            padding: 20px;
            background: #f5f5f5;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        
        .action-btn:hover {
            background: #2980b9;
        }
        
        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>AirUsea Super-Admin</h2>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
            
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li><a href="superadmin_dashboard.php" style="color: white; display: block; padding: 10px; background: #34495e; margin: 5px 0; border-radius: 4px;">Dashboard</a></li>
                    <li><a href="manage_users.php" style="color: white; display: block; padding: 10px; margin: 5px 0;">Manage Users</a></li>
                    <li><a href="manage_owners.php" style="color: white; display: block; padding: 10px; margin: 5px 0;">Manage Owners</a></li>
                    <li><a href="view_reports.php" style="color: white; display: block; padding: 10px; margin: 5px 0;">View Reports</a></li>
                    <li><a href="system_settings.php" style="color: white; display: block; padding: 10px; margin: 5px 0;">System Settings</a></li>
                    <li><a href="audit_logs.php" style="color: white; display: block; padding: 10px; margin: 5px 0;">Audit Logs</a></li>
                    <li><a href="logout.php" style="color: white; display: block; padding: 10px; margin: 5px 0; background: #e74c3c;">Logout</a></li>
                </ul>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <h1>Super-Admin Dashboard</h1>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Drone Owners</h3>
                    <div class="stat-value"><?php echo $stats['total_owners']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Drones</h3>
                    <div class="stat-value"><?php echo $stats['total_drones']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Rentals</h3>
                    <div class="stat-value"><?php echo $stats['active_rentals']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Owner Requests</h3>
                    <div class="stat-value"><?php echo $stats['pending_owner_requests']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Reports</h3>
                    <div class="stat-value"><?php echo $stats['pending_reports']; ?></div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="manage_users.php?action=add" class="action-btn">Add New User</a>
                <a href="view_reports.php" class="action-btn">View Reports</a>
                <a href="manage_owners.php" class="action-btn">Approve Owners</a>
                <a href="system_settings.php" class="action-btn">System Settings</a>
                <a href="audit_logs.php" class="action-btn">View Audit Logs</a>
                <a href="backup_database.php" class="action-btn">Backup Database</a>
            </div>
            
            <!-- Recent Activity -->
            <div class="recent-activity">
                <h3>Recent Admin Activity</h3>
                <?php
                $activity_query = "
                    SELECT a.action_type, a.details, a.created_at, ad.adminName 
                    FROM ADMIN_AUDIT_LOG a
                    JOIN ADMINS ad ON a.adminID = ad.adminID
                    ORDER BY a.created_at DESC 
                    LIMIT 10
                ";
                
                $result = $conn->query($activity_query);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<div class='activity-item'>";
                        echo "<strong>" . htmlspecialchars($row['adminName']) . "</strong> - ";
                        echo htmlspecialchars($row['action_type']) . ": ";
                        echo htmlspecialchars($row['details']) . " - ";
                        echo "<small>" . $row['created_at'] . "</small>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>No recent activity</p>";
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>