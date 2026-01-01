<?php
session_start();
require_once 'db.php';

// Check if user is super admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['is_superadmin']) || $_SESSION['is_superadmin'] != 1) {
    header("Location: index_login.php");
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $userID = $_POST['user_id'] ?? '';
        $action = $_POST['action'];
        
        switch ($action) {
            case 'ban':
                $stmt = $pdo->prepare("UPDATE users SET status = 'banned' WHERE UserID = ?");
                $stmt->execute([$userID]);
                $message = "User banned successfully";
                break;
                
            case 'suspend':
                $stmt = $pdo->prepare("UPDATE users SET status = 'suspended', suspension_end = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE UserID = ?");
                $stmt->execute([$userID]);
                $message = "User suspended for 7 days";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM users WHERE UserID = ?");
                $stmt->execute([$userID]);
                $message = "User deleted successfully";
                break;
                
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE UserID = ?");
                $stmt->execute([$userID]);
                $message = "User activated successfully";
                break;
        }
    }
}

// Fetch all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | Super Admin</title>
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
            border-bottom: 4px solid #3498db;
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
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .user-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }
        
        .user-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .user-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-banned { background: #f8d7da; color: #721c24; }
        .status-suspended { background: #fff3cd; color: #856404; }
        .status-pending { background: #cce5ff; color: #004085; }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-ban { background: #dc3545; color: white; }
        .btn-suspend { background: #ffc107; color: #212529; }
        .btn-delete { background: #6c757d; color: white; }
        .btn-activate { background: #28a745; color: white; }
        .btn-view { background: #007bff; color: white; }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .search-bar {
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
        }
        
        .search-bar input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .search-btn {
            padding: 12px 25px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
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
        .stat-card.banned { border-color: #e74c3c; }
        .stat-card.suspended { border-color: #f39c12; }
        
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
        
        .message-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: none;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Manage Users <span class="super-admin-badge">SUPER ADMIN</span></h1>
            <div style="color: white;">
                <p><strong><?php echo htmlspecialchars($_SESSION['Name'] ?? 'Admin'); ?></strong></p>
                <p><small><?php echo htmlspecialchars($_SESSION['Email'] ?? ''); ?></small></p>
            </div>
        </div>
        
        <div class="content">
            <div class="page-title">
                👥 Manage Users
            </div>
            
            <div class="page-description">
                View, delete, ban, or suspend users. Access complete user information and activity logs.
            </div>
            
            <?php if (isset($message)): ?>
                <div class="message-box message-success" id="messageBox">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-bar">
                <div class="stat-card total">
                    <div class="number"><?php echo count($users); ?></div>
                    <div class="label">Total Users</div>
                </div>
                <div class="stat-card active">
                    <div class="number"><?php echo count(array_filter($users, fn($u) => ($u['status'] ?? 'active') == 'active')); ?></div>
                    <div class="label">Active Users</div>
                </div>
                <div class="stat-card banned">
                    <div class="number"><?php echo count(array_filter($users, fn($u) => ($u['status'] ?? 'active') == 'banned')); ?></div>
                    <div class="label">Banned Users</div>
                </div>
                <div class="stat-card suspended">
                    <div class="number"><?php echo count(array_filter($users, fn($u) => ($u['status'] ?? 'active') == 'suspended')); ?></div>
                    <div class="label">Suspended Users</div>
                </div>
            </div>
            
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search users by name, email, or ID..." onkeyup="searchUsers()">
                <button class="search-btn" onclick="searchUsers()">🔍 Search</button>
            </div>
            
            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        $status = $user['status'] ?? 'active';
                        $statusClass = "status-$status";
                        $lastLogin = $user['last_login'] ?? 'Never';
                    ?>
                    <tr class="user-row">
                        <td>#<?php echo htmlspecialchars($user['UserID']); ?></td>
                        <td><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($user['Email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role'] ?? 'user'); ?></td>
                        <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span></td>
                        <td><?php echo date('Y-m-d', strtotime($user['created_at'] ?? 'now')); ?></td>
                        <td><?php echo $lastLogin === 'Never' ? 'Never' : date('Y-m-d H:i', strtotime($lastLogin)); ?></td>
                        <td>
                            <div class="action-buttons">
                                <form method="POST" style="display: inline;" onsubmit="return confirmAction('view details of this user?')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                    <button type="button" class="action-btn btn-view" onclick="viewUserDetails(<?php echo $user['UserID']; ?>)">View</button>
                                </form>
                                
                                <?php if ($status == 'active'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('ban this user?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                        <input type="hidden" name="action" value="ban">
                                        <button type="submit" class="action-btn btn-ban">Ban</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('suspend this user for 7 days?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="action-btn btn-suspend">Suspend</button>
                                    </form>
                                <?php elseif ($status == 'banned' || $status == 'suspended'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('activate this user?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="action-btn btn-activate">Activate</button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirmAction('delete this user permanently? This action cannot be undone.')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="action-btn btn-delete">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($users)): ?>
                <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                    <h3>No users found</h3>
                    <p>The system has no registered users yet.</p>
                </div>
            <?php endif; ?>
            
            <a href="superadmin_panel.php" class="back-btn">← Back to Super Admin Panel</a>
        </div>
    </div>
    
    <script>
        function confirmAction(action) {
            return confirm(`Are you sure you want to ${action}?`);
        }
        
        function searchUsers() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const rows = document.querySelectorAll('.user-row');
            
            rows.forEach(row => {
                const text = row.textContent || row.innerText;
                row.style.display = text.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
            });
        }
        
        function viewUserDetails(userID) {
            // In a real system, this would open a modal or navigate to user details page
            alert(`Viewing details for User ID: ${userID}\n\nThis would show complete user information and activity logs.`);
        }
        
        // Auto-hide message after 5 seconds
        setTimeout(() => {
            const messageBox = document.getElementById('messageBox');
            if (messageBox) {
                messageBox.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>