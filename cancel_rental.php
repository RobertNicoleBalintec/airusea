<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: index_login.php');
    exit();
}

// Get rental ID from URL
$rental_id = $_GET['RentalID'] ?? 0;
$user_id = $_SESSION['UserID'];

if ($rental_id == 0) {
    header('Location: chest.php?msg=notfound');
    exit();
}

try {
    // Cancel the rental
    $sql = "UPDATE rentals 
            SET Status = 'CANCELLED', CancelledAt = NOW() 
            WHERE RentalID = ? 
            AND UserID = ? 
            AND Status = 'ACTIVE'
            AND RentStart > NOW()";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$rental_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: chest.php?msg=cancelled&id=' . $rental_id);
    } else {
        header('Location: chest.php?msg=cantcancel');
    }
    exit();
    
} catch (Exception $e) {
    error_log("Cancellation error: " . $e->getMessage());
    header('Location: chest.php?msg=error');
    exit();
}
?>