<?php
session_start();
require 'db.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: index_login.php");
    exit();
}

// Check if user is admin - admins shouldn't be renting
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    header("Location: dashboard.php?error=admin_cannot_rent");
    exit();
}

require 'logger.php';

if (isset($_POST['rent']) && isset($_POST['drone_id'])) {
    $drone_id = intval($_POST['drone_id']);
    $user_id = $_SESSION['UserID'];
    
    try {
        // 1. Check if drone exists and is available
        $check_stmt = $pdo->prepare("
            SELECT d.* 
            FROM drones d
            WHERE d.DroneID = ? 
            AND d.status = 'available' 
            AND d.QuantityAvailable > 0
        ");
        $check_stmt->execute([$drone_id]);
        $drone = $check_stmt->fetch();
        
        if (!$drone) {
            // Log the attempt to rent unavailable drone
            logEvent($_SESSION['Email'] ?? 'User ID: ' . $user_id, 
                "Attempted to rent unavailable drone #$drone_id");
            
            header("Location: drones.php?error=drone_unavailable");
            exit();
        }
        
        // 2. Check if drone is currently rented (additional safety check)
        $rental_check = $pdo->prepare("
            SELECT * FROM rentals 
            WHERE DroneID = ? 
            AND RentEnd >= NOW()
            AND status = 'active'
            LIMIT 1
        ");
        $rental_check->execute([$drone_id]);
        
        if ($rental_check->rowCount() > 0) {
            // Log conflict
            logEvent($_SESSION['Email'] ?? 'User ID: ' . $user_id, 
                "Attempted to rent already rented drone #$drone_id");
            
            header("Location: drones.php?error=drone_already_rented");
            exit();
        }
        
        // 3. Check if user has any overdue rentals
        $overdue_check = $pdo->prepare("
            SELECT COUNT(*) as overdue_count 
            FROM rentals 
            WHERE UserID = ? 
            AND RentEnd < NOW()
            AND status = 'active'
        ");
        $overdue_check->execute([$user_id]);
        $overdue_result = $overdue_check->fetch();
        
        if ($overdue_result['overdue_count'] > 0) {
            header("Location: drones.php?error=has_overdue_rentals");
            exit();
        }
        
        // 4. Check rent start and end dates
        $rent_start = date('Y-m-d H:i:s');
        
        // Calculate rent end (default 7 days from now)
        $rent_end_default = '+7 days';
        $rent_end = date('Y-m-d H:i:s', strtotime($rent_end_default));
        
        // If custom dates are provided, use them
        if (isset($_POST['rent_start']) && !empty($_POST['rent_start'])) {
            $rent_start = $_POST['rent_start'];
        }
        
        if (isset($_POST['rent_end']) && !empty($_POST['rent_end'])) {
            $rent_end = $_POST['rent_end'];
            
            // Validate rent end is after rent start
            if (strtotime($rent_end) <= strtotime($rent_start)) {
                header("Location: rent.php?DroneID=$drone_id&error=invalid_dates");
                exit();
            }
        }
        
        // 5. Calculate total cost
        $days = ceil((strtotime($rent_end) - strtotime($rent_start)) / (60 * 60 * 24));
        $total_cost = $days * $drone['PricePerDay'];
        
        // 6. Start transaction
        $pdo->beginTransaction();
        
        try {
            // 7. Insert rental record
            $stmt = $pdo->prepare("
                INSERT INTO rentals 
                (UserID, DroneID, RentStart, RentEnd, TotalCost, status) 
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([$user_id, $drone_id, $rent_start, $rent_end, $total_cost]);
            $rental_id = $pdo->lastInsertId();
            
            // 8. Decrease drone quantity available
            $update_drone_stmt = $pdo->prepare("
                UPDATE drones 
                SET QuantityAvailable = QuantityAvailable - 1 
                WHERE DroneID = ? 
                AND QuantityAvailable > 0
            ");
            
            $update_drone_stmt->execute([$drone_id]);
            
            // Check if update was successful
            if ($update_drone_stmt->rowCount() === 0) {
                throw new Exception("Failed to update drone quantity. It may have been rented by someone else.");
            }
            
            // 9. If quantity reaches 0, we could optionally mark as unavailable
            // but we'll let the status system handle it through the queries
            
            // 10. Commit transaction
            $pdo->commit();
            
            // 11. Log successful rental
            logEvent($_SESSION['Email'] ?? 'User ID: ' . $user_id, 
                "Rented drone #$drone_id ({$drone['Brand']} {$drone['Model']}) for $days days. Total: $" . number_format($total_cost, 2));
            
            // 12. Redirect to success page or rentals page
            header("Location: chest.php?success=rented&rental_id=$rental_id&drone_id=$drone_id");
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            $pdo->rollBack();
            
            // Log the error
            logEvent($_SESSION['Email'] ?? 'User ID: ' . $user_id, 
                "Rental failed for drone #$drone_id: " . $e->getMessage());
            
            throw $e; // Re-throw to be caught by outer catch
        }
        
    } catch (PDOException $e) {
        // Log database error
        logEvent($_SESSION['Email'] ?? 'System', 
            "Database error during rental process: " . $e->getMessage());
        
        header("Location: rent.php?DroneID=$drone_id&error=db_error&message=" . urlencode($e->getMessage()));
        exit();
    } catch (Exception $e) {
        // Log general error
        logEvent($_SESSION['Email'] ?? 'System', 
            "Rental process error: " . $e->getMessage());
        
        header("Location: rent.php?DroneID=$drone_id&error=rental_failed&message=" . urlencode($e->getMessage()));
        exit();
    }
    
} else {
    // Invalid request
    logEvent($_SESSION['Email'] ?? 'Guest', 
        "Invalid rental request - missing parameters");
    
    header("Location: drones.php?error=invalid_request");
    exit();
}
?>

<?php
// If somehow we reach here (shouldn't happen), show error
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rental Processing Error</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .error-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        .error-icon {
            font-size: 48px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .error-message {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .back-link {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        .back-link:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">❌</div>
        <div class="error-message">
            <h2>Rental Processing Error</h2>
            <p>An unexpected error occurred while processing your rental.</p>
            <p>Please try again or contact support if the problem persists.</p>
        </div>
        <a href="drones.php" class="back-link">Back to Available Drones</a>
    </div>
</body>
</html>