<?php
require_once 'db.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo "<h2>Database Tables</h2>";
echo "<ul>";
foreach ($tables as $table) {
    echo "<li>$table</li>";
    
    // Show columns for each table
    $columns = $pdo->query("DESCRIBE $table")->fetchAll();
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li>{$col['Field']} ({$col['Type']})</li>";
    }
    echo "</ul>";
}
echo "</ul>";
?>