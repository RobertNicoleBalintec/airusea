<?php
session_start();
require_once 'db.php';

// Check if user is super admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['is_superadmin']) || $_SESSION['is_superadmin'] != 1) {
    header("Location: index_login.php");
    exit();
}

// Check if penalties table exists, create if not
try {
    $pdo->query("SELECT 1 FROM penalties LIMIT 1");
} catch (Exception $e) {
    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS penalties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        penalty_type VARCHAR(50),
        description TEXT,
        rate_per_day DECIMAL(10,2),
        grace_period_days INT,
        max_days INT,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Add default penalty settings
    $defaultPenalties = [
        ['Overdue Return', 'Penalty for returning drone after due date', 15.00, 1, 30],
        ['Damage Fee', 'Penalty for damaged drone equipment', 50.00, 0, 0],
        ['Late Payment', 'Penalty for late rental payment', 10.00, 3, 60],
        ['Lost Item', 'Penalty for lost drone or accessories', 200.00, 0, 0],
        ['Rule Violation', 'Penalty for violating rental rules', 25.00, 0, 0]
    ];
    
    foreach ($defaultPenalties as $penalty) {
        $stmt = $pdo->prepare("INSERT INTO penalties (penalty_type, description, rate_per_day, grace_period_days, max_days, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$penalty[0], $penalty[1], $penalty[2], $penalty[3], $penalty[4], $_SESSION['UserID']]);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add' || $action == 'update') {
            $penaltyType = $_POST['penalty_type'] ?? '';
            $description = $_POST['description'] ?? '';
            $ratePerDay = $_POST['rate_per_day'] ?? 0;
            $gracePeriod = $_POST['grace_period_days'] ?? 0;
            $maxDays = $_POST['max_days'] ?? 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if ($action == 'add') {
                $stmt = $pdo->prepare("INSERT INTO penalties (penalty_type, description, rate_per_day, grace_period_days, max_days, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$penaltyType, $description, $ratePerDay, $gracePeriod, $maxDays, $isActive, $_SESSION['UserID']]);
                $message = "Penalty rule added successfully";
                
            } elseif ($action == 'update' && isset($_POST['penalty_id'])) {
                $penaltyID = $_POST['penalty_id'];
                $stmt = $pdo->prepare("UPDATE penalties SET penalty_type = ?, description = ?, rate_per_day = ?, grace_period_days = ?, max_days = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$penaltyType, $description, $ratePerDay, $gracePeriod, $maxDays, $isActive, $penaltyID]);
                $message = "Penalty rule updated successfully";
            }
            
        } elseif ($action == 'delete' && isset($_POST['penalty_id'])) {
            $penaltyID = $_POST['penalty_id'];
            $stmt = $pdo->prepare("DELETE FROM penalties WHERE id = ?");
            $stmt->execute([$penaltyID]);
            $message = "Penalty rule deleted successfully";
        }
    }
}

// Fetch all penalty rules
$stmt = $pdo->query("SELECT * FROM penalties ORDER BY is_active DESC, penalty_type ASC");
$penalties = $stmt->fetchAll();

// Calculate total active penalty rules
$activeCount = count(array_filter($penalties, fn($p) => $p['is_active'] == 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Overdue Penalties | Super Admin</title>
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
            border-bottom: 4px solid #9b59b6;
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
        
        .penalties-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .penalty-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .penalty-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .penalty-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .penalty-header h3 {
            color: #2c3e50;
            margin: 0;
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
        
        .penalty-body {
            padding: 20px;
        }
        
        .penalty-details {
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-label {
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .detail-value {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .penalty-rate {
            font-size: 24px;
            color: #e74c3c;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
        }
        
        .penalty-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
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
        .btn-delete { background: #dc3545; color: white; }
        .btn-toggle { background: #6c757d; color: white; }
        
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
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
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
        .stat-card.revenue { border-color: #9b59b6; }
        
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
        
        .penalty-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .penalty-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }
        
        .penalty-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .penalty-table tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Set Overdue Penalties <span class="super-admin-badge">SUPER ADMIN</span></h1>
            <div style="color: white;">
                <p><strong><?php echo htmlspecialchars($_SESSION['Name'] ?? 'Admin'); ?></strong></p>
                <p><small><?php echo htmlspecialchars($_SESSION['Email'] ?? ''); ?></small></p>
            </div>
        </div>
        
        <div class="content">
            <div class="page-title">
                💰 Set Overdue Penalties
            </div>
            
            <div class="page-description">
                Configure penalty rates per day for overdue drone returns. From your flowchart.
            </div>
            
            <?php if (isset($message)): ?>
                <div class="message-box message-success" id="messageBox">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-bar">
                <div class="stat-card total">
                    <div class="number"><?php echo count($penalties); ?></div>
                    <div class="label">Total Penalty Rules</div>
                </div>
                <div class="stat-card active">
                    <div class="number"><?php echo $activeCount; ?></div>
                    <div class="label">Active Rules</div>
                </div>
                <div class="stat-card inactive">
                    <div class="number"><?php echo count($penalties) - $activeCount; ?></div>
                    <div class="label">Inactive Rules</div>
                </div>
                <div class="stat-card revenue">
                    <div class="number">$<?php echo number_format(array_sum(array_column($penalties, 'rate_per_day')), 2); ?></div>
                    <div class="label">Total Daily Rates</div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>➕ Add New Penalty Rule</h3>
                <form method="POST" id="penaltyForm">
                    <input type="hidden" name="action" value="add" id="formAction">
                    <input type="hidden" name="penalty_id" id="penaltyId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="penalty_type">Penalty Type *</label>
                            <input type="text" name="penalty_type" id="penalty_type" required 
                                   placeholder="e.g., Overdue Return, Damage Fee">
                        </div>
                        <div class="form-group">
                            <label for="rate_per_day">Rate Per Day ($) *</label>
                            <input type="number" name="rate_per_day" id="rate_per_day" required 
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="grace_period_days">Grace Period (Days)</label>
                            <input type="number" name="grace_period_days" id="grace_period_days" 
                                   min="0" value="1" placeholder="0">
                        </div>
                        <div class="form-group">
                            <label for="max_days">Maximum Days Applied</label>
                            <input type="number" name="max_days" id="max_days" 
                                   min="0" value="30" placeholder="0 for unlimited">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea name="description" id="description" required 
                                  placeholder="Describe the penalty rule and when it applies..."></textarea>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="is_active" checked>
                        <label for="is_active">Active Rule</label>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="submit-btn" id="submitBtn">➕ Add Penalty Rule</button>
                        <button type="button" class="reset-btn" onclick="resetForm()">🔄 Reset Form</button>
                    </div>
                </form>
            </div>
            
            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Current Penalty Rules</h3>
            
            <?php if (empty($penalties)): ?>
                <div class="empty-state">
                    <h3>No Penalty Rules Defined</h3>
                    <p>Add your first penalty rule using the form above.</p>
                </div>
            <?php else: ?>
                <div class="penalties-grid">
                    <?php foreach ($penalties as $penalty): 
                        $statusClass = $penalty['is_active'] ? 'status-active' : 'status-inactive';
                        $statusText = $penalty['is_active'] ? 'Active' : 'Inactive';
                    ?>
                    <div class="penalty-card">
                        <div class="penalty-header">
                            <h3><?php echo htmlspecialchars($penalty['penalty_type']); ?></h3>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </div>
                        <div class="penalty-body">
                            <div class="penalty-details">
                                <div class="detail-row">
                                    <span class="detail-label">Rate/Day:</span>
                                    <span class="detail-value">$<?php echo number_format($penalty['rate_per_day'], 2); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Grace Period:</span>
                                    <span class="detail-value"><?php echo $penalty['grace_period_days']; ?> days</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Max Days:</span>
                                    <span class="detail-value"><?php echo $penalty['max_days'] == 0 ? 'Unlimited' : $penalty['max_days'] . ' days'; ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Last Updated:</span>
                                    <span class="detail-value"><?php echo date('Y-m-d', strtotime($penalty['updated_at'])); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($penalty['description'])): ?>
                            <div class="penalty-description">
                                <?php echo htmlspecialchars($penalty['description']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons">
                                <button type="button" class="action-btn btn-edit" 
                                        onclick="editPenalty(<?php echo $penalty['id']; ?>, '<?php echo htmlspecialchars($penalty['penalty_type']); ?>', '<?php echo htmlspecialchars($penalty['description']); ?>', <?php echo $penalty['rate_per_day']; ?>, <?php echo $penalty['grace_period_days']; ?>, <?php echo $penalty['max_days']; ?>, <?php echo $penalty['is_active']; ?>)">
                                    ✏️ Edit
                                </button>
                                <form method="POST" style="display: inline; flex: 1;" onsubmit="return confirm('Delete this penalty rule?')">
                                    <input type="hidden" name="penalty_id" value="<?php echo $penalty['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="action-btn btn-delete">🗑️ Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 10px; border-left: 4px solid #f39c12;">
                <h4 style="color: #856404; margin-bottom: 10px;">💡 How Penalties Work</h4>
                <p style="color: #856404; margin-bottom: 10px;"><strong>Grace Period:</strong> Number of days after due date before penalty starts.</p>
                <p style="color: #856404; margin-bottom: 10px;"><strong>Rate Per Day:</strong> Amount charged per day after grace period ends.</p>
                <p style="color: #856404;"><strong>Max Days:</strong> Maximum number of days penalty is applied (0 = unlimited).</p>
            </div>
            
            <a href="superadmin_panel.php" class="back-btn">← Back to Super Admin Panel</a>
        </div>
    </div>
    
    <script>
        function editPenalty(id, type, description, rate, grace, max, active) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('penaltyId').value = id;
            document.getElementById('penalty_type').value = type;
            document.getElementById('description').value = description;
            document.getElementById('rate_per_day').value = rate;
            document.getElementById('grace_period_days').value = grace;
            document.getElementById('max_days').value = max;
            document.getElementById('is_active').checked = active == 1;
            document.getElementById('submitBtn').textContent = '💾 Update Penalty Rule';
            
            // Scroll to form
            document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
        }
        
        function resetForm() {
            document.getElementById('penaltyForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('penaltyId').value = '';
            document.getElementById('submitBtn').textContent = '➕ Add Penalty Rule';
        }
        
        // Auto-hide message after 5 seconds
        setTimeout(() => {
            const messageBox = document.getElementById('messageBox');
            if (messageBox) {
                messageBox.style.display = 'none';
            }
        }, 5000);
        
        // Calculate penalty example
        function calculatePenalty() {
            const rate = parseFloat(document.getElementById('rate_per_day').value) || 0;
            const grace = parseInt(document.getElementById('grace_period_days').value) || 0;
            const max = parseInt(document.getElementById('max_days').value) || 0;
            
            let example = '';
            if (rate > 0) {
                example = `Example: If a drone is 10 days overdue, penalty = $${rate} × (10 - ${grace}) = $${rate * (10 - grace)}`;
                if (max > 0) {
                    example += ` (capped at $${rate * max} for ${max} days)`;
                }
                alert(example);
            }
        }
    </script>
</body>
</html>