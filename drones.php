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

// Check if status column exists
$checkStatusColumn = $pdo->query("SHOW COLUMNS FROM rentals LIKE 'status'");
$hasStatusColumn = $checkStatusColumn->rowCount() > 0;

// ========== AVAILABLE DRONES ==========
// Show drones that are available AND not phased_out
$availableQuery = "
    SELECT d.* 
    FROM drones d
    WHERE d.status = 'available' 
    AND d.QuantityAvailable > 0
    AND NOT EXISTS (
        SELECT 1 FROM rentals r 
        WHERE r.DroneID = d.DroneID 
        AND r.RentEnd >= NOW()
        " . ($hasStatusColumn ? "AND (r.status IS NULL OR r.status != 'cancelled')" : "") . "
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
// Show drones that are currently rented (rent not due) AND not phased_out
if ($hasStatusColumn) {
    $deployedQuery = "
        SELECT d.*, r.RentStart, r.RentEnd, u.Email, u.Name
        FROM drones d
        JOIN rentals r ON d.DroneID = r.DroneID
        JOIN users u ON r.UserID = u.UserID
        WHERE r.RentEnd >= NOW()  -- Rent not due
        AND (r.status IS NULL OR r.status != 'cancelled')
        AND d.status = 'available'  -- Only show available drones that are deployed
        ORDER BY r.RentEnd ASC
    ";
} else {
    $deployedQuery = "
        SELECT d.*, r.RentStart, r.RentEnd, u.Email, u.Name
        FROM drones d
        JOIN rentals r ON d.DroneID = r.DroneID
        JOIN users u ON r.UserID = u.UserID
        WHERE r.RentEnd >= NOW()  -- Rent not due
        AND d.status = 'available'  -- Only show available drones that are deployed
        ORDER BY r.RentEnd ASC
    ";
}
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
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }
        .drone {
            border: 1px solid #fff9f9e2;
            padding: 15px;
            border-radius: 8px;
            width: 250px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            background-color: white;
            transition: transform 0.3s ease;
        }
        .drone:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .drone.deployed {
            background-color: #ffefdd;
            border-color: #ffd54f;
        }
        .drone img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
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
            background-color: #f87015ef;
            color: #fdb706f7;
        }
        .btn-primary {
            display: inline-block;
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            margin-top: 10px;
            text-align: center;
            width: 100%;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #2980b9;
        }
        .btn-disabled {
            background-color: #fd8216cb !important;
            cursor: not-allowed !important;
            opacity: 0.6;
            width: 100%;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            margin-top: 10px;
        }
        #deployed-drones {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #000000ff;
        }
        .header-search {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-bar input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .search-bar button {
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
        }
        .search-bar button:hover {
            background-color: #2980b9;
        }
        .clear-search {
            color: #e74c3c;
            text-decoration: none;
            padding: 10px;
        }
        .clear-search:hover {
            text-decoration: underline;
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        p {
            margin: 5px 0;
        }
        strong {
            color: #2c3e50;
        }
        
        /* Header styles */
        header {
            background-color: #2c3e50;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        .logo {
            height: 50px;
        }
        .navbar {
            display: flex;
            gap: 20px;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .navbar a:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .my-rentals-btn {
            background-color: #3498db;
        }
        .my-rentals-btn:hover {
            background-color: #2980b9;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .drones-container {
                justify-content: center;
            }
            .drone {
                width: 100%;
                max-width: 300px;
            }
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            .navbar {
                flex-wrap: wrap;
                justify-content: center;
            }
            .search-bar {
                flex-direction: column;
            }
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

    <main style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <div class="header-search">
            <form method="GET" action="drones.php" class="search-bar">
                <input type="text" name="query" placeholder="Search by model, brand, price..." 
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
                <p style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    No drones available<?php echo !empty($_GET['query']) ? ' matching your search.' : '.'; ?>
                </p>
            <?php else: ?>
                <div class="drones-container">
                <?php while ($drone = $stmt->fetch()): ?>
                    <div class="drone">
                        <h3 style="margin-top: 0; color: #2c3e50;"><?php echo htmlspecialchars($drone['Model']); ?></h3>
                        <?php if (!empty($drone['ImageURL'])): ?>
                            <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" 
                                 alt="<?php echo htmlspecialchars($drone['Model']); ?>" 
                                 onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100\" height=\"100\" viewBox=\"0 0 24 24\"><path fill=\"%23999\" d=\"M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z\"/></svg>'"/>
                        <?php else: ?>
                            <div style="height: 150px; background: #ecf0f1; display: flex; align-items: center; justify-content: center; border-radius: 5px; color: #7f8c8d;">
                                <i class="fas fa-helicopter" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        <p><strong>Brand:</strong> <?php echo htmlspecialchars($drone['Brand']); ?></p>
                        <p><strong>Price/Day:</strong> $<?php echo number_format($drone['PricePerDay'], 2); ?></p>
                        <?php if (isset($drone['QuantityAvailable'])): ?>
                            <p><strong>Available:</strong> <?php echo htmlspecialchars($drone['QuantityAvailable']); ?> unit(s)</p>
                        <?php endif; ?>
                        <p><strong>Status:</strong> <span class="status-badge available-badge">Available</span></p>
                        <a href="rent.php?DroneID=<?php echo $drone['DroneID']; ?>" class="btn-primary">Rent This Drone</a>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- 🚁 DEPLOYED DRONES SECTION -->
        <section id="deployed-drones">
            <h2>🚁 Currently Deployed Drones</h2>
            
            <?php if ($deployedStmt->rowCount() === 0): ?>
                <p style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    No drones currently deployed.
                </p>
            <?php else: ?>
                <div class="drones-container">
                <?php while ($drone = $deployedStmt->fetch()): 
                    $daysLeft = ceil((strtotime($drone['RentEnd']) - time()) / (60 * 60 * 24));
                ?>
                    <div class="drone deployed">
                        <h3 style="margin-top: 0; color: #2c3e50;"><?php echo htmlspecialchars($drone['Model']); ?></h3>
                        <?php if (!empty($drone['ImageURL'])): ?>
                            <img src="images/<?php echo htmlspecialchars($drone['ImageURL']); ?>" 
                                 alt="<?php echo htmlspecialchars($drone['Model']); ?>"
                                 onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"100\" height=\"100\" viewBox=\"0 0 24 24\"><path fill=\"%23999\" d=\"M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z\"/></svg>'"/>
                        <?php else: ?>
                            <div style="height: 150px; background: #ffefdd; display: flex; align-items: center; justify-content: center; border-radius: 5px; color: #e67e22;">
                                <i class="fas fa-helicopter" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        <p><strong>Brand:</strong> <?php echo htmlspecialchars($drone['Brand']); ?></p>
                        <p><strong>Price/Day:</strong> $<?php echo number_format($drone['PricePerDay'], 2); ?></p>
                        <p><strong>Rented By:</strong> <?php echo htmlspecialchars($drone['Name'] ?? $drone['Email']); ?></p>
                        <p><strong>Rent End:</strong> <?php echo date('M d, Y', strtotime($drone['RentEnd'])); ?></p>
                        <?php if ($daysLeft > 0): ?>
                            <p><strong>Days Left:</strong> <span style="color: #e74c3c; font-weight: bold;"><?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?></span></p>
                        <?php endif; ?>
                        <p><strong>Status:</strong> <span class="status-badge deployed-badge">Deployed</span></p>
                        <button class="btn-disabled" disabled>Currently Rented</button>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <script>
        // Add Font Awesome icons
        const faLink = document.createElement('link');
        faLink.rel = 'stylesheet';
        faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(faLink);
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Highlight current section in navigation
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.navbar a[href^="#"]');
            
            let currentSection = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (window.scrollY >= (sectionTop - 100)) {
                    currentSection = '#' + section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentSection) {
                    link.classList.add('active');
                }
            });
        });
    </script>
    
    <style>
        .navbar a.active {
            background-color: rgba(255,255,255,0.2);
            font-weight: bold;
        }
    </style>
</body>
</html>