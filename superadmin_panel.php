<?php
// superadmin_panel.php - COMPLETELY FIXED VERSION
session_start();

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: index_login.php");
    exit();
}

require_once 'db.php';

// Check if user is super admin (multiple checks)
$isSuperAdmin = false;

// Check session first
if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] == 1) {
    $isSuperAdmin = true;
} else {
    // Check database
    $stmt = $pdo->prepare("SELECT COUNT(*) as is_super FROM super_admins WHERE userID = ?");
    $stmt->execute([$_SESSION['UserID']]);
    $result = $stmt->fetch();
    
    if ($result && $result['is_super'] > 0) {
        $isSuperAdmin = true;
        $_SESSION['is_superadmin'] = 1;
        $_SESSION['role'] = 'superadmin';
        $_SESSION['is_admin'] = 1;
    }
}

// If not super admin, redirect
if (!$isSuperAdmin) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
        header("Location: admin_panel.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

// Get user info - fetch with specific column names to avoid issues
$stmt = $pdo->prepare("SELECT 
    userID, 
    name, 
    Email as email, 
    is_admin, 
    role,
    last_login 
    FROM users WHERE userID = ?");
$stmt->execute([$_SESSION['UserID']]);
$user = $stmt->fetch();

// Initialize stats array
$stats = [];
$error = '';

try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    // Total admins - check if admins table exists, otherwise count from users
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM admins");
        $stats['total_admins'] = $stmt->fetch()['total'];
    } catch (Exception $e) {
        // Fallback: count admin users
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 1 OR role = 'admin' OR role = 'superadmin'");
        $stats['total_admins'] = $stmt->fetch()['total'];
    }
    
    // Total owners
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role LIKE '%owner%'");
    $stats['total_owners'] = $stmt->fetch()['total'];
    
    // Check if drones table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'drones'")->fetchAll();
    if (count($tables) > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM drones");
        $stats['total_drones'] = $stmt->fetch()['total'];
    } else {
        $stats['total_drones'] = 0;
    }
    
    // Check if rentals table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'rentals'")->fetchAll();
    if (count($tables) > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM rentals");
        $stats['total_rentals'] = $stmt->fetch()['total'];
    } else {
        $stats['total_rentals'] = 0;
    }
    
    // Check if owner_requests table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'owner_requests'")->fetchAll();
    if (count($tables) > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM owner_requests WHERE status = 'pending'");
        $stats['pending_requests'] = $stmt->fetch()['total'];
    } else {
        $stats['pending_requests'] = 0;
    }
    
    // Overdue rentals
    if (isset($stats['total_rentals']) && $stats['total_rentals'] > 0) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM rentals WHERE status = 'overdue'");
            $stats['overdue_rentals'] = $stmt->fetch()['total'];
        } catch (Exception $e) {
            $stats['overdue_rentals'] = 0;
        }
    } else {
        $stats['overdue_rentals'] = 0;
    }
    
} catch (PDOException $e) {
    $error = "Error fetching statistics: " . $e->getMessage();
}

// Safely get user display information
$userName = htmlspecialchars($user['name'] ?? ($_SESSION['Name'] ?? 'Admin'));
$userEmail = htmlspecialchars($user['email'] ?? ($_SESSION['Email'] ?? 'No email found'));
$userID = htmlspecialchars($user['userID'] ?? $_SESSION['UserID'] ?? 'Unknown');
$lastLogin = isset($user['last_login']) && $user['last_login'] ? $user['last_login'] : date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Panel | Airusea Drone Rental</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
            border-bottom: 4px solid #8e44ad;
        }
        
        .header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info p {
            margin: 3px 0;
            font-size: 14px;
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
        
        .dashboard {
            padding: 25px;
        }
        
        .welcome-section {
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #8e44ad;
        }
        
        .welcome-section h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
            border-top: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card.users { border-color: #3498db; }
        .stat-card.admins { border-color: #2ecc71; }
        .stat-card.owners { border-color: #f39c12; }
        .stat-card.drones { border-color: #1abc9c; }
        .stat-card.rentals { border-color: #9b59b6; }
        .stat-card.pending { border-color: #e67e22; }
        .stat-card.overdue { border-color: #e74c3c; }
        
        .stat-card h3 {
            font-size: 13px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        
        .function-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .function-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            border: 2px solid #ecf0f1;
        }
        
        .function-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #8e44ad;
        }
        
        .function-card h3 {
            font-size: 18px;
            margin-bottom: 12px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .function-card p {
            color: #666;
            line-height: 1.5;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .btn {
            display: inline-block;
            background: #8e44ad;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #732d91;
        }
        
        .footer {
            margin-top: 40px;
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: background 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        .home-btn {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
        }
        
        .home-btn:hover {
            text-decoration: underline;
        }
        
        .quick-actions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .quick-actions h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }
        
        .action-btn.emergency {
            background: #e74c3c;
            color: white;
        }
        
        .action-btn.normal {
            background: #3498db;
            color: white;
        }
        
        .action-btn.info {
            background: #2ecc71;
            color: white;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
                padding: 20px;
            }
            
            .user-info {
                text-align: center;
            }
            
            .stats-grid,
            .function-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                text-align: center;
            }
        }
        
        .error-box {
            background: #ffeaea;
            color: #c0392b;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #c0392b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👑 SUPER ADMIN PANEL <span class="super-admin-badge">SUPER ADMIN</span></h1>
            <div class="user-info">
                <p><strong>Welcome, <?php echo $userName; ?></strong></p>
                <p><?php echo $userEmail; ?></p>
                <p><small>User ID: <?php echo $userID; ?></small></p>
            </div>
        </div>
        
        <div class="dashboard">
            <?php if ($error): ?>
                <div class="error-box">
                    <strong>⚠ Warning:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="welcome-section">
                <h2>🚀 Welcome to Super Admin Command Center</h2>
                <p>You have full system control. Manage users, approve requests, configure penalties, and monitor all activities.</p>
                <p><small>Last login: <?php echo htmlspecialchars($lastLogin); ?></small></p>
            </div>
            
            <h2 style="color: #2c3e50; margin-bottom: 20px;">📊 System Overview</h2>
            <div class="stats-grid">
                <div class="stat-card users">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $stats['total_users'] ?? 0; ?></div>
                    <small>Registered users</small>
                </div>
                
                <div class="stat-card admins">
                    <h3>Administrators</h3>
                    <div class="number"><?php echo $stats['total_admins'] ?? 0; ?></div>
                    <small>Admins + Super Admins</small>
                </div>
                
                <div class="stat-card owners">
                    <h3>Drone Owners</h3>
                    <div class="number"><?php echo $stats['total_owners'] ?? 0; ?></div>
                    <small>Approved owners</small>
                </div>
                
                <div class="stat-card drones">
                    <h3>Total Drones</h3>
                    <div class="number"><?php echo $stats['total_drones'] ?? 0; ?></div>
                    <small>Available drones</small>
                </div>
                
                <div class="stat-card rentals">
                    <h3>Total Rentals</h3>
                    <div class="number"><?php echo $stats['total_rentals'] ?? 0; ?></div>
                    <small>All-time rentals</small>
                </div>
                
                <div class="stat-card pending">
                    <h3>Pending Requests</h3>
                    <div class="number"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                    <small>Awaiting approval</small>
                </div>
                
                <div class="stat-card overdue">
                    <h3>Overdue Rentals</h3>
                    <div class="number"><?php echo $stats['overdue_rentals'] ?? 0; ?></div>
                    <small>Require attention</small>
                </div>
            </div>
            
            <h2 style="color: #2c3e50; margin-top: 40px; margin-bottom: 20px;">🔧 Super Admin Functions</h2>
            <div class="function-grid">
                <a href="manage_users.php" class="function-card">
                    <h3>👥 Manage Users</h3>
                    <p>View, delete, ban, or suspend users. Access complete user information and activity logs.</p>
                    <span class="btn">Manage Users</span>
                </a>
                
                <a href="approve_owners.php" class="function-card">
                    <h3>✅ Approve Owner Requests</h3>
                    <p>Review and approve/reject user requests to become drone owners.</p>
                    <span class="btn">Review Requests</span>
                </a>
                
                <a href="approve_drones.php" class="function-card">
                    <h3>🚁 Approve Drone Uploads</h3>
                    <p>Approve new drones uploaded by owners before they become available for rent.</p>
                    <span class="btn">Approve Drones</span>
                </a>
                
                <a href="set_penalties.php" class="function-card">
                    <h3>💰 Set Overdue Penalties</h3>
                    <p>Configure penalty rates per day for overdue drone returns.</p>
                    <span class="btn">Set Penalties</span>
                </a>
                
                <a href="reports.php" class="function-card">
                    <h3>📈 Generate Reports</h3>
                    <p>Create system reports, financial summaries, police reports, and activity analytics.</p>
                    <span class="btn">View Reports</span>
                </a>
                
                <a href="manage_admins.php" class="function-card">
                    <h3>👑 Manage Admins</h3>
                    <p>Assign admin privileges to users and manage admin permissions. Control access levels.</p>
                    <span class="btn">Manage Admins</span>
                </a>
            </div>
            
            <div class="quick-actions">
                <h3>🚨 Quick Actions</h3>
                <div class="action-buttons">
                    <a href="reset_superadmin.php" class="action-btn emergency">Reset Password</a>
                    <a href="system_settings.php" class="action-btn normal">System Settings</a>
                    <a href="logs.php" class="action-btn info">View Logs</a>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <a href="index.php" class="home-btn">← Back to Home Page</a>
            <div>
                <span style="color: #7f8c8d; font-size: 13px; margin-right: 20px;">
                    Session: <?php echo substr(session_id(), 0, 10); ?>...
                </span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh stats every 60 seconds
        setInterval(() => {
            fetch('get_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update stats if needed
                    console.log('Stats updated:', data);
                })
                .catch(error => console.error('Error updating stats:', error));
        }, 60000);
        
        // Display current time
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString();
            }
        }
        
        // Add time display to header
        const header = document.querySelector('.header h1');
        if (header) {
            const timeSpan = document.createElement('span');
            timeSpan.style.cssText = 'font-size: 12px; opacity: 0.7; margin-left: 10px; font-weight: normal;';
            timeSpan.id = 'current-time';
            header.appendChild(timeSpan);
            
            setInterval(updateTime, 1000);
            updateTime();
        }
        
        // Add confirmation for critical actions
        document.querySelectorAll('.function-card[href*="manage"], .function-card[href*="delete"], .function-card[href*="reset"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href.includes('delete') || this.href.includes('reset')) {
                    if (!confirm('⚠️ This is a critical action. Are you sure?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>