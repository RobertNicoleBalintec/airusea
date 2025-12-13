<?php
// setup_cancellation.php - Run this once to set up cancellation feature
session_start();
require_once 'db.php';
require_once 'auth.php';

// Only allow admins to run this script
if (!isAdmin()) {
    die('<h2>Access Denied</h2><p>Only administrators can run this script.</p>');
}

echo "<h2>Setting up Cancellation Feature</h2>";

try {
    // Check if status column exists (lowercase)
    $check = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'status'");
    if ($check->rowCount() == 0) {
        // Add lowercase status column
        $pdo->exec("ALTER TABLE rentals ADD COLUMN status VARCHAR(20) DEFAULT NULL");
        echo "<p>✅ Added 'status' column to rentals table</p>";
    } else {
        echo "<p>⚠️ 'status' column already exists</p>";
    }
    
    // Check if CancelledAt column exists
    $check = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'CancelledAt'");
    if ($check->rowCount() == 0) {
        // Add CancelledAt column
        $pdo->exec("ALTER TABLE rentals ADD COLUMN CancelledAt DATETIME NULL");
        echo "<p>✅ Added 'CancelledAt' column to rentals table</p>";
    } else {
        echo "<p>⚠️ 'CancelledAt' column already exists</p>";
    }
    
    // Update existing rentals to have active status (lowercase)
    $pdo->exec("UPDATE rentals SET status = 'active' WHERE status IS NULL");
    echo "<p>✅ Updated existing rentals with active status</p>";
    
    // Add index for better performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_status ON rentals(status)");
    echo "<p>✅ Created index on status column</p>";
    
    echo "<h3 style='color: green;'>✅ Setup Completed Successfully!</h3>";
    echo "<p><a href='chest.php'>Go to My Rentals</a> | <a href='dashboard.php'>Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Setup Failed</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database permissions or run this SQL manually in phpMyAdmin:</p>";
    echo "<pre>
ALTER TABLE rentals ADD COLUMN status VARCHAR(20) DEFAULT NULL;
ALTER TABLE rentals ADD COLUMN CancelledAt DATETIME NULL;
UPDATE rentals SET status = 'active' WHERE status IS NULL;
CREATE INDEX idx_status ON rentals(status);
    </pre>";
}
?>