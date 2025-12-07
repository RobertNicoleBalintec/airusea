<?php
require_once 'db.php';

echo "<h2>Fixing Database Columns</h2>";

try {
    // Check for typo in column name
    $check = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'CancelIedAt'");
    if ($check->rowCount() > 0) {
        echo "<p>⚠️ Found typo: 'CancelIedAt' column exists (with capital I instead of l)</p>";
        
        // Rename the column
        $pdo->exec("ALTER TABLE rentals CHANGE CancelIedAt CancelledAt DATETIME NULL");
        echo "<p>✅ Fixed column name from 'CancelIedAt' to 'CancelledAt'</p>";
    } else {
        echo "<p>✅ Column name 'CancelledAt' is correct</p>";
    }
    
    // Check RentEnd column
    $check = $pdo->query("SELECT * FROM rentals WHERE RentEnd IS NULL LIMIT 1");
    if ($check->rowCount() > 0) {
        echo "<p>⚠️ Found rentals with NULL RentEnd dates</p>";
        
        // Fix RentEnd dates (set to 3 days after RentStart)
        $pdo->exec("UPDATE rentals SET RentEnd = DATE_ADD(RentStart, INTERVAL 3 DAY) WHERE RentEnd IS NULL");
        $affected = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
        echo "<p>✅ Fixed $affected rentals with RentEnd dates</p>";
    }
    
    // Check TotalCost column
    $check = $pdo->query("SELECT * FROM rentals WHERE TotalCost IS NULL LIMIT 1");
    if ($check->rowCount() > 0) {
        echo "<p>⚠️ Found rentals with NULL TotalCost</p>";
        
        // Calculate TotalCost (PricePerDay * 3 days)
        $pdo->exec("
            UPDATE rentals r
            JOIN drones d ON r.DroneID = d.DroneID
            SET r.TotalCost = d.PricePerDay * 3
            WHERE r.TotalCost IS NULL
        ");
        $affected = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
        echo "<p>✅ Fixed $affected rentals with TotalCost</p>";
    }
    
    echo "<h3 style='color: green;'>✅ Fixes Completed!</h3>";
    echo "<p><a href='chest.php'>Go to My Rentals</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Fix Failed</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>