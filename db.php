<?php
// db.php - FIXED VERSION
$host = "localhost";
$db = "airusea"; 
$user = "root";
$pass = "";        
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Create mysqli connection for compatibility
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("MySQLi connection failed: " . $conn->connect_error);
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>