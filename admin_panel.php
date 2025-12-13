<?php
require 'auth.php';
requireLogin();
$isAdmin = isAdmin();

if (!$isAdmin) {
    header('Location: dashboard.php');
    exit();
}

require 'db.php';
require 'logger.php';

// ADD THIS: Fetch user's name from database
$user_stmt = $pdo->prepare("SELECT Name FROM users WHERE UserID = ?");
$user_stmt->execute([$_SESSION['UserID']]);
$user = $user_stmt->fetch();
$display_name = !empty($user['Name']) ? $user['Name'] : $_SESSION['Email'];

// Handle success/error messages from remove_drone.php and other actions
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'phased_out':
            $drone_id = isset($_GET['id']) ? intval($_GET['id']) : 'unknown';
            $success_message = "✅ Drone #$drone_id has been successfully phased out.";
            break;
        case 'removed':
            $success_message = "✅ Drone has been successfully removed.";
            break;
        case '1':
            $success_message = "✅ Drone updated successfully.";
            break;
        case 'price_updated':
            $new_id = isset($_GET['new_id']) ? intval($_GET['new_id']) : 'unknown';
            $success_message = "✅ Price changed successfully. New drone #$new_id created and old drone phased out.";
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'drone_has_active_rentals':
            $error_message = "❌ Cannot phase out drone: It has active rentals.";
            break;
        case 'drone_already_phased_out':
            $error_message = "❌ This drone is already phased out.";
            break;
        case 'drone_not_found':
            $error_message = "❌ Drone not found.";
            break;
        case 'invalid_id':
            $error_message = "❌ Invalid drone ID.";
            break;
        case 'db_error':
            $error_message = "❌ Database error: " . (isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Unknown error');
            break;
        case 'general_error':
            $error_message = "❌ Error: " . (isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Unknown error');
            break;
    }
}

// Check if database tables exist
try {
    $categories = $pdo->query("SELECT * FROM categories");
    $wingTypes = $pdo->query("SELECT * FROM wingtype");
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel | Airusea</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_styles.css"> <!-- NEW STYLESHEET -->
    <link rel="stylesheet" href="media.css">
    <style>
        /* Hide main site header on admin pages */
        header {
            display: none !important;
        }
        
        /* Force admin page styling */
        body {
            background: #f5f7fa !important;
            padding-top: 0 !important;
            margin: 0 !important;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .status-available {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-phased-out {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-unknown {
            background-color: #f0f0f0;
            color: #666;
            border: 1px solid #ddd;
        }

        /* Status filter buttons */
        .status-filter {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .status-filter h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1em;
            color: #2c3e50;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .filter-all {
            background: #3498db;
            color: white;
        }

        .filter-available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .filter-phased-out {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Action Buttons */
        .btn-edit {
            background: #3498db;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            text-align: center;
            margin-top: 5px;
            width: 100%;
            box-sizing: border-box;
        }

        .btn-edit:hover {
            background: #2980b9;
        }

        .btn-remove {
            background: #e74c3c;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }

        .btn-remove:hover {
            background: #c0392b;
        }

        /* Admin table improvements */
        .admin-table td {
            vertical-align: middle;
        }

        .admin-table tr:hover {
            background-color: #f9f9f9;
        }

        .action-cell {
            min-width: 120px;
        }

        .phased-id {
            font-family: monospace;
            color: #e74c3c;
            font-weight: bold;
        }

        .phased-id-none {
            color: #95a5a6;
            font-style: italic;
        }
        
        /* Success and Error Messages */
        .message-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            animation: slideIn 0.5s ease, fadeOut 0.5s ease 4.5s forwards;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            margin-bottom: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            margin-bottom: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.2em;
            cursor: pointer;
            margin-left: auto;
            padding: 0 5px;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
    </style>
</head>
<body class="admin-page">
    <!-- Success and Error Messages -->
    <?php if ($success_message || $error_message): ?>
    <div class="message-container">
        <?php if ($success_message): ?>
        <div class="success-message" id="successMessage">
            <span>✅</span>
            <span><?php echo htmlspecialchars($success_message); ?></span>
            <button class="message-close" onclick="document.getElementById('successMessage').style.display='none'">×</button>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="error-message" id="errorMessage">
            <span>❌</span>
            <span><?php echo htmlspecialchars($error_message); ?></span>
            <button class="message-close" onclick="document.getElementById('errorMessage').style.display='none'">×</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="admin-container">
        <!-- Admin Header -->
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <div class="admin-user-info">
                <div>
                    Welcome, <strong><?= htmlspecialchars($display_name) ?></strong> (Administrator)
                </div>
                <div class="admin-links">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>

        <!-- Manage Users Section -->
        <div class="admin-section">
            <h2 class="section-title">👥 Manage Users</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Email</th>
                        <th>Admin Status</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $pdo->query("SELECT * FROM users ORDER BY UserID");
                    while ($user = $users->fetch()) {
                        echo "<tr>";
                        echo "<td><strong>#{$user['UserID']}</strong></td>";
                        echo "<td>{$user['Email']}</td>";
                        echo "<td>";
                        echo "<span class='status-badge " . ($user['is_admin'] ? 'status-yes' : 'status-no') . "'>";
                        echo $user['is_admin'] ? 'Yes' : 'No';
                        echo "</span>";
                        echo "</td>";
                        echo "<td>";
                        if (isset($user['RegistrationDate']) && !empty($user['RegistrationDate'])) {
                            echo date('M d, Y', strtotime($user['RegistrationDate']));
                        } else {
                            echo '<span style="color: #95a5a6; font-style: italic;">Not set</span>';
                        }
                        echo "</td>";
                        echo "<td>
                                <a href='manage_user.php?user_id={$user['UserID']}' 
                                class='btn btn-manage'>
                                🔧 Manage
                                </a>
                            </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Add New Drone Section -->
        <div class="admin-section">
            <h2 class="section-title">🚁 Add New Drone</h2>
            <form action="add_drone.php" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Brand:</label>
                        <input type="text" name="brand" required placeholder="e.g., DJI">
                    </div>
                    
                    <div class="form-group">
                        <label>Model:</label>
                        <input type="text" name="model" required placeholder="e.g., Mavic 3">
                    </div>
                    
                    <div class="form-group">
                        <label>Category:</label>
                        <select name="category_id" required>
                            <?php
                            if ($categories->rowCount() > 0) {
                                $categories->execute(); // Reset pointer
                                while ($category = $categories->fetch()) {
                                    echo "<option value='{$category['CategoryID']}'>{$category['CategoryName']}</option>";
                                }
                            } else {
                                echo "<option value=''>No categories found</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Wing Type:</label>
                        <select name="wing_type_id" required>
                            <?php
                            if ($wingTypes->rowCount() > 0) {
                                $wingTypes->execute(); // Reset pointer
                                while ($wing = $wingTypes->fetch()) {
                                    echo "<option value='{$wing['WingTypeID']}'>{$wing['WingTypeName']}</option>";
                                }
                            } else {
                                echo "<option value=''>No wing types found</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Price per Day ($):</label>
                        <input type="number" step="0.01" name="price_per_day" required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity Available:</label>
                        <input type="number" name="quantity_available" min="1" required placeholder="1">
                    </div>
                    
                    <div class="form-group form-full">
                        <label>Drone Image:</label>
                        <input type="file" name="image" accept="image/*" required>
                        <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                            Recommended: JPG, PNG, or GIF format
                        </small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-submit">➕ Add Drone</button>
            </form>
        </div>

        <!-- Current Drones Section -->
        <div class="admin-section">
            <h2 class="section-title">📋 Current Drones Inventory</h2>
            
            <!-- Status Filter -->
            <div class="status-filter">
                <h3>Filter by Status:</h3>
                <div class="filter-buttons">
                    <?php
                    $current_status = isset($_GET['status']) ? $_GET['status'] : '';
                    ?>
                    <a href="admin_panel.php" 
                       class="filter-btn filter-all <?php echo $current_status === '' ? 'active' : ''; ?>">
                       All Drones
                    </a>
                    <a href="admin_panel.php?status=available" 
                       class="filter-btn filter-available <?php echo $current_status === 'available' ? 'active' : ''; ?>">
                       Available Only
                    </a>
                    <a href="admin_panel.php?status=phased_out" 
                       class="filter-btn filter-phased-out <?php echo $current_status === 'phased_out' ? 'active' : ''; ?>">
                       Phased Out Only
                    </a>
                </div>
            </div>
            
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Drone ID</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Category</th>
                        <th>Price/Day</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Phased From ID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        // Get status filter from URL
                        $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
                        
                        // Build the query
                        $sql = "
                            SELECT d.*, c.CategoryName, w.WingTypeName 
                            FROM drones d
                            LEFT JOIN categories c ON d.CategoryID = c.CategoryID
                            LEFT JOIN wingtype w ON d.WingTypeID = w.WingTypeID
                        ";
                        
                        // Add status filter if specified
                        if ($status_filter && in_array($status_filter, ['available', 'phased_out'])) {
                            $sql .= " WHERE d.status = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$status_filter]);
                        } else {
                            $sql .= " ORDER BY d.DroneID";
                            $stmt = $pdo->query($sql);
                        }
                        
                        if ($stmt->rowCount() > 0) {
                            while ($drone = $stmt->fetch()) {
                                // Determine status badge class
                                $status_class = '';
                                $status_text = ucfirst($drone['status'] ?? 'unknown');
                                
                                switch($drone['status']) {
                                    case 'available':
                                        $status_class = 'status-available';
                                        break;
                                    case 'phased_out':
                                        $status_class = 'status-phased-out';
                                        break;
                                    default:
                                        $status_class = 'status-unknown';
                                        $status_text = 'Unknown';
                                }
                                
                                // Format phased_from_id
                                $phased_from_id = $drone['phased_from_id'];
                                $phased_from_display = $phased_from_id ? '#' . $phased_from_id : '-';
                                $phased_class = $phased_from_id ? 'phased-id' : 'phased-id-none';
                                
                                // Determine action button based on status
                                $action_button = '';
                                if ($drone['status'] == 'available') {
                                    // Only show remove and edit buttons for available drones
                                    $action_button = "
                                        <a href='remove_drone.php?id={$drone['DroneID']}' 
                                           class='btn-remove'
                                           onclick='return confirm(\"Are you sure you want to phase out \\\"{$drone['Brand']} {$drone['Model']}\\\"? This will make it unavailable for future rentals.\")'>
                                           🗑️ Phase Out
                                        </a>
                                        <a href='edit_drone.php?id={$drone['DroneID']}' 
                                           class='btn-edit'>
                                           ✏️ Edit
                                        </a>
                                    ";
                                } else if ($drone['status'] == 'phased_out') {
                                    // Show info for phased out drones
                                    $action_button = "
                                        <span style='color: #95a5a6; font-style: italic; font-size: 0.9em;'>
                                            Phased Out
                                        </span>
                                    ";
                                } else {
                                    $action_button = "
                                        <span style='color: #95a5a6; font-style: italic; font-size: 0.9em;'>
                                            No Actions
                                        </span>
                                    ";
                                }
                                
                                echo "<tr>
                                    <td><strong>#{$drone['DroneID']}</strong></td>
                                    <td><strong>{$drone['Brand']}</strong></td>
                                    <td>{$drone['Model']}</td>
                                    <td>{$drone['CategoryName']}</td>
                                    <td><strong>$" . number_format($drone['PricePerDay'], 2) . "</strong></td>
                                    <td>
                                        <span style='padding: 5px 10px; background: #e8f4fc; border-radius: 4px; font-weight: 500;'>
                                            {$drone['QuantityAvailable']}
                                        </span>
                                    </td>
                                    <td>
                                        <span class='status-badge {$status_class}'>
                                            {$status_text}
                                        </span>
                                    </td>
                                    <td>
                                        <span class='{$phased_class}'>
                                            {$phased_from_display}
                                        </span>
                                    </td>
                                    <td class='action-cell'>
                                        {$action_button}
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9' style='text-align: center; padding: 30px; color: #7f8c8d;'>
                                    No drones in inventory. Add your first drone above.
                                  </td></tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='9' style='text-align: center; padding: 20px; color: #e74c3c;'>
                                Error loading drones: " . htmlspecialchars($e->getMessage()) . "
                              </td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Add active state styling to filter buttons
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            filterButtons.forEach(btn => {
                if (btn.classList.contains('active')) {
                    btn.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
                    btn.style.fontWeight = 'bold';
                }
            });
            
            // Auto-hide messages after 5 seconds
            setTimeout(function() {
                const successMsg = document.getElementById('successMessage');
                const errorMsg = document.getElementById('errorMessage');
                
                if (successMsg) {
                    successMsg.style.animation = 'fadeOut 0.5s ease forwards';
                    setTimeout(() => successMsg.style.display = 'none', 500);
                }
                
                if (errorMsg) {
                    errorMsg.style.animation = 'fadeOut 0.5s ease forwards';
                    setTimeout(() => errorMsg.style.display = 'none', 500);
                }
            }, 5000);
        });
        
        // Manual close for messages
        function closeMessage(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.style.animation = 'fadeOut 0.5s ease forwards';
                setTimeout(() => element.style.display = 'none', 500);
            }
        }
    </script>
</body>
</html>