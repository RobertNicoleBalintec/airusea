<?php
// setup_cancellation.php - Run this once to set up cancellation feature
require_once 'db.php';

echo "<h2>Setting up Cancellation Feature</h2>";

try {
    // Check if Status column exists
    $check = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'Status'");
    if ($check->rowCount() == 0) {
        // Add Status column
        $pdo->exec("ALTER TABLE rentals ADD COLUMN Status VARCHAR(20) DEFAULT 'ACTIVE'");
        echo "<p>✅ Added 'Status' column to rentals table</p>";
    } else {
        echo "<p>⚠️ 'Status' column already exists</p>";
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
    
    // Update existing rentals to have ACTIVE status
    $pdo->exec("UPDATE rentals SET Status = 'ACTIVE' WHERE Status IS NULL");
    echo "<p>✅ Updated existing rentals with ACTIVE status</p>";
    
    echo "<h3 style='color: green;'>✅ Setup Completed Successfully!</h3>";
    echo "<p><a href='chest.php'>Go to My Rentals</a> | <a href='dashboard.php'>Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Setup Failed</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database permissions or run this SQL manually in phpMyAdmin:</p>";
    echo "<pre>
ALTER TABLE rentals ADD COLUMN Status VARCHAR(20) DEFAULT 'ACTIVE';
ALTER TABLE rentals ADD COLUMN CancelledAt DATETIME NULL;
UPDATE rentals SET Status = 'ACTIVE' WHERE Status IS NULL;
    </pre>";
}
?>