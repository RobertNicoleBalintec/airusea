<?php
// check_user_files.php - List user management files
echo "<h3>Checking User Management Files</h3>";

$userFiles = [
    'safe_delete_user.php',
    'manage_user.php',
    'delete_user.php',
    'edit_user.php',
    'user_management.php'
];

echo "<ul>";
foreach ($userFiles as $file) {
    if (file_exists($file)) {
        echo "<li>✅ Found: <strong>$file</strong> - <a href='$file'>Open</a></li>";
    } else {
        echo "<li>❌ Missing: <strong>$file</strong></li>";
    }
}
echo "</ul>";

echo "<p><a href='admin_panel.php'>← Back to Admin Panel</a></p>";
?>