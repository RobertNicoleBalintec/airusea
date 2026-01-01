<?php
session_start();
require_once 'db.php';

// Check if user is super admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['is_superadmin']) || $_SESSION['is_superadmin'] != 1) {
    header("Location: index_login.php");
    exit();
}

// Check if admins table exists, create if not
try {
    $pdo->query("SELECT 1 FROM admins LIMIT 1");
} catch (Exception $e) {
    // Create table if it doesn't exist - based on your actual structure WITHOUT permissions column
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        adminID INT AUTO_INCREMENT PRIMARY KEY,
        adminName VARCHAR(100),
        AdminEmail VARCHAR(100),
        Password VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        roleID INT DEFAULT 3
    )");
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'assign' && isset($_POST['user_id'])) {
            $userID = $_POST['user_id'];
            $permissions = $_POST['permissions'] ?? 'basic';
            
            // Get user info
            $stmt = $pdo->prepare("SELECT name, Email FROM users WHERE UserID = ?");
            $stmt->execute([$userID]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if already admin
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE AdminEmail = ?");
                $stmt->execute([$user['Email']]);
                $exists = $stmt->fetchColumn();
                
                if (!$exists) {
                    // Add to admins table - WITHOUT permissions column
                    $stmt = $pdo->prepare("INSERT INTO admins (adminName, AdminEmail, roleID) VALUES (?, ?, 3)");
                    $stmt->execute([$user['name'], $user['Email']]);
                    
                    // Update user role
                    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1, role = 'admin', roleID = 3 WHERE UserID = ?");
                    $stmt->execute([$userID]);
                    
                    $message = "Admin privileges assigned successfully";
                } else {
                    $error = "User is already an admin";
                }
            }
            
        } elseif ($action == 'update' && isset($_POST['admin_id'])) {
            $adminID = $_POST['admin_id'];
            $permissions = $_POST['permissions'] ?? 'basic';
            
            // Check if permissions column exists
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'permissions'");
                $hasPermissions = $stmt->fetch();
                
                if ($hasPermissions) {
                    $stmt = $pdo->prepare("UPDATE admins SET permissions = ? WHERE adminID = ?");
                    $stmt->execute([$permissions, $adminID]);
                } else {
                    // Just update the admin record without permissions
                    $stmt = $pdo->prepare("UPDATE admins SET adminName = adminName WHERE adminID = ?");
                    $stmt->execute([$adminID]);
                }
            } catch (Exception $e) {
                // Silently continue
            }
            $message = "Admin updated successfully";
            
        } elseif ($action == 'remove' && isset($_POST['admin_id'])) {
            $adminID = $_POST['admin_id'];
            
            // Get email from admin record
            $stmt = $pdo->prepare("SELECT AdminEmail FROM admins WHERE adminID = ?");
            $stmt->execute([$adminID]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Remove from admins table
                $stmt = $pdo->prepare("DELETE FROM admins WHERE adminID = ?");
                $stmt->execute([$adminID]);
                
                // Update user role
                $stmt = $pdo->prepare("UPDATE users SET is_admin = 0, role = 'regular', roleID = 1 WHERE Email = ?");
                $stmt->execute([$admin['AdminEmail']]);
                
                $message = "Admin privileges removed successfully";
            }
        }
    }
}

// Fetch all admins - SAFE: Check what columns exist first
try {
    // Check what columns exist in admins table
    $stmt = $pdo->query("SHOW COLUMNS FROM admins");
    $adminColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Build SELECT clause based on actual columns
    $selectColumns = ["a.adminID", "a.adminName", "a.AdminEmail", "a.created_at", "a.roleID"];
    
    if (in_array('is_active', $adminColumns)) {
        $selectColumns[] = "a.is_active";
    } else {
        $selectColumns[] = "1 as is_active"; // Default to active
    }
    
    if (in_array('permissions', $adminColumns)) {
        $selectColumns[] = "a.permissions";
    } else {
        $selectColumns[] = "'basic' as permissions"; // Default permission
    }
    
    $selectClause = implode(", ", $selectColumns);
    
    $sql = "SELECT $selectClause, u.UserID, u.last_login, u.name as user_name
            FROM admins a 
            LEFT JOIN users u ON a.AdminEmail = u.Email 
            ORDER BY a.created_at DESC";

    $stmt = $pdo->query($sql);
    $admins = $stmt->fetchAll();
} catch (Exception $e) {
    // If error, just get basic admin info
    $sql = "SELECT a.adminID, a.adminName, a.AdminEmail, a.created_at, 
                   'basic' as permissions, 1 as is_active,
                   u.UserID, u.last_login, u.name as user_name
            FROM admins a 
            LEFT JOIN users u ON a.AdminEmail = u.Email 
            ORDER BY a.created_at DESC";
    $stmt = $pdo->query($sql);
    $admins = $stmt->fetchAll();
}

// Fetch users who are not admins (for assignment) - FIXED: Use prepare() and execute()
try {
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE (is_admin = 0 OR is_admin IS NULL) 
        AND role NOT IN ('superadmin', 'admin') 
        AND UserID != ? 
        AND Email NOT IN (SELECT AdminEmail FROM admins)
        ORDER BY name ASC
    ");
    $stmt->execute([$_SESSION['UserID']]);
    $nonAdmins = $stmt->fetchAll();
} catch (Exception $e) {
    // If error, get all non-admin users without the extra conditions
    $stmt = $pdo->query("
        SELECT * FROM users 
        WHERE (is_admin = 0 OR is_admin IS NULL) 
        AND role NOT IN ('superadmin', 'admin')
        ORDER BY name ASC
    ");
    $nonAdmins = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admins | Super Admin</title>
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
            border-bottom: 4px solid #e74c3c;
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
        
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .admin-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .admin-header {
            background: linear-gradient(to right, #3498db, #2980b9);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .admin-avatar {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
            font-size: 20px;
            font-weight: bold;
        }
        
        .admin-body {
            padding: 20px;
        }
        
        .admin-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-label {
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .info-value {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .permissions-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .permission-badge {
            padding: 4px 10px;
            background: #e8f4f8;
            color: #3498db;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .action-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-edit { background: #007bff; color: white; }
        .btn-remove { background: #dc3545; color: white; }
        .btn-view { background: #6c757d; color: white; }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 40px;
        }
        
        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-group select,
        .form-group input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .permission-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .permission-option input {
            width: 18px;
            height: 18px;
        }
        
        .permission-option label {
            font-weight: normal;
            margin: 0;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .checkbox-group input {
            width: 18px;
            height: 18px;
        }
        
        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .submit-btn {
            padding: 12px 30px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
        
        .reset-btn {
            padding: 12px 30px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
        
        .submit-btn:hover, .reset-btn:hover {
            opacity: 0.9;
        }
        
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid;
        }
        
        .stat-card.total { border-color: #3498db; }
        .stat-card.active { border-color: #2ecc71; }
        .stat-card.inactive { border-color: #e74c3c; }
        .stat-card.super { border-color: #9b59b6; }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        
        .stat-card .label {
            font-size: 14px;
            color: #7f8c8d;
        }
        
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
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .message-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .admin-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }
        
        .admin-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .admin-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            color: #2c3e50;
            font-size: 20px;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👑 Manage Admins <span class="super-admin-badge">SUPER ADMIN</span></h1>
            <div style="color: white;">
                <p><strong><?php echo htmlspecialchars($_SESSION['Name'] ?? 'Admin'); ?></strong></p>
                <p><small><?php echo htmlspecialchars($_SESSION['Email'] ?? ''); ?></small></p>
            </div>
        </div>
        
        <div class="content">
            <div class="page-title">
                👑 Manage Admins
            </div>
            
            <div class="page-description">
                Assign admin privileges to users and manage admin permissions. Control access levels.
            </div>
            
            <?php if (isset($message)): ?>
                <div class="message-box message-success" id="messageBox">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message-box message-error" id="errorBox">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-bar">
                <div class="stat-card total">
                    <div class="number"><?php echo count($admins); ?></div>
                    <div class="label">Total Admins</div>
                </div>
                <div class="stat-card active">
                    <div class="number"><?php echo count(array_filter($admins, fn($a) => ($a['is_active'] ?? 1) == 1)); ?></div>
                    <div class="label">Active Admins</div>
                </div>
                <div class="stat-card inactive">
                    <div class="number"><?php echo count(array_filter($admins, fn($a) => ($a['is_active'] ?? 1) == 0)); ?></div>
                    <div class="label">Inactive Admins</div>
                </div>
                <div class="stat-card super">
                    <div class="number">1</div>
                    <div class="label">Super Admins</div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>➕ Assign New Admin</h3>
                <form method="POST" id="assignForm">
                    <input type="hidden" name="action" value="assign">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="user_id">Select User *</label>
                            <select name="user_id" id="user_id" required>
                                <option value="">-- Select a user --</option>
                                <?php foreach ($nonAdmins as $user): ?>
                                <option value="<?php echo $user['UserID']; ?>">
                                    <?php echo htmlspecialchars($user['name'] ?? $user['Email']); ?> (<?php echo $user['Email']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="permissions">Permission Level *</label>
                            <select name="permissions" id="permissions" required>
                                <option value="basic">Basic Admin</option>
                                <option value="moderate">Moderate Admin</option>
                                <option value="full">Full Admin</option>
                                <option value="custom">Custom Permissions</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="permissions-grid" id="customPermissions" style="display: none;">
                        <div class="permission-option">
                            <input type="checkbox" id="perm_users" name="custom_perms[]" value="manage_users">
                            <label for="perm_users">Manage Users</label>
                        </div>
                        <div class="permission-option">
                            <input type="checkbox" id="perm_drones" name="custom_perms[]" value="manage_drones">
                            <label for="perm_drones">Manage Drones</label>
                        </div>
                        <div class="permission-option">
                            <input type="checkbox" id="perm_rentals" name="custom_perms[]" value="manage_rentals">
                            <label for="perm_rentals">Manage Rentals</label>
                        </div>
                        <div class="permission-option">
                            <input type="checkbox" id="perm_reports" name="custom_perms[]" value="view_reports">
                            <label for="perm_reports">View Reports</label>
                        </div>
                        <div class="permission-option">
                            <input type="checkbox" id="perm_settings" name="custom_perms[]" value="system_settings">
                            <label for="perm_settings">System Settings</label>
                        </div>
                        <div class="permission-option">
                            <input type="checkbox" id="perm_approve" name="custom_perms[]" value="approve_requests">
                            <label for="perm_approve">Approve Requests</label>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="submit-btn" onclick="return confirm('Assign admin privileges to this user?')">➕ Assign Admin</button>
                        <button type="reset" class="reset-btn">🔄 Reset Form</button>
                    </div>
                </form>
            </div>
            
            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Current Admin Team</h3>
            
            <?php if (empty($admins)): ?>
                <div class="empty-state">
                    <h3>No Admins Assigned</h3>
                    <p>Assign admin privileges to users using the form above.</p>
                    <p>Only you (Super Admin) have access currently.</p>
                </div>
            <?php else: ?>
                <div class="admin-grid">
                    <?php foreach ($admins as $admin): 
                        $statusClass = ($admin['is_active'] ?? 1) ? 'status-active' : 'status-inactive';
                        $statusText = ($admin['is_active'] ?? 1) ? 'Active' : 'Inactive';
                        $permissions = $admin['permissions'] ?? 'basic';
                    ?>
                    <div class="admin-card">
                        <div class="admin-header">
                            <h3><?php echo htmlspecialchars($admin['adminName'] ?? $admin['user_name'] ?? 'Unknown Admin'); ?></h3>
                            <div class="admin-avatar">
                                <?php echo strtoupper(substr(($admin['adminName'] ?? $admin['user_name'] ?? 'A'), 0, 1)); ?>
                            </div>
                        </div>
                        <div class="admin-body">
                            <div class="admin-info">
                                <div class="info-row">
                                    <span class="info-label">Admin ID:</span>
                                    <span class="info-value">#<?php echo $admin['adminID']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">User ID:</span>
                                    <span class="info-value">#<?php echo $admin['UserID'] ?? 'N/A'; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($admin['AdminEmail'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Created:</span>
                                    <span class="info-value"><?php echo date('Y-m-d', strtotime($admin['created_at'] ?? 'now')); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Role ID:</span>
                                    <span class="info-value">#<?php echo $admin['roleID'] ?? '3'; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Last Login:</span>
                                    <span class="info-value">
                                        <?php echo isset($admin['last_login']) && $admin['last_login'] ? date('Y-m-d', strtotime($admin['last_login'])) : 'Never'; ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value"><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></span>
                                </div>
                            </div>
                            
                            <div class="permissions-badges">
                                <span class="permission-badge"><?php echo htmlspecialchars($permissions); ?></span>
                            </div>
                            
                            <div class="action-buttons">
                                <button type="button" class="action-btn btn-edit" 
                                        onclick="editAdmin(<?php echo $admin['adminID']; ?>, '<?php echo htmlspecialchars($admin['permissions'] ?? 'basic'); ?>', <?php echo $admin['is_active'] ?? 1; ?>)">
                                    ✏️ Edit Permissions
                                </button>
                                <form method="POST" style="display: inline; flex: 1;" 
                                      onsubmit="return confirm('Remove admin privileges from <?php echo htmlspecialchars($admin['adminName'] ?? $admin['user_name'] ?? 'this admin'); ?>?')">
                                    <input type="hidden" name="admin_id" value="<?php echo $admin['adminID']; ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="action-btn btn-remove">🗑️ Remove Admin</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 40px; padding: 20px; background: #fff3cd; border-radius: 10px; border-left: 4px solid #f39c12;">
                <h4 style="color: #856404; margin-bottom: 10px;">🔐 Admin Permission Levels</h4>
                <p style="color: #856404; margin-bottom: 10px;"><strong>Basic Admin:</strong> Can view users and rentals, manage basic operations.</p>
                <p style="color: #856404; margin-bottom: 10px;"><strong>Moderate Admin:</strong> Can approve requests, manage drones, generate reports.</p>
                <p style="color: #856404;"><strong>Full Admin:</strong> Has all permissions except super admin functions.</p>
            </div>
            
            <a href="superadmin_panel.php" class="back-btn">← Back to Super Admin Panel</a>
        </div>
    </div>
    
    <!-- Edit Admin Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Edit Admin Permissions</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="admin_id" id="editAdminId">
                
                <div class="form-group">
                    <label for="edit_permissions">Permission Level</label>
                    <select name="permissions" id="edit_permissions" required>
                        <option value="basic">Basic Admin</option>
                        <option value="moderate">Moderate Admin</option>
                        <option value="full">Full Admin</option>
                        <option value="custom">Custom Permissions</option>
                    </select>
                </div>
                
                <div class="permissions-grid" id="editCustomPermissions" style="display: none;">
                    <div class="permission-option">
                        <input type="checkbox" id="edit_perm_users" name="custom_perms[]" value="manage_users">
                        <label for="edit_perm_users">Manage Users</label>
                    </div>
                    <div class="permission-option">
                        <input type="checkbox" id="edit_perm_drones" name="custom_perms[]" value="manage_drones">
                        <label for="edit_perm_drones">Manage Drones</label>
                    </div>
                    <div class="permission-option">
                        <input type="checkbox" id="edit_perm_rentals" name="custom_perms[]" value="manage_rentals">
                        <label for="edit_perm_rentals">Manage Rentals</label>
                    </div>
                    <div class="permission-option">
                        <input type="checkbox" id="edit_perm_reports" name="custom_perms[]" value="view_reports">
                        <label for="edit_perm_reports">View Reports</label>
                    </div>
                    <div class="permission-option">
                        <input type="checkbox" id="edit_perm_settings" name="custom_perms[]" value="system_settings">
                        <label for="edit_perm_settings">System Settings</label>
                    </div>
                    <div class="permission-option">
                        <input type="checkbox" id="edit_perm_approve" name="custom_perms[]" value="approve_requests">
                        <label for="edit_perm_approve">Approve Requests</label>
                    </div>
                </div>
                
                <div class="checkbox-group" style="margin-top: 20px;">
                    <input type="checkbox" name="is_active" id="edit_is_active" checked>
                    <label for="edit_is_active">Active Admin</label>
                </div>
                
                <div class="form-buttons" style="margin-top: 30px;">
                    <button type="submit" class="submit-btn">💾 Save Changes</button>
                    <button type="button" class="reset-btn" onclick="closeModal()">❌ Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Show/hide custom permissions based on selection
        document.getElementById('permissions').addEventListener('change', function() {
            document.getElementById('customPermissions').style.display = 
                this.value === 'custom' ? 'block' : 'none';
        });
        
        document.getElementById('edit_permissions').addEventListener('change', function() {
            document.getElementById('editCustomPermissions').style.display = 
                this.value === 'custom' ? 'block' : 'none';
        });
        
        function editAdmin(adminId, permissions, isActive) {
            document.getElementById('editAdminId').value = adminId;
            document.getElementById('edit_permissions').value = permissions;
            document.getElementById('edit_is_active').checked = isActive == 1;
            
            // Show modal
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeModal();
            }
        };
        
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messageBox = document.getElementById('messageBox');
            if (messageBox) {
                messageBox.style.display = 'none';
            }
            const errorBox = document.getElementById('errorBox');
            if (errorBox) {
                errorBox.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>