<?php
require_once 'db.php';

echo "<h2>Checking Database Status Values</h2>";

// Check distinct status values in rentals
$stmt = $pdo->query("SELECT DISTINCT Status, COUNT(*) as count FROM rentals GROUP BY Status");
$statuses = $stmt->fetchAll();

echo "<h3>Current Status Values in Rentals Table:</h3>";
if (empty($statuses)) {
    echo "<p>No rentals found in database.</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    foreach ($statuses as $status) {
        echo "<tr><td>" . htmlspecialchars($status['Status'] ?? 'NULL') . "</td><td>" . $status['count'] . "</td></tr>";
    }
    echo "</table>";
}

// Check one sample rental
echo "<h3>Sample Rental Data:</h3>";
$sample = $pdo->query("SELECT * FROM rentals LIMIT 1")->fetch();
if ($sample) {
    echo "<pre>" . print_r($sample, true) . "</pre>";
} else {
    echo "<p>No rentals found.</p>";
}

echo "<p><a href='chest.php'>Back to My Rentals</a></p>";
?>