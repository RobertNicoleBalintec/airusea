<?php
// update_database.php
session_start();
require_once 'db.php';
require_once 'auth.php';

if (!isAdmin()) {
    header('Location: index_login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_status_column') {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Database Update</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
            .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
            .info { color: #856404; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; margin: 10px 0; }
        </style>
    </head>
    <body>";
    
    try {
        echo "<h2>Database Update Process</h2>";
        
        // 1. Check and add status column to rentals table
        $check = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'status'");
        if ($check->rowCount() == 0) {
            // Add lowercase status column
            $pdo->exec("ALTER TABLE rentals ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
            echo "<div class='success'>✅ Added 'status' column to rentals table with default 'active'</div>";
        } else {
            echo "<div class='info'>ℹ️ 'status' column already exists in rentals table</div>";
        }
        
        // 2. Standardize all status values to lowercase
        $pdo->exec("UPDATE rentals SET status = LOWER(status) WHERE status IS NOT NULL");
        echo "<div class='success'>✅ Standardized all status values to lowercase</div>";
        
        // 3. Update expired rentals (past due but still marked active)
        $expiredCount = $pdo->exec("UPDATE rentals SET status = 'expired' WHERE RentEnd < NOW() AND status = 'active'");
        echo "<div class='success'>✅ Updated $expiredCount past rentals to 'expired' status</div>";
        
        // 4. Check and add CancelledAt column
        $check = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'CancelledAt'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE rentals ADD COLUMN CancelledAt DATETIME NULL");
            echo "<div class='success'>✅ Added 'CancelledAt' column to rentals table</div>";
        } else {
            echo "<div class='info'>ℹ️ 'CancelledAt' column already exists</div>";
        }
        
        // 5. Check and add status column to drones table
        $check = $pdo->query("SHOW COLUMNS FROM drones LIKE 'status'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE drones ADD COLUMN status VARCHAR(20) DEFAULT 'available'");
            echo "<div class='success'>✅ Added 'status' column to drones table with default 'available'</div>";
        } else {
            echo "<div class='info'>ℹ️ 'status' column already exists in drones table</div>";
        }
        
        // 6. Update all drones to have available status if null
        $updateCount = $pdo->exec("UPDATE drones SET status = 'available' WHERE status IS NULL");
        echo "<div class='success'>✅ Updated $updateCount drones with 'available' status</div>";
        
        // 7. Create indexes for better performance
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rentals_status ON rentals(status)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_drones_status ON drones(status)");
            echo "<div class='success'>✅ Created indexes on status columns</div>";
        } catch (Exception $e) {
            echo "<div class='info'>ℹ️ " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // 8. Fix NULL RentEnd dates (RentalID 1 in your data)
        $nullCount = $pdo->exec("UPDATE rentals SET RentEnd = DATE_ADD(RentStart, INTERVAL 7 DAY) WHERE RentEnd IS NULL");
        echo "<div class='success'>✅ Fixed $nullCount NULL RentEnd dates</div>";
        
        echo "<h3 class='success'>🎉 Database Update Completed Successfully!</h3>";
        echo "<p><a href='dashboard.php'>← Return to Dashboard</a></p>";
        
        // Log the action
        logEvent($_SESSION['Email'], 'Updated database structure for status tracking');
        
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Error updating database: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<p><a href='dashboard.php'>← Return to Dashboard</a></p>";
    }
    
    echo "</body></html>";
} else {
    header('Location: dashboard.php');
    exit();
}
?>