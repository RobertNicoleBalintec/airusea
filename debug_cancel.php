<?php
session_start();
require_once 'db.php';

echo "<pre>";
echo "=== DEBUG CANCELLATION ===\n";

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    echo "ERROR: Not logged in\n";
    exit();
}

// Get rental ID from URL
$rental_id = $_GET['RentalID'] ?? 17; // Default to test rental 17
$user_id = $_SESSION['UserID'];

echo "Rental ID: $rental_id\n";
echo "User ID: $user_id\n";

// Check rental details
$sql = "SELECT * FROM rentals WHERE RentalID = ? AND UserID = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$rental_id, $user_id]);
$rental = $stmt->fetch();

if (!$rental) {
    echo "ERROR: Rental not found or doesn't belong to user\n";
    exit();
}

echo "\n=== RENTAL DETAILS ===\n";
print_r($rental);

echo "\n=== TIME CHECK ===\n";
$rent_start = strtotime($rental['RentStart']);
$current_time = time();
echo "Rent Start: " . $rental['RentStart'] . " ($rent_start)\n";
echo "Current Time: " . date('Y-m-d H:i:s') . " ($current_time)\n";
echo "Difference: " . ($rent_start - $current_time) . " seconds\n";
echo "Has started? " . ($current_time > $rent_start ? 'YES' : 'NO') . "\n";

echo "\n=== ATTEMPTING CANCELLATION ===\n";
$sql = "UPDATE rentals 
        SET Status = 'CANCELLED', CancelledAt = NOW() 
        WHERE RentalID = ? 
        AND UserID = ? 
        AND Status = 'ACTIVE'
        AND RentStart > NOW()";

echo "SQL: $sql\n";

$stmt = $pdo->prepare($sql);
$stmt->execute([$rental_id, $user_id]);
$affected = $stmt->rowCount();

echo "Affected rows: $affected\n";

if ($affected > 0) {
    echo "\n✅ SUCCESS: Rental cancelled!\n";
    
    // Verify
    $check = $pdo->prepare("SELECT Status, CancelledAt FROM rentals WHERE RentalID = ?");
    $check->execute([$rental_id]);
    $result = $check->fetch();
    echo "Verification: " . print_r($result, true);
} else {
    echo "\n❌ FAILED: No rows updated\n";
    
    // Check why
    echo "\n=== CHECKING CONDITIONS ===\n";
    
    $checks = [
        "Status = 'ACTIVE'" => ($rental['Status'] == 'ACTIVE'),
        "RentStart > NOW()" => (strtotime($rental['RentStart']) > time()),
        "User matches" => ($rental['UserID'] == $user_id)
    ];
    
    foreach ($checks as $condition => $result) {
        echo "$condition: " . ($result ? '✅ TRUE' : '❌ FALSE') . "\n";
    }
}

echo "\n<a href='chest.php'>Back to My Rentals</a>";
echo "</pre>";
?>