<?php
session_start();
require_once 'db.php';

// Check if user is super admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['is_superadmin']) || $_SESSION['is_superadmin'] != 1) {
    header("Location: index_login.php");
    exit();
}

// Check if drones table exists, create if not
try {
    $pdo->query("SELECT 1 FROM drones LIMIT 1");
} catch (Exception $e) {
    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS drones (
        droneID INT AUTO_INCREMENT PRIMARY KEY,
        ownerID INT,
        drone_name VARCHAR(100),
        drone_model VARCHAR(100),
        drone_type VARCHAR(50),
        rental_price DECIMAL(10,2),
        status VARCHAR(20) DEFAULT 'pending',
        uploaded_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        approved_date DATETIME,
        approved_by INT,
        image_url VARCHAR(255),
        description TEXT
    )");
    
    // Add sample data for demo
    $sampleDrones = [
        ['DJI Mavic 3', 'Quadcopter', 49.99, 'High-end drone with 4K camera'],
        ['Autel EVO II', 'Hexacopter', 39.99, 'Professional drone for photography'],
        ['Parrot Anafi', 'Compact Drone', 29.99, 'Portable drone with zoom camera'],
        ['DJI Phantom 4', 'Quadcopter', 44.99, 'Pro drone with obstacle avoidance'],
        ['Ryze Tello', 'Mini Drone', 19.99, 'Beginner friendly programming drone']
    ];
    
    foreach ($sampleDrones as $drone) {
        $stmt = $pdo->prepare("INSERT INTO drones (drone_name, drone_type, rental_price, description, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$drone[0], $drone[1], $drone[2], $drone[3]]);
    }
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['drone_id'])) {
        $droneID = $_POST['drone_id'];
        $action = $_POST['action'];
        $notes = $_POST['notes'] ?? '';
        
        if ($action == 'approve') {
            $stmt = $pdo->prepare("UPDATE drones SET status = 'approved', approved_by = ?, approved_date = NOW() WHERE droneID = ?");
            $stmt->execute([$_SESSION['UserID'], $droneID]);
            $message = "Drone approved successfully";
            
        } elseif ($action == 'reject') {
            $stmt = $pdo->prepare("UPDATE drones SET status = 'rejected', approved_by = ?, approved_date = NOW() WHERE droneID = ?");
            $stmt->execute([$_SESSION['UserID'], $droneID]);
            $message = "Drone rejected";
            
        } elseif ($action == 'feature') {
            $stmt = $pdo->prepare("UPDATE drones SET status = 'featured', approved_by = ?, approved_date = NOW() WHERE droneID = ?");
            $stmt->execute([$_SESSION['UserID'], $droneID]);
            $message = "Drone marked as featured";
        }
    }
}

// Fetch pending drones - FIXED: Use droneID for ordering instead of uploaded_date
$stmt = $pdo->query("SELECT * FROM drones WHERE status = 'pending' ORDER BY droneID DESC");
$pendingDrones = $stmt->fetchAll();

// Fetch all drones
$stmt = $pdo->query("SELECT * FROM drones ORDER BY droneID DESC");
$allDrones = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approve Drone Uploads | Super Admin</title>
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
            border-bottom: 4px solid #1abc9c;
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
        
        .drones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .drone-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .drone-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .drone-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }
        
        .drone-info {
            padding: 20px;
        }
        
        .drone-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .drone-name {
            font-size: 20px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .drone-price {
            font-size: 24px;
            color: #2ecc71;
            font-weight: bold;
        }
        
        .drone-details {
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-label {
            color: #7f8c8d;
        }
        
        .detail-value {
            color: #2c3e50;
            font-weight: 500;
        }
        
        .drone-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-featured { background: #cce5ff; color: #004085; }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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
        
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-feature { background: #007bff; color: white; }
        .btn-view { background: #6c757d; color: white; }
        
        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .notes-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
            font-family: inherit;
            resize: vertical;
            font-size: 14px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }
        
        .tab-btn {
            padding: 10px 25px;
            background: #f8f9fa;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: #1abc9c;
            color: white;
        }
        
        .tab-btn:hover {
            background: #e9ecef;
        }
        
        .tab-btn.active:hover {
            background: #16a085;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
        .stat-card.pending { border-color: #f39c12; }
        .stat-card.approved { border-color: #2ecc71; }
        .stat-card.featured { border-color: #9b59b6; }
        
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
        
        .drone-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .drone-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }
        
        .drone-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .drone-table tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚁 Approve Drone Uploads <span class="super-admin-badge">SUPER ADMIN</span></h1>
            <div style="color: white;">
                <p><strong><?php echo htmlspecialchars($_SESSION['Name'] ?? 'Admin'); ?></strong></p>
                <p><small><?php echo htmlspecialchars($_SESSION['Email'] ?? ''); ?></small></p>
            </div>
        </div>
        
        <div class="content">
            <div class="page-title">
                🚁 Approve Drone Uploads
            </div>
            
            <div class="page-description">
                Approve new drones uploaded by owners before they become available for rent.
            </div>
            
            <?php if (isset($message)): ?>
                <div class="message-box message-success" id="messageBox">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-bar">
                <div class="stat-card total">
                    <div class="number"><?php echo count($allDrones); ?></div>
                    <div class="label">Total Drones</div>
                </div>
                <div class="stat-card pending">
                    <div class="number"><?php echo count(array_filter($allDrones, fn($d) => ($d['status'] ?? 'pending') == 'pending')); ?></div>
                    <div class="label">Pending Review</div>
                </div>
                <div class="stat-card approved">
                    <div class="number"><?php echo count(array_filter($allDrones, fn($d) => ($d['status'] ?? '') == 'approved')); ?></div>
                    <div class="label">Approved</div>
                </div>
                <div class="stat-card featured">
                    <div class="number"><?php echo count(array_filter($allDrones, fn($d) => ($d['status'] ?? '') == 'featured')); ?></div>
                    <div class="label">Featured</div>
                </div>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('pending')">⏳ Pending Approval (<?php echo count($pendingDrones); ?>)</button>
                <button class="tab-btn" onclick="switchTab('approved')">✅ Approved Drones</button>
                <button class="tab-btn" onclick="switchTab('all')">📋 All Drones</button>
            </div>
            
            <!-- Pending Drones Tab -->
            <div id="pending-tab" class="tab-content active">
                <?php if (empty($pendingDrones)): ?>
                    <div class="empty-state">
                        <h3>🎉 No Drones Pending Approval</h3>
                        <p>All drones have been reviewed and approved.</p>
                        <p>New drone uploads will appear here for review.</p>
                    </div>
                <?php else: ?>
                    <div class="drones-grid">
                        <?php foreach ($pendingDrones as $drone): ?>
                        <div class="drone-card">
                            <div class="drone-image">
                                🚁
                            </div>
                            <div class="drone-info">
                                <div class="drone-header">
                                    <div class="drone-name"><?php echo htmlspecialchars($drone['drone_name'] ?? 'Unknown Drone'); ?></div>
                                    <div class="drone-price">$<?php echo number_format($drone['rental_price'] ?? 0, 2); ?>/day</div>
                                </div>
                                
                                <div class="drone-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Model:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($drone['drone_model'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Type:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($drone['drone_type'] ?? 'N/A'); ?></span>
                                    </div>
                                    <?php if (isset($drone['uploaded_date'])): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Uploaded:</span>
                                        <span class="detail-value"><?php echo date('Y-m-d', strtotime($drone['uploaded_date'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (isset($drone['ownerID']) && $drone['ownerID']): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Owner ID:</span>
                                        <span class="detail-value">#<?php echo $drone['ownerID']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($drone['description'])): ?>
                                <div class="drone-description">
                                    <?php echo htmlspecialchars($drone['description']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <input type="hidden" name="drone_id" value="<?php echo $drone['droneID']; ?>">
                                    <textarea name="notes" class="notes-input" placeholder="Add review notes (optional)..." rows="2"></textarea>
                                    <div class="action-buttons">
                                        <button type="submit" name="action" value="approve" class="action-btn btn-approve" onclick="return confirm('Approve this drone for rental?')">✅ Approve</button>
                                        <button type="submit" name="action" value="feature" class="action-btn btn-feature" onclick="return confirm('Mark this drone as featured?')">⭐ Feature</button>
                                        <button type="submit" name="action" value="reject" class="action-btn btn-reject" onclick="return confirm('Reject this drone upload?')">❌ Reject</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Approved Drones Tab -->
            <div id="approved-tab" class="tab-content">
                <?php 
                $approvedDrones = array_filter($allDrones, fn($d) => ($d['status'] ?? '') == 'approved' || ($d['status'] ?? '') == 'featured');
                ?>
                <?php if (empty($approvedDrones)): ?>
                    <div class="empty-state">
                        <h3>No Approved Drones</h3>
                        <p>No drones have been approved yet.</p>
                    </div>
                <?php else: ?>
                    <table class="drone-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Drone Name</th>
                                <th>Model</th>
                                <th>Type</th>
                                <th>Price/Day</th>
                                <th>Status</th>
                                <th>Approved Date</th>
                                <th>Owner</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approvedDrones as $drone): 
                                $statusClass = "status-" . ($drone['status'] ?? '');
                            ?>
                            <tr>
                                <td>#<?php echo $drone['droneID']; ?></td>
                                <td><strong><?php echo htmlspecialchars($drone['drone_name'] ?? 'Unknown'); ?></strong></td>
                                <td><?php echo htmlspecialchars($drone['drone_model'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($drone['drone_type'] ?? 'N/A'); ?></td>
                                <td><strong>$<?php echo number_format($drone['rental_price'] ?? 0, 2); ?></strong></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($drone['status'] ?? ''); ?></span></td>
                                <td>
                                    <?php if (isset($drone['approved_date']) && $drone['approved_date']): ?>
                                        <?php echo date('Y-m-d', strtotime($drone['approved_date'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($drone['ownerID']) && $drone['ownerID']): ?>
                                        Owner #<?php echo $drone['ownerID']; ?>
                                    <?php else: ?>
                                        System
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove approval for this drone?')">
                                        <input type="hidden" name="drone_id" value="<?php echo $drone['droneID']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="action-btn btn-reject" style="padding: 5px 10px; font-size: 12px;">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- All Drones Tab -->
            <div id="all-tab" class="tab-content">
                <?php if (empty($allDrones)): ?>
                    <div class="empty-state">
                        <h3>No Drones in System</h3>
                        <p>No drones have been uploaded to the system yet.</p>
                    </div>
                <?php else: ?>
                    <table class="drone-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Drone Name</th>
                                <th>Status</th>
                                <th>Price/Day</th>
                                <th>Owner</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allDrones as $drone): 
                                $statusClass = "status-" . ($drone['status'] ?? 'pending');
                            ?>
                            <tr>
                                <td>#<?php echo $drone['droneID']; ?></td>
                                <td><strong><?php echo htmlspecialchars($drone['drone_name'] ?? 'Unknown'); ?></strong></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($drone['status'] ?? 'pending'); ?></span></td>
                                <td>$<?php echo number_format($drone['rental_price'] ?? 0, 2); ?></td>
                                <td>
                                    <?php if (isset($drone['ownerID']) && $drone['ownerID']): ?>
                                        Owner #<?php echo $drone['ownerID']; ?>
                                    <?php else: ?>
                                        System
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons" style="flex-wrap: nowrap;">
                                        <button onclick="viewDroneDetails(<?php echo $drone['droneID']; ?>)" class="action-btn btn-view" style="padding: 5px 10px; font-size: 12px;">View</button>
                                        <?php if (($drone['status'] ?? '') == 'pending'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this drone?')">
                                                <input type="hidden" name="drone_id" value="<?php echo $drone['droneID']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="action-btn btn-approve" style="padding: 5px 10px; font-size: 12px;">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <a href="superadmin_panel.php" class="back-btn">← Back to Super Admin Panel</a>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.includes(tabName === 'pending' ? 'Pending' : tabName === 'approved' ? 'Approved' : 'All')) {
                    btn.classList.add('active');
                }
            });
            
            // Update tab content
            document.getElementById('pending-tab').classList.remove('active');
            document.getElementById('approved-tab').classList.remove('active');
            document.getElementById('all-tab').classList.remove('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }
        
        function viewDroneDetails(droneID) {
            alert(`Viewing details for Drone ID: ${droneID}\n\nThis would show complete drone information including specifications, images, and rental history.`);
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