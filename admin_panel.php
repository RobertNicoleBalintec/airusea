<?php
// admin_panel.php - FIXED SUPER-ADMIN VERSION
require 'auth.php';
requireLogin();

// Check if user is admin OR superadmin
$isAdmin = isAdmin();
$isSuperAdmin = isSuperAdmin();

if (!$isAdmin && !$isSuperAdmin) {
    header('Location: dashboard.php');
    exit();
}

require 'db.php';
require 'logger.php';

// Log access
logEvent("Accessed admin panel");

// Get user info - FIXED QUERY
$user_stmt = $pdo->prepare("
    SELECT Name, Email, role 
    FROM users 
    WHERE UserID = ?
");
$user_stmt->execute([$_SESSION['UserID']]);
$user = $user_stmt->fetch();
$display_name = !empty($user['Name']) ? $user['Name'] : $_SESSION['Email'];

// Determine role for display
$user_role = $user['role'] ?? ($isSuperAdmin ? 'superadmin' : ($isAdmin ? 'admin' : 'user'));

// Handle messages
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    $messages = [
        'phased_out' => "✅ Drone #" . ($_GET['id'] ?? 'unknown') . " has been successfully phased out.",
        'removed' => "✅ Drone has been successfully removed.",
        '1' => "✅ Drone updated successfully.",
        'price_updated' => "✅ Price changed successfully. New drone #" . ($_GET['new_id'] ?? 'unknown') . " created.",
        'user_updated' => "✅ User updated successfully.",
        'user_banned' => "✅ User banned successfully.",
        'user_unbanned' => "✅ User unbanned successfully.",
        'settings_updated' => "✅ System settings updated successfully."
    ];
    
    if (isset($messages[$_GET['success']])) {
        $success_message = $messages[$_GET['success']];
    }
}

if (isset($_GET['error'])) {
    $errors = [
        'insufficient_permissions' => "❌ Insufficient permissions to access this feature.",
        'drone_has_active_rentals' => "❌ Cannot phase out drone: It has active rentals.",
        'drone_already_phased_out' => "❌ This drone is already phased out.",
        'drone_not_found' => "❌ Drone not found.",
        'invalid_id' => "❌ Invalid ID.",
        'db_error' => "❌ Database error: " . ($_GET['message'] ?? 'Unknown error'),
        'general_error' => "❌ Error: " . ($_GET['message'] ?? 'Unknown error')
    ];
    
    if (isset($errors[$_GET['error']])) {
        $error_message = $errors[$_GET['error']];
    }
}

// Get system statistics
try {
    $stats = [];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Total admins
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin', 'superadmin')");
    $stats['total_admins'] = $stmt->fetchColumn();
    
    // Total drones
    $stmt = $pdo->query("SELECT COUNT(*) FROM drones WHERE status = 'available'");
    $stats['available_drones'] = $stmt->fetchColumn();
    
    // Active rentals
    $stmt = $pdo->query("SELECT COUNT(*) FROM rentals WHERE status IN ('approved', 'active')");
    $stats['active_rentals'] = $stmt->fetchColumn();
    
    // Pending rentals
    $stmt = $pdo->query("SELECT COUNT(*) FROM rentals WHERE status = 'pending'");
    $stats['pending_rentals'] = $stmt->fetchColumn();
    
    // Overdue rentals
    $stmt = $pdo->query("SELECT COUNT(*) FROM rentals WHERE status = 'overdue'");
    $stats['overdue_rentals'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $stats = [];
    $error_message = "❌ Error loading statistics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel | Airusea</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        /* SUPER-ADMIN SPECIFIC STYLES */
        .admin-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
            text-transform: uppercase;
        }
        
        .badge-superadmin {
            background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        /* Super-admin specific sections */
        .superadmin-only {
            border-left: 4px solid #8e44ad;
            background: #f9f0ff;
            padding-left: 15px;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid #3498db;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #7f8c8d;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        
        .action-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: #2c3e50;
            border: 2px solid #ecf0f1;
            transition: all 0.3s;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        
        .action-icon {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        
        /* Tabs for different admin sections */
        .admin-tabs {
            display: flex;
            border-bottom: 2px solid #ecf0f1;
            margin: 30px 0;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            color: #7f8c8d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #3498db;
            border-bottom-color: #3498db;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="admin-page">
    <!-- Messages -->
    <?php if ($success_message || $error_message): ?>
    <div class="message-container">
        <?php if ($success_message): ?>
        <div class="success-message" id="successMessage">
            <span>✅</span>
            <span><?= htmlspecialchars($success_message) ?></span>
            <button class="message-close" onclick="closeMessage('successMessage')">×</button>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="error-message" id="errorMessage">
            <span>❌</span>
            <span><?= htmlspecialchars($error_message) ?></span>
            <button class="message-close" onclick="closeMessage('errorMessage')">×</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="admin-container">
        <!-- Admin Header -->
        <div class="admin-header">
            <h1>Admin Panel 
                <span class="admin-badge <?= $isSuperAdmin ? 'badge-superadmin' : 'badge-admin' ?>">
                    <?= $isSuperAdmin ? 'Super Admin' : 'Admin' ?>
                </span>
            </h1>
            <div class="admin-user-info">
                <div>
                    Welcome, <strong><?= htmlspecialchars($display_name) ?></strong>
                    <span style="color: #bdc3c7;">• <?= ucfirst($user_role) ?></span>
                </div>
                <div class="admin-links">
                    <a href="dashboard.php">User Dashboard</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="admin-section">
            <h2 class="section-title">📊 System Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-value"><?= $stats['total_users'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <h3>Administrators</h3>
                    <div class="stat-value"><?= $stats['total_admins'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <h3>Available Drones</h3>
                    <div class="stat-value"><?= $stats['available_drones'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Rentals</h3>
                    <div class="stat-value"><?= $stats['active_rentals'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Rentals</h3>
                    <div class="stat-value"><?= $stats['pending_rentals'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <h3>Overdue Rentals</h3>
                    <div class="stat-value"><?= $stats['overdue_rentals'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="admin-section">
            <h2 class="section-title">⚡ Quick Actions</h2>
            <div class="quick-actions">
                <a href="#manage-users" class="action-card" onclick="showTab('users')">
                    <span class="action-icon">👥</span>
                    Manage Users
                </a>
                <a href="#manage-drones" class="action-card" onclick="showTab('drones')">
                    <span class="action-icon">🚁</span>
                    Manage Drones
                </a>
                <a href="#rentals" class="action-card" onclick="showTab('rentals')">
                    <span class="action-icon">📋</span>
                    View Rentals
                </a>
                
                <?php if ($isSuperAdmin): ?>
                <a href="system_settings.php" class="action-card superadmin-only">
                    <span class="action-icon">⚙️</span>
                    System Settings
                </a>
                <a href="audit_logs.php" class="action-card superadmin-only">
                    <span class="action-icon">📜</span>
                    Audit Logs
                </a>
                <a href="manage_admins.php" class="action-card superadmin-only">
                    <span class="action-icon">👑</span>
                    Manage Admins
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="admin-tabs">
            <button class="tab active" onclick="showTab('drones')">🚁 Drones</button>
            <button class="tab" onclick="showTab('users')">👥 Users</button>
            <button class="tab" onclick="showTab('rentals')">📋 Rentals</button>
            <?php if ($isSuperAdmin): ?>
            <button class="tab" onclick="showTab('settings')">⚙️ Settings</button>
            <?php endif; ?>
        </div>

        <!-- Tab Content: Drones Management -->
        <div id="tab-drones" class="tab-content active">
            <!-- Your existing drones management code goes here -->
            <?php include 'admin_drones_section.php'; ?>
        </div>

        <!-- Tab Content: Users Management -->
        <div id="tab-users" class="tab-content">
            <?php include 'admin_users_section.php'; ?>
        </div>

        <!-- Tab Content: Rentals -->
        <div id="tab-rentals" class="tab-content">
            <?php include 'admin_rentals_section.php'; ?>
        </div>

        <?php if ($isSuperAdmin): ?>
        <!-- Tab Content: Super-Admin Settings -->
        <div id="tab-settings" class="tab-content">
            <?php include 'admin_settings_section.php'; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(`tab-${tabName}`).classList.add('active');
            
            // Activate selected tab button
            event.target.classList.add('active');
        }
        
        // Message close functionality
        function closeMessage(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.style.animation = 'fadeOut 0.5s ease forwards';
                setTimeout(() => element.style.display = 'none', 500);
            }
        }
        
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            closeMessage('successMessage');
            closeMessage('errorMessage');
        }, 5000);
    </script>
</body>
</html>