<?php
// delete_user.php - Safe User Deletion
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

// IMPORTANT: Prevent admin from deleting themselves
if ($user_id == $_SESSION['UserID']) {
    die("❌ You cannot delete your own account while logged in.");
}

// Handle deletion
$deleted = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        try {
            // Log the action before deletion
            require 'logger.php';
            logEvent("User deleted: {$user['Email']} (ID: {$user['UserID']}) by admin {$_SESSION['Email']}");
            
            // Delete the user
            $deleteStmt = $pdo->prepare("DELETE FROM users WHERE UserID = ?");
            $deleteStmt->execute([$user_id]);
            
            $deleted = true;
            
            // Redirect after 3 seconds
            header("refresh:3;url=admin_panel.php");
            
        } catch (Exception $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete User | Airusea</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .warning-card {
            background: #fff5f5;
            border: 3px solid #fc8181;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
        }
        
        .warning-icon {
            font-size: 48px;
            color: #e53e3e;
            margin-bottom: 20px;
        }
        
        .user-info {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
            border: 1px solid #e2e8f0;
        }
        
        .confirm-form {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        
        .confirm-text {
            padding: 15px;
            background: #fed7d7;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
            color: #742a2a;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .success-message {
            background: #c6f6d5;
            border: 2px solid #9ae6b4;
            color: #22543d;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 30px 0;
        }
    </style>
</head>
<body class="admin-page">
    <div class="delete-container">
        <div class="admin-header" style="margin-bottom: 30px;">
            <h1>Delete User Account</h1>
            <div class="admin-links">
                <a href="manage_user.php?user_id=<?= $user_id ?>">← Back to Manage User</a>
                <a href="admin_panel.php">Admin Panel</a>
            </div>
        </div>
        
        <?php if ($deleted): ?>
            <div class="success-message">
                <div style="font-size: 36px;">✅</div>
                <h2>User Deleted Successfully</h2>
                <p>The user account has been permanently removed from the system.</p>
                <p>Redirecting to admin panel in 3 seconds...</p>
            </div>
        <?php elseif ($error): ?>
            <div class="warning-card">
                <div class="warning-icon">❌</div>
                <h2>Error</h2>
                <p><?= htmlspecialchars($error) ?></p>
                <div class="button-group">
                    <a href="manage_user.php?user_id=<?= $user_id ?>" class="btn">← Go Back</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Warning Card -->
            <div class="warning-card">
                <div class="warning-icon">⚠️</div>
                <h2 style="color: #e53e3e;">Permanent Deletion Warning</h2>
                <p>You are about to <strong>permanently delete</strong> a user account.</p>
            </div>
            
            <!-- User Information -->
            <div class="user-info">
                <h3>User to be deleted:</h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['Email']) ?></p>
                <p><strong>User ID:</strong> #<?= $user['UserID'] ?></p>
                <p><strong>Admin Status:</strong> <?= $user['is_admin'] ? '✅ Administrator' : '👤 Regular User' ?></p>
                <?php if (isset($user['RegistrationDate'])): ?>
                    <p><strong>Member since:</strong> <?= date('F j, Y', strtotime($user['RegistrationDate'])) ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Confirmation -->
            <div class="confirm-form">
                <div class="confirm-text">
                    ⚠️ This action cannot be undone. All user data will be permanently lost.
                </div>
                
                <form method="POST">
                    <div style="text-align: center; margin: 25px 0;">
                        <label style="display: block; margin-bottom: 15px; font-weight: bold; color: #2c3e50;">
                            <input type="checkbox" name="confirm_delete" value="yes" required>
                            I understand that this action is permanent and cannot be undone
                        </label>
                        
                        <label style="display: block; margin-bottom: 15px; font-weight: bold; color: #2c3e50;">
                            <input type="checkbox" name="confirm_email" value="yes" required>
                            I confirm I want to delete user: <strong><?= htmlspecialchars($user['Email']) ?></strong>
                        </label>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-remove" 
                                onclick="return confirm('FINAL WARNING: Are you absolutely sure you want to delete \'<?= htmlspecialchars($user['Email']) ?>\'?')">
                            🗑️ PERMANENTLY DELETE USER
                        </button>
                        <a href="manage_user.php?user_id=<?= $user_id ?>" class="btn" style="background: #a0aec0;">
                            ← Cancel & Go Back
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Safety Notes -->
            <div style="margin-top: 40px; padding: 20px; background: #edf2f7; border-radius: 8px;">
                <h4>📝 Important Notes:</h4>
                <ul>
                    <li>Consider disabling the account instead of deleting</li>
                    <li>Check if user has active rentals before deletion</li>
                    <li>Make sure you have a backup if needed</li>
                    <li>This action will be logged for security audit</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>