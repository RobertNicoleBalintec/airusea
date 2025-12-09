<?php
// manage_user.php - User Management Page
session_start();
require 'db.php';
require 'auth.php';

// Check if user is admin
if (!isset($_SESSION['UserID']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php');
    exit();
}

// Get user ID from URL
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    header('Location: admin_panel.php');
    exit();
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE UserID = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

// Handle form submission
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_admin') {
        // Update admin status
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        try {
            $updateStmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE UserID = ?");
            $updateStmt->execute([$is_admin, $user_id]);
            
            $message = "✅ Admin status updated successfully!";
            $success = true;
            
            // Refresh user data
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            // Log the action
            require 'logger.php';
            logEvent("Admin status changed for user {$user['Email']} to " . ($is_admin ? 'Admin' : 'User'));
            
        } catch (Exception $e) {
            $message = "❌ Error updating user: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage User | Airusea</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        .manage-user-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .user-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .user-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 24px;
        }
        
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .info-label {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .admin-toggle {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        
        .toggle-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .toggle-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
        
        .toggle-group label {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }
        
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 8px;
            padding: 25px;
            margin-top: 40px;
        }
        
        .danger-zone h3 {
            color: #e53e3e;
            margin-top: 0;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .message.error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
    </style>
</head>
<body class="admin-page">
    <div class="manage-user-container">
        <!-- Header -->
        <div class="admin-header" style="margin-bottom: 30px;">
            <h1>Manage User</h1>
            <div class="admin-user-info">
                <div>Editing: <strong><?= htmlspecialchars($user['Email']) ?></strong></div>
                <div class="admin-links">
                    <a href="admin_panel.php">← Back to Admin Panel</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $success ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <!-- User Information Card -->
        <div class="user-card">
            <div class="user-header">
                <h1>User Details</h1>
                <div style="font-size: 14px; color: #7f8c8d;">
                    User ID: #<?= $user['UserID'] ?>
                </div>
            </div>
            
            <div class="user-info-grid">
                <div class="info-item">
                    <div class="info-label">Email Address</div>
                    <div class="info-value"><?= htmlspecialchars($user['Email']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Account Status</div>
                    <div class="info-value">
                        <?php if ($user['is_admin']): ?>
                            <span style="color: #38a169; font-weight: bold;">👑 Administrator</span>
                        <?php else: ?>
                            <span style="color: #4a5568;">👤 Regular User</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Registration Date</div>
                    <div class="info-value">
                        <?php if (isset($user['RegistrationDate']) && !empty($user['RegistrationDate'])): ?>
                            <?= date('F j, Y \a\t g:i A', strtotime($user['RegistrationDate'])) ?>
                        <?php else: ?>
                            <span style="color: #a0aec0; font-style: italic;">Not recorded</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Account Age</div>
                    <div class="info-value">
                        <?php if (isset($user['RegistrationDate']) && !empty($user['RegistrationDate'])): 
                            $regDate = new DateTime($user['RegistrationDate']);
                            $now = new DateTime();
                            $interval = $regDate->diff($now);
                            echo $interval->format('%a days');
                        else: ?>
                            <span style="color: #a0aec0;">Unknown</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Admin Toggle Form -->
            <form method="POST">
                <div class="admin-toggle">
                    <h3 style="margin-top: 0; color: #2c3e50;">Administrator Permissions</h3>
                    <p style="color: #718096; margin-bottom: 20px;">
                        Grant or revoke administrator access for this user.
                    </p>
                    
                    <div class="toggle-group">
                        <input type="checkbox" id="is_admin" name="is_admin" value="1" 
                               <?= $user['is_admin'] ? 'checked' : '' ?>>
                        <label for="is_admin">
                            <?php if ($user['is_admin']): ?>
                                ✅ User has <strong>Administrator</strong> privileges
                            <?php else: ?>
                                🔒 User has <strong>Regular User</strong> privileges
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <input type="hidden" name="action" value="update_admin">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-submit">
                        💾 Save Changes
                    </button>
                    <a href="admin_panel.php" class="btn" style="background: #a0aec0;">
                        ← Cancel
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Danger Zone -->
        <div class="danger-zone">
            <h3>⚠️ Danger Zone</h3>
            <p style="color: #718096; margin-bottom: 20px;">
                These actions are permanent and cannot be undone. Proceed with caution.
            </p>
            
            <div style="display: flex; gap: 15px;">
                <a href="delete_user.php?user_id=<?= $user['UserID'] ?>" 
                   class="btn btn-remove"
                   onclick="return confirm('⚠️ WARNING: This will permanently delete user \'<?= htmlspecialchars($user['Email']) ?>\'\\n\\nThis action cannot be undone.\\n\\nAre you absolutely sure?')">
                   🗑️ Delete User Account
                </a>
                
        
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #fff; border-radius: 6px; border: 1px dashed #e53e3e;">
                <strong style="color: #e53e3e;">⚠️ Important Notes:</strong>
                <ul style="color: #718096; margin: 10px 0 0 20px;">
                    <li>Deleted users cannot be recovered</li>
                    <li>All user data will be permanently removed</li>
                    <li>Active rentals will need to be handled separately</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>