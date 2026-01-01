<?php
// setup_superadmin.php - ONE-TIME SETUP SCRIPT
session_start();
echo "<h2>Super Admin Setup Utility</h2>";

if (!file_exists('db.php')) {
    die("Error: db.php not found. Please create it first.");
}

require_once 'db.php';

// Check if super_admins table exists
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('super_admins', $tables)) {
    echo "<p>Creating super_admins table...</p>";
    
    $sql = "CREATE TABLE IF NOT EXISTS super_admins (
        superAdminID INT PRIMARY KEY AUTO_INCREMENT,
        userID INT NOT NULL UNIQUE,
        assigned_by INT,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
    )";
    
    try {
        $pdo->exec($sql);
        echo "<p style='color:green;'>✓ super_admins table created</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red;'>✗ Error creating table: " . $e->getMessage() . "</p>";
    }
}

// Get first admin user or create one
$user = $pdo->query("SELECT * FROM users WHERE is_admin = 1 LIMIT 1")->fetch();

if (!$user) {
    echo "<p>No admin user found. Checking regular users...</p>";
    $user = $pdo->query("SELECT * FROM users LIMIT 1")->fetch();
    
    if ($user) {
        echo "<p>Making user #{$user['userID']} ({$user['email']}) an admin...</p>";
        $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE userID = ?");
        $stmt->execute([$user['userID']]);
    } else {
        echo "<p>No users found. Please register first.</p>";
        echo "<p><a href='register.php'>Register here</a></p>";
        exit();
    }
}

// Make this user a super admin
$check = $pdo->prepare("SELECT COUNT(*) FROM super_admins WHERE userID = ?");
$check->execute([$user['userID']]);

if ($check->fetchColumn() == 0) {
    echo "<p>Making user #{$user['userID']} ({$user['email']}) a super admin...</p>";
    
    $stmt = $pdo->prepare("INSERT INTO super_admins (userID) VALUES (?)");
    $stmt->execute([$user['userID']]);
    
    echo "<p style='color:green;'>✓ Super admin created successfully!</p>";
    echo "<p>User ID: {$user['userID']}</p>";
    echo "<p>Email: {$user['email']}</p>";
    echo "<p>You can now login with this account.</p>";
    echo "<p><a href='index_login.php'>Go to Login</a></p>";
} else {
    echo "<p style='color:orange;'>⚠ User #{$user['userID']} is already a super admin</p>";
    echo "<p><a href='index_login.php'>Go to Login</a></p>";
}

// Show all super admins
echo "<h3>Current Super Admins:</h3>";
$superAdmins = $pdo->query("
    SELECT u.userID, u.email, u.name, s.assigned_at 
    FROM users u 
    JOIN super_admins s ON u.userID = s.userID
")->fetchAll();

if (count($superAdmins) > 0) {
    echo "<table border='1' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Assigned</th></tr>";
    foreach ($superAdmins as $admin) {
        echo "<tr>";
        echo "<td>{$admin['userID']}</td>";
        echo "<td>{$admin['email']}</td>";
        echo "<td>{$admin['name']}</td>";
        echo "<td>{$admin['assigned_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No super admins found.</p>";
}
?>