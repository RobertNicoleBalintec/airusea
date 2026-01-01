<?php
session_start();
require_once 'db.php';

// Check if user is super admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['is_superadmin']) || $_SESSION['is_superadmin'] != 1) {
    header("Location: index_login.php");
    exit();
}

// Check if owner_requests table exists, create if not
try {
    $pdo->query("SELECT 1 FROM owner_requests LIMIT 1");
} catch (Exception $e) {
    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS owner_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        userID INT,
        name VARCHAR(100),
        email VARCHAR(100),
        request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) DEFAULT 'pending',
        approved_by INT,
        approved_date DATETIME,
        notes TEXT
    )");
    
    // Add sample data for demo
    $sampleRequests = [
        ['John Doe', 'john@example.com'],
        ['Jane Smith', 'jane@example.com'],
        ['Bob Wilson', 'bob@example.com']
    ];
    
    foreach ($sampleRequests as $request) {
        $stmt = $pdo->prepare("INSERT INTO owner_requests (name, email, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$request[0], $request[1]]);
    }
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $requestID = $_POST['request_id'];
        $action = $_POST['action'];
        $notes = $_POST['notes'] ?? '';
        
        if ($action == 'approve') {
            // Update request status
            $stmt = $pdo->prepare("UPDATE owner_requests SET status = 'approved', approved_by = ?, approved_date = NOW(), notes = ? WHERE id = ?");
            $stmt->execute([$_SESSION['UserID'], $notes, $requestID]);
            
            // Get user info from request
            $stmt = $pdo->prepare("SELECT userID, email FROM owner_requests WHERE id = ?");
            $stmt->execute([$requestID]);
            $request = $stmt->fetch();
            
            if ($request && $request['userID']) {
                // Update user role to owner
                $stmt = $pdo->prepare("UPDATE users SET role = 'owner' WHERE UserID = ?");
                $stmt->execute([$request['userID']]);
            }
            
            $message = "Owner request approved successfully";
            
        } elseif ($action == 'reject') {
            $stmt = $pdo->prepare("UPDATE owner_requests SET status = 'rejected', approved_by = ?, approved_date = NOW(), notes = ? WHERE id = ?");
            $stmt->execute([$_SESSION['UserID'], $notes, $requestID]);
            $message = "Owner request rejected";
        }
    }
}

// Fetch pending owner requests - FIXED: Use correct column name
$stmt = $pdo->query("SELECT * FROM owner_requests WHERE status = 'pending' ORDER BY id DESC");
$requests = $stmt->fetchAll();

// Fetch all requests for history
$stmt = $pdo->query("SELECT * FROM owner_requests ORDER BY id DESC");
$allRequests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approve Owner Requests | Super Admin</title>
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
            border-bottom: 4px solid #2ecc71;
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
        
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .request-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
            overflow: hidden;
        }
        
        .request-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .request-header h3 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .request-body {
            padding: 20px;
        }
        
        .request-info {
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
        
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-view { background: #007bff; color: white; }
        
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
        }
        
        .history-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .history-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }
        
        .history-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .history-table tr:hover {
            background-color: #f8f9fa;
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
            background: #007bff;
            color: white;
        }
        
        .tab-btn:hover {
            background: #e9ecef;
        }
        
        .tab-btn.active:hover {
            background: #0069d9;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Approve Owner Requests <span class="super-admin-badge">SUPER ADMIN</span></h1>
            <div style="color: white;">
                <p><strong><?php echo htmlspecialchars($_SESSION['Name'] ?? 'Admin'); ?></strong></p>
                <p><small><?php echo htmlspecialchars($_SESSION['Email'] ?? ''); ?></small></p>
            </div>
        </div>
        
        <div class="content">
            <div class="page-title">
                ✅ Approve Owner Requests
            </div>
            
            <div class="page-description">
                Review and approve/reject user requests to become drone owners. From your flowchart.
            </div>
            
            <?php if (isset($message)): ?>
                <div class="message-box message-success" id="messageBox">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('pending')">⏳ Pending Requests (<?php echo count($requests); ?>)</button>
                <button class="tab-btn" onclick="switchTab('history')">📜 All Requests (<?php echo count($allRequests); ?>)</button>
            </div>
            
            <!-- Pending Requests Tab -->
            <div id="pending-tab" class="tab-content active">
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <h3>🎉 No Pending Requests</h3>
                        <p>There are currently no owner requests awaiting approval.</p>
                        <p>All requests have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="requests-grid">
                        <?php foreach ($requests as $request): 
                            // Safely get date
                            $requestDate = $request['request_date'] ?? date('Y-m-d H:i:s');
                        ?>
                        <div class="request-card">
                            <div class="request-header">
                                <h3>Request #<?php echo $request['id']; ?></h3>
                                <span class="status-badge status-pending">Pending</span>
                            </div>
                            <div class="request-body">
                                <div class="request-info">
                                    <div class="info-row">
                                        <span class="info-label">Applicant:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($request['name']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($request['email']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Request Date:</span>
                                        <span class="info-value"><?php echo date('Y-m-d H:i', strtotime($requestDate)); ?></span>
                                    </div>
                                    <?php if (isset($request['userID']) && $request['userID']): ?>
                                    <div class="info-row">
                                        <span class="info-label">User ID:</span>
                                        <span class="info-value">#<?php echo $request['userID']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <textarea name="notes" class="notes-input" placeholder="Add notes (optional)..." rows="2"></textarea>
                                    <div class="action-buttons">
                                        <button type="submit" name="action" value="approve" class="action-btn btn-approve" onclick="return confirm('Approve this owner request?')">✅ Approve</button>
                                        <button type="submit" name="action" value="reject" class="action-btn btn-reject" onclick="return confirm('Reject this owner request?')">❌ Reject</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- History Tab -->
            <div id="history-tab" class="tab-content">
                <?php if (empty($allRequests)): ?>
                    <div class="empty-state">
                        <h3>No Request History</h3>
                        <p>No owner requests have been made yet.</p>
                    </div>
                <?php else: ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Applicant</th>
                                <th>Email</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Approved/Rejected By</th>
                                <th>Date</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allRequests as $req): 
                                $statusClass = "status-" . $req['status'];
                                $requestDate = $req['request_date'] ?? date('Y-m-d H:i:s');
                            ?>
                            <tr>
                                <td>#<?php echo $req['id']; ?></td>
                                <td><?php echo htmlspecialchars($req['name']); ?></td>
                                <td><?php echo htmlspecialchars($req['email']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($requestDate)); ?></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                                <td>
                                    <?php if (isset($req['approved_by']) && $req['approved_by']): ?>
                                        Admin #<?php echo $req['approved_by']; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($req['approved_date']) && $req['approved_date']): ?>
                                        <?php echo date('Y-m-d', strtotime($req['approved_date'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($req['notes'] ?? '-'); ?></td>
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
                if (btn.textContent.includes(tabName === 'pending' ? 'Pending' : 'All')) {
                    btn.classList.add('active');
                }
            });
            
            // Update tab content
            document.getElementById('pending-tab').classList.remove('active');
            document.getElementById('history-tab').classList.remove('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
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