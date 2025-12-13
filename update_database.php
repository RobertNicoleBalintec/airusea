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
            .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; }
            .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; }
            .info { color: #856404; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; }
        </style>
    </head>
    <body>";
    
    try {
        echo "<h2>Database Update Process</h2>";
        
        // Check if status column already exists (lowercase)
        $check = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'status'");
        if ($check->rowCount() == 0) {
            // Add lowercase status column
            $pdo->exec("ALTER TABLE rentals ADD COLUMN status VARCHAR(20) DEFAULT NULL");
            echo "<div class='success'>✅ Added 'status' column to rentals table</div>";
        } else {
            echo "<div class='info'>ℹ️ 'status' column already exists</div>";
        }
        
        // Check if CancelledAt column exists
        $check = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'CancelledAt'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE rentals ADD COLUMN CancelledAt DATETIME NULL");
            echo "<div class='success'>✅ Added 'CancelledAt' column to rentals table</div>";
        } else {
            echo "<div class='info'>ℹ️ 'CancelledAt' column already exists</div>";
        }
        
        // Update existing records
        $updateCount = $pdo->exec("UPDATE rentals SET status = 'active' WHERE status IS NULL");
        echo "<div class='success'>✅ Updated $updateCount existing rentals with 'active' status</div>";
        
        // Add an index for better performance (only if not exists)
        try {
            $pdo->exec("CREATE INDEX idx_status ON rentals(status)");
            echo "<div class='success'>✅ Created index on status column</div>";
        } catch (Exception $e) {
            echo "<div class='info'>ℹ️ Index might already exist</div>";
        }
        
        echo "<h3 class='success'>🎉 Database Update Completed Successfully!</h3>";
        echo "<p><a href='dashboard.php'>← Return to Dashboard</a></p>";
        
        // Log the action
        logEvent($_SESSION['Email'], 'Updated rentals table structure');
        
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