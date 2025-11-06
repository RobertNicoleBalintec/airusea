<?php
require 'db.php';
require 'logger.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $stmt = $pdo->prepare('INSERT INTO users (Name, Email, Password, Phone, Address) VALUES (?, ?, ?, ?, ?)');
    if ($stmt->execute([$name, $email, $password, $phone, $address])) {
        logAction($pdo->lastInsertId(), "Registered.");
        header('Location: index.php');
        exit();
    } else {
        echo 'Registration failed!';
    }
}
?>
