<?php
// chest.php — Fixed: uses PDO ($pdo) and falls back if rentals.Status doesn't exist
session_start();
require_once 'db.php';
require_once 'auth.php';
require_once 'logger.php';

// Make sure $pdo exists
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Helpful error message for development — remove or log in production
    die('Database connection error: $pdo not found. Check db.php');
}

// Redirect admins away (if you want admins to not access this page)
if (function_exists('isAdmin') && isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

// Ensure user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: index_login.php');
    exit();
}

$user_id = (int) $_SESSION['UserID'];

// Optional: log page access (logger should handle missing session Email gracefully)
if (function_exists('logEvent')) {
    $email = $_SESSION['Email'] ?? 'unknown';
    logEvent($email, 'Accessed My Rentals (chest.php)');
}

// Get user info (name/email)
try {
    $user_stmt = $pdo->prepare("SELECT Name, Email FROM users WHERE UserID = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC) ?: ['Name' => 'N/A', 'Email' => 'N/A'];
} catch (PDOException $e) {
    // Fatal if we can't fetch user
    die("DB error fetching user: " . htmlspecialchars($e->getMessage()));
}

// Try the "Status column present" SQL first; if it fails, fall back to a query without r.Status
$sql_with_status = "SELECT 
    r.RentalID,
    r.RentStart,
    r.RentEnd,
    r.TotalCost,
    r.Status,
    d.DroneID,
    d.Brand,
    d.Model,
    d.Size,
    d.PricePerDay,
    d.ImageURL,
    d.Description,
    d.UsageCase,
    CASE 
        WHEN r.Status = 'CANCELLED' THEN 'CANCELLED'
        WHEN r.RentEnd < NOW() THEN 'OVERDUE'
        ELSE 'ACTIVE'
    END AS StatusDisplay
FROM rentals r
JOIN drones d ON r.DroneID = d.DroneID
WHERE r.UserID = ?
ORDER BY 
    CASE 
        WHEN r.Status = 'CANCELLED' THEN 4
        WHEN r.RentEnd < NOW() THEN 3
        WHEN r.RentStart > NOW() THEN 1
        ELSE 2
    END,
    r.RentEnd ASC";

$sql_without_status = "SELECT 
    r.RentalID,
    r.RentStart,
    r.RentEnd,
    r.TotalCost,
    d.DroneID,
    d.Brand,
    d.Model,
    d.Size,
    d.PricePerDay,
    d.ImageURL,
    d.Description,
    d.UsageCase,
    CASE 
        WHEN r.RentEnd < NOW() THEN 'OVERDUE'
        WHEN r.RentStart > NOW() THEN 'ACTIVE'
        ELSE 'ACTIVE'
    END AS StatusDisplay
FROM rentals r
JOIN drones d ON r.DroneID = d.DroneID
WHERE r.UserID = ?
ORDER BY 
    CASE 
        WHEN r.RentEnd < NOW() THEN 3
        WHEN r.RentStart > NOW() THEN 1
        ELSE 2
    END,
    r.RentEnd ASC";

$rentals = [];
try {
    $stmt = $pdo->prepare($sql_with_status);
    $stmt->execute([$user_id]);
    $rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If column not found (SQLSTATE 42S22) or other error, try fallback query without Status
    if ($e->getCode() === '42S22' || stripos($e->getMessage(), 'Unknown column') !== false) {
        try {
            $stmt = $pdo->prepare($sql_without_status);
            $stmt->execute([$user_id]);
            $rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            die("DB error fetching rentals (fallback): " . htmlspecialchars($e2->getMessage()));
        }
    } else {
        die("DB error fetching rentals: " . htmlspecialchars($e->getMessage()));
    }
}

// Count rentals by status
$active_count = $cancelled_count = $overdue_count = 0;
foreach ($rentals as $r) {
    $sd = $r['StatusDisplay'] ?? 'ACTIVE';
    if ($sd === 'ACTIVE') $active_count++;
    elseif ($sd === 'CANCELLED') $cancelled_count++;
    elseif ($sd === 'OVERDUE') $overdue_count++;
}

// Optional success messages for cancellations (from cancel_rental.php)
$success_msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'cancelled') {
        $success_msg = '<div class="success-message"><h3>Rental Cancelled Successfully!</h3><p>Your rental has been cancelled. Refunds (if any) will be processed according to our policy.</p></div>';
    } elseif ($_GET['msg'] === 'cantcancel') {
        $success_msg = '<div class="error-message"><h3>Cannot Cancel Rental</h3><p>This rental cannot be cancelled because it has already started or does not exist.</p></div>';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Drone Rentals - Airusea</title>
<link rel="stylesheet" href="style.css">
<style>
/* Minimal inline styles so page is usable right away */
.page-container { max-width:1000px; margin:40px auto; padding:20px; }
.rental-card { border:1px solid #ddd; padding:16px; border-radius:8px; margin-bottom:18px; display:flex; gap:16px; align-items:flex-start; background:#fff; }
.drone-image { width:150px; height:100px; object-fit:cover; border-radius:6px; }
.status { padding:6px 10px; border-radius:16px; font-weight:bold; }
.status.active { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.status.overdue { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.status.cancelled { background:#f2f2f2; color:#666; border:1px solid #ddd; }
.button { display:inline-block; padding:8px 12px; background:#3498db; color:#fff; text-decoration:none; border-radius:6px; }
.cancel-btn { background:#e74c3c; border:0; padding:8px 12px; color:#fff; border-radius:6px; cursor:pointer; }
.customer-box { margin-bottom:20px; background:#fff; padding:12px; border-radius:8px; border:1px solid #eee; }
.rental-stats { display:flex; gap:12px; margin-bottom:20px; }
.stat-box { padding:10px 14px; border-radius:8px; background:#fff; border:1px solid #eee; min-width:100px; text-align:center; }
</style>
</head>
<body>
<header>
    <div class="header-content">
        <img src="images/logo.jpg" alt="Airusea Logo" class="logo">
        <nav class="navbar">
            <a href="index.php">Home</a>
            <?php if (!function_exists('isAdmin') || !isAdmin()): ?><a href="drones.php">Rent A Drone</a><?php endif; ?>
            <a href="logout.php" onclick="return confirm('Log out?');">Logout</a>
        </nav>
</header>

<div class="page-container">
    <h1>My Drone Rentals</h1>

    <?php echo $success_msg; ?>

    <div class="customer-box">
        <strong>Name:</strong> <?php echo htmlspecialchars($user['Name'] ?? 'N/A'); ?> <br>
        <strong>Email:</strong> <?php echo htmlspecialchars($user['Email'] ?? 'N/A'); ?> <br>
        <strong>Total Rentals:</strong> <?php echo count($rentals); ?>
    </div>

    <div class="rental-stats">
        <div class="stat-box"><strong><?php echo $active_count; ?></strong><div>Active</div></div>
        <div class="stat-box"><strong><?php echo $cancelled_count; ?></strong><div>Cancelled</div></div>
        <div class="stat-box"><strong><?php echo $overdue_count; ?></strong><div>Overdue</div></div>
    </div>

    <h2>Your Current Rentals</h2>

    <?php if (empty($rentals)): ?>
        <div>No rentals found. <a class="button" href="dashboard.php">Browse Drones</a></div>
    <?php else: ?>
        <?php foreach ($rentals as $rental): 
            // Cancel allowed if status computed as ACTIVE and rental hasn't started yet
            $rentStartTs = isset($rental['RentStart']) ? strtotime($rental['RentStart']) : 0;
            $can_cancel = ( ($rental['StatusDisplay'] ?? 'ACTIVE') === 'ACTIVE' && $rentStartTs > time() );
            $shortDesc = $rental['Description'] ?? '';
            if (strlen($shortDesc) > 150) $shortDesc = substr($shortDesc,0,150).'...';
        ?>
        <div class="rental-card">
            <div>
                <?php if (!empty($rental['ImageURL'])): ?>
                    <img src="<?php echo htmlspecialchars($rental['ImageURL']); ?>" alt="Drone image" class="drone-image">
                <?php else: ?>
                    <div class="drone-image" style="background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;">No Image</div>
                <?php endif; ?>
            </div>

            <div style="flex:1;">
                <h3><?php echo htmlspecialchars($rental['Brand'] . ' ' . $rental['Model']); ?></h3>

                <div style="margin:6px 0;">
                    <span class="status <?php echo strtolower($rental['StatusDisplay'] ?? 'active'); ?>">
                        <?php echo htmlspecialchars($rental['StatusDisplay'] ?? 'ACTIVE'); ?>
                    </span>
                </div>

                <p><strong>Rental Start:</strong> <?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($rental['RentStart']))); ?></p>
                <p><strong>Rental End:</strong> <?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($rental['RentEnd']))); ?></p>
                <p><strong>Total Cost:</strong> ₱<?php echo number_format($rental['TotalCost'] ?? 0, 2); ?></p>

                <?php if (!empty($shortDesc)): ?>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($shortDesc); ?></p>
                <?php endif; ?>

                <p>
                    <a class="button" href="drone_details.php?DroneID=<?php echo urlencode($rental['DroneID']); ?>">View Drone Details</a>

                    <?php if ($can_cancel): ?>
                        <!-- POST-based cancel form (safer than GET) -->
                        <form style="display:inline-block;margin-left:8px;" method="post" action="cancel_rental.php" onsubmit="return confirm('Are you sure you want to cancel this rental?');">
                            <input type="hidden" name="RentalID" value="<?php echo htmlspecialchars($rental['RentalID']); ?>">
                            <button type="submit" class="cancel-btn">Cancel Rental</button>
                        </form>
                    <?php elseif (($rental['StatusDisplay'] ?? '') === 'CANCELLED'): ?>
                        <span style="margin-left:10px;color:#666;">Already cancelled</span>
                    <?php elseif (($rental['StatusDisplay'] ?? '') === 'OVERDUE'): ?>
                        <span style="margin-left:10px;color:#c0392b;">Cannot cancel (overdue)</span>
                    <?php else: ?>
                        <span style="margin-left:10px;color:#666;">Cannot cancel (rental started)</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
