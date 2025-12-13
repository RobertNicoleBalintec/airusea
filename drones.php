<?php
session_start();
require_once 'db.php';
require_once 'logger.php';
require_once 'auth.php';

logEvent($_SESSION['Email'] ?? 'Guest', 'Accessed the drones page');

if (!isset($_SESSION['UserID'])) {
    header("Location: index_login.php");
    exit();
}

// CHECK IF USER IS ADMIN - REDIRECT IF THEY ARE
if (isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

// ========== AVAILABLE DRONES ==========
// Show drones that are NOT phased_out and NOT currently rented
$availableQuery = "
    SELECT d.* 
    FROM drones d
    WHERE d.status = 'available' 
    AND d.QuantityAvailable > 0
    AND NOT EXISTS (
        SELECT 1 FROM rentals r 
        WHERE r.DroneID = d.DroneID 
        AND r.RentEnd >= NOW()
        AND r.status = 'active'
    )
";
$params = [];

if (!empty($_GET['query'])) {
    $availableQuery .= " AND (d.Model LIKE :search OR d.Brand LIKE :search OR d.PricePerDay LIKE :search)";
    $params[':search'] = "%" . $_GET['query'] . "%";
}

$stmt = $pdo->prepare($availableQuery);
$stmt->execute($params);

// ========== DEPLOYED DRONES ==========
// Show drones that are currently rented (rent not due)
$deployedQuery = "
    SELECT d.*, r.RentStart, r.RentEnd, u.Email, u.Name
    FROM drones d
    JOIN rentals r ON d.DroneID = r.DroneID
    JOIN users u ON r.UserID = u.UserID
    WHERE r.RentEnd >= NOW()  -- Rent not due
    AND r.status = 'active'
    AND d.status != 'phased_out'
    ORDER BY r.RentEnd ASC
";
$deployedStmt = $pdo->prepare($deployedQuery);
$deployedStmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Available Drones</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Add these styles to your existing style.css or here */
        .drones-container {
            display: flex;
            justify-content:
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }
        .drone {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            width: 250px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .drone.deployed {
            background-color: #1313138f;
            border-color: #ffd54f;
        }
        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
            display: inline-block;
            margin-left: 5px;
        }
        .available-badge {
            background-color: #d4edda;
            color: #155724;
        }
        .deployed-badge {
            background-color: #fe8f44ee;
            color: #856404;
        }
        .btn-disabled {
            background-color: #fd8216cb !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }
        #deployed-drones {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #000000ff;
        }
    </style>
</head>
<body>
   
    <header>
        <div class="header-content">
            <img src="images/logo.jpg" alt="Airusea Logo" class="logo">
            <nav class="navbar">
                <a href="index.php">Home</a>
                <a href="#available-drones">Available</a>
                <a href="#deployed-drones">Deployed</a>
                <a href="chest.php" class="my-rentals-btn">My Rentals</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="header-search">
            <form method="GET" action="drones.php" class="search-bar">
                <input type="text" name="query" placeholder="Search by model, price, brand..." 
                       value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>" />
                <button type="submit">Search</button>
                <?php if (!empty($_GET['query'])): ?>
                    <a href="drones.php" class="clear-search">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ✅ AVAILABLE DRONES SECTION -->
        <section id="available-drones">
            <h2>✅ Available Drones</h2>
            
            <?php if ($stmt->rowCount() === 0): ?>
                <p>No drones available<?php echo !empty($_GET['query']) ? ' matching your search.' : '.'; ?></p>
            <?php endif; ?>
            
            <div class="drones-container">
            <?php while ($drone = $stmt->fetch()): ?>
                <div class="drone">
                    <h2><?php echo htmlspecialchars($drone['Model']); ?></h2>
                    <?php if (!empty($drone['ImageURL'])): ?>
                        <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" 
                             alt="<?php echo htmlspecialchars($drone['Model']); ?>"/>
                    <?php endif; ?>
                    <p><strong>Brand:</strong> <?php echo htmlspecialchars($drone['Brand']); ?></p>
                    <p><strong>Price/Day:</strong> $<?php echo number_format($drone['PricePerDay'], 2); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge available-badge">Available</span></p>
                    <a href="rent.php?DroneID=<?php echo $drone['DroneID']; ?>" class="btn btn-primary">Rent This Drone</a>
                </div>
            <?php endwhile; ?>
            </div>
        </section>

        <!-- 🚁 DEPLOYED DRONES SECTION -->
        <section id="deployed-drones">
            <h2>🚁 Currently Deployed Drones</h2>
            
            <?php if ($deployedStmt->rowCount() === 0): ?>
                <p>No drones currently deployed.</p>
            <?php endif; ?>

            <div class="drones-container">
            <?php while ($drone = $deployedStmt->fetch()): ?>
                <div class="drone deployed">
                    <h2><?php echo htmlspecialchars($drone['Model']); ?></h2>
                    <?php if (!empty($drone['ImageURL'])): ?>
                        <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" 
                             alt="<?php echo htmlspecialchars($drone['Model']); ?>"/>
                    <?php endif; ?>
                    <p><strong>Brand:</strong> <?php echo htmlspecialchars($drone['Brand']); ?></p>
                    <p><strong>Price/Day:</strong> $<?php echo number_format($drone['PricePerDay'], 2); ?></p>
                    <p><strong>Rented By:</strong> <?php echo htmlspecialchars($drone['Name'] ?? $drone['Email']); ?></p>
                    <p><strong>Rent End:</strong> <?php echo date('M d, Y', strtotime($drone['RentEnd'])); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge deployed-badge">Deployed</span></p>
                    <button class="btn btn-disabled" disabled>Currently Rented</button>
                </div>
            <?php endwhile; ?>
            </div>
        </section>
    </main>
</body>
</html>