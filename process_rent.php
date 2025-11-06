<?php
session_start();
require 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if the user is not logged in
    header("Location: login.php");
    exit();
}

// Check if the form was submitted and the drone_id is set
if (isset($_POST['rent']) && isset($_POST['drone_id'])) {
    $drone_id = $_POST['drone_id'];
    $user_id = $_SESSION['user_id'];  // Assuming the user is logged in and their user ID is stored in session

    // Insert rental into the rentals table (this simulates the rental)
    $stmt = $pdo->prepare("INSERT INTO rentals (UserID, DroneID, RentalDate) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $drone_id]);

    echo "Drone rented successfully!";
} else {
    echo "Error: Unable to process your rental.";
}
?>
