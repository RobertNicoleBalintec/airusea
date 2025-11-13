<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['rent']) && isset($_POST['drone_id'])) {
    $drone_id = $_POST['drone_id'];
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO rentals (UserID, DroneID, RentalDate) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $drone_id]);

    echo "Drone rented successfully!";
} else {
    echo "Error: Unable to process your rental.";
}
?>
