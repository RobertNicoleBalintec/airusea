<?php
require 'auth.php';
requireLogin();

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

require 'db.php';
require 'logger.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_panel.php?error=invalid_id');
    exit();
}

$drone_id = intval($_GET['id']);

try {
    // Check if drone exists
    $stmt = $pdo->prepare("SELECT * FROM drones WHERE DroneID = ?");
    $stmt->execute([$drone_id]);
    $drone = $stmt->fetch();
    
    if (!$drone) {
        header('Location: admin_panel.php?error=drone_not_found');
        exit();
    }
    
    // Check if drone is already phased out
    if ($drone['status'] === 'phased_out') {
        header('Location: admin_panel.php?error=drone_already_phased_out');
        exit();
    }
    
    // Check if drone has active rentals
    $rental_check = $pdo->prepare("
        SELECT * FROM rentals 
        WHERE DroneID = ? 
        AND RentEnd >= NOW()
        AND status = 'active'
    ");
    $rental_check->execute([$drone_id]);
    
    if ($rental_check->rowCount() > 0) {
        header('Location: admin_panel.php?error=drone_has_active_rentals');
        exit();
    }
    
    // Check if there are any future rentals scheduled
    $future_rentals_check = $pdo->prepare("
        SELECT * FROM rentals 
        WHERE DroneID = ? 
        AND RentStart > NOW()
        AND status = 'active'
    ");
    $future_rentals_check->execute([$drone_id]);
    
    if ($future_rentals_check->rowCount() > 0) {
        // If there are future rentals, we should cancel them first
        $cancel_future_stmt = $pdo->prepare("
            UPDATE rentals 
            SET status = 'cancelled', 
                CancelledAt = NOW()
            WHERE DroneID = ? 
            AND RentStart > NOW()
            AND status = 'active'
        ");
        $cancel_future_stmt->execute([$drone_id]);
        
        logEvent($_SESSION['UserID'], 
            "Cancelled future rentals for drone ID: $drone_id before phasing out");
    }
    
    // Instead of deleting, mark as phased_out
    $update_stmt = $pdo->prepare("
        UPDATE drones 
        SET status = 'phased_out', 
            QuantityAvailable = 0,
            phased_from_id = CASE 
                WHEN phased_from_id IS NULL OR phased_from_id = 0 
                THEN DroneID 
                ELSE phased_from_id 
            END
        WHERE DroneID = ?
    ");
    
    $update_stmt->execute([$drone_id]);
    
    // Verify the update
    $verify_stmt = $pdo->prepare("SELECT status FROM drones WHERE DroneID = ?");
    $verify_stmt->execute([$drone_id]);
    $updated_drone = $verify_stmt->fetch();
    
    if ($updated_drone && $updated_drone['status'] === 'phased_out') {
        // Log the action with detailed information
        $log_message = "Phased out drone ID: $drone_id ({$drone['Brand']} {$drone['Model']})";
        
        // Add phased_from_id info if available
        $phased_info_stmt = $pdo->prepare("SELECT phased_from_id FROM drones WHERE DroneID = ?");
        $phased_info_stmt->execute([$drone_id]);
        $phased_info = $phased_info_stmt->fetch();
        
        if ($phased_info && $phased_info['phased_from_id']) {
            $log_message .= " [Phased from ID: #{$phased_info['phased_from_id']}]";
        }
        
        logEvent($_SESSION['UserID'] ?? $_SESSION['Email'] ?? 'Admin', $log_message);
        
        // Redirect with success message
        header('Location: admin_panel.php?success=phased_out&id=' . $drone_id);
        exit();
    } else {
        // If update failed, show error
        throw new Exception("Failed to phase out drone. Status update did not complete.");
    }
    
} catch (PDOException $e) {
    // Log the error
    logEvent($_SESSION['UserID'] ?? 'System', 
        "ERROR removing drone ID: $drone_id - " . $e->getMessage());
    
    // Redirect with error
    header('Location: admin_panel.php?error=db_error&message=' . urlencode($e->getMessage()));
    exit();
} catch (Exception $e) {
    // Log the error
    logEvent($_SESSION['UserID'] ?? 'System', 
        "ERROR removing drone ID: $drone_id - " . $e->getMessage());
    
    // Redirect with error
    header('Location: admin_panel.php?error=general_error&message=' . urlencode($e->getMessage()));
    exit();
}