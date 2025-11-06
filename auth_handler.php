<?php
require 'db.php';
require 'logger.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['Email'];
    $password = $_POST['Password'];

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['Password'])) {
        $_SESSION['UserID'] = $user['UserID'];
        $_SESSION['Email'] = $user['Email'];
        logAction($user['UserID'], "Logged in.");
        header('Location: dashboard.php');
        exit();
    } else {
        echo 'Invalid credentials!';
    }
}
?>
