<?php
session_start();

include('db.php');
include('logger.php');
include('auth.php');

logEvent("Accessed the main page.");

// FIXED: Using correct column names from your database
$stmt = $pdo->query("
    SELECT d.*, COUNT(r.rentID) AS rent_count
    FROM drones d
    LEFT JOIN rentals r ON d.DroneID = r.droneID
    GROUP BY d.DroneID
    ORDER BY d.DroneID DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AirErusea | Drone Rentals</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Orange color matching your logo */
        .btn-orange {
            background-color: #FF8C00 !important; /* Orange color */
            color: white !important;
            border: 2px solid #E67E22 !important;
            transition: all 0.3s ease !important;
        }
        
        .btn-orange:hover {
            background-color: #E67E22 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(230, 126, 34, 0.3);
        }
        
        /* Add styles for drone details modal */
        .drone-details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
            color: #333;
        }
        
        .drone-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .drone-details-image {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .drone-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .spec-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #FF8C00; /* Orange border */
        }
        
        .spec-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .spec-value {
            font-weight: bold;
            color: #333;
        }
        
        /* Modal orange button */
        .modal-orange-btn {
            background-color: #FF8C00;
            color: white;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            border: 2px solid #E67E22;
            transition: all 0.3s;
        }
        
        .modal-orange-btn:hover {
            background-color: #E67E22;
            color: white;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <img src="images/logo.jpg" alt="Airusea Logo" class="logo">
            <nav class="navbar">
                <a href="index.php">Home</a>
                <?php if (!isAdmin()): ?>
                    <a href="drones.php">Rent A Drone</a>
                <?php endif; ?>

                <?php if (isset($_SESSION['UserID'])): ?>
                    <?php if (!isAdmin()): ?>
                        <a href="chest.php">My Rentals</a>
                    <?php endif; ?>
                    <?php if (isAdmin()): ?>
                        <a href="admin_panel.php">Admin Panel</a>
                    <?php endif; ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="index_login.php">Login</a>
                    <a href="register.php">Sign Up</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <section class="home-page">
        <h1>AIR</h1>
        <h1>ERUSEA</h1>
        <p style="line-height: 0px; font-size: 20px;">"A site to see the world from above."</p>
    </section>
    
    <!-- Drone Collection Section -->
    <section class="drones-section">
        <h2 class="section-title">Our Drone Collection</h2>
        <div class="drones-container">
            <?php
            if ($stmt->rowCount() > 0) {
                while ($drone = $stmt->fetch()) {
                    $imageUrl = !empty($drone['ImageURL']) ? "images/" . $drone['ImageURL'] : 'images/default_image.jpg';
                    echo '<div class="drone-card">';
                    echo '<img src="' . $imageUrl . '" alt="Drone Image" class="drone-image">';
                    echo '<h3>' . htmlspecialchars($drone['Brand']) . ' ' . htmlspecialchars($drone['Model']) . '</h3>';
                    echo '<p>Price/Day: ₱' . number_format($drone['PricePerDay'], 2) . '</p>';
                    
                    // Show different button based on user type
                    if (isAdmin()) {
                        // Admins see "View Details" button with ORANGE color
                        echo '<button class="btn btn-orange view-details-btn" 
                              data-drone-id="' . $drone['DroneID'] . '" 
                              data-brand="' . htmlspecialchars($drone['Brand']) . '" 
                              data-model="' . htmlspecialchars($drone['Model']) . '" 
                              data-price="₱' . number_format($drone['PricePerDay'], 2) . '" 
                              data-image="' . $imageUrl . '" 
                              data-description="' . htmlspecialchars($drone['Description'] ?? 'No description available.') . '">
                              View Details
                              </button>';
                    } else {
                        // Regular users see the regular rent button (keeping original color)
                        echo '<a href="rent.php?DroneID=' . $drone['DroneID'] . '" class="btn">Rent This Drone</a>';
                    }
                    
                    echo '</div>';
                }
            } else {
                echo "<p>No drones available at the moment.</p>";
            }
            ?>
        </div>
    </section>
    
    <!-- Drone Details Modal (Hidden by default) -->
    <div id="droneDetailsModal" class="drone-details-modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">&times;</button>
            <h2 id="modalDroneTitle"></h2>
            
            <div class="drone-details-grid">
                <div>
                    <img id="modalDroneImage" src="" alt="Drone Image" class="drone-details-image">
                    <div style="margin-top: 20px;">
                        <h3 style="color: #FF8C00; font-size: 1.5rem;"> <!-- Orange color -->
                            <span id="modalDronePrice"></span> per day
                        </h3>
                    </div>
                </div>
                
                <div>
                    <h3>Description</h3>
                    <p id="modalDroneDescription"></p>
                    
                    <div class="drone-specs" id="modalDroneSpecs">
                        <!-- Specifications will be loaded via JavaScript -->
                    </div>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <a href="#" id="modalManageDrone" class="modal-orange-btn">
                            Manage in Admin Panel
                        </a>
                        <button onclick="closeModal()" class="btn" style="margin-left: 10px;">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Get all "View Details" buttons
        const viewDetailsBtns = document.querySelectorAll('.view-details-btn');
        const modal = document.getElementById('droneDetailsModal');
        
        // Function to open modal with drone details
        function openModal(droneData) {
            document.getElementById('modalDroneTitle').textContent = droneData.brand + ' ' + droneData.model;
            document.getElementById('modalDroneImage').src = droneData.image;
            document.getElementById('modalDroneImage').alt = droneData.brand + ' ' + droneData.model;
            document.getElementById('modalDronePrice').textContent = droneData.price;
            document.getElementById('modalDroneDescription').textContent = droneData.description;
            
            // Set manage link
            document.getElementById('modalManageDrone').href = 'admin_panel.php';
            
            // Display specifications
            document.getElementById('modalDroneSpecs').innerHTML = `
                <div class="spec-item">
                    <div class="spec-label">Brand</div>
                    <div class="spec-value">${droneData.brand}</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Model</div>
                    <div class="spec-value">${droneData.model}</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Daily Rate</div>
                    <div class="spec-value">${droneData.price}</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Status</div>
                    <div class="spec-value">Available in Inventory</div>
                </div>
            `;
            
            modal.style.display = 'flex';
        }
        
        // Function to close modal
        function closeModal() {
            modal.style.display = 'none';
        }
        
        // Add click event to all "View Details" buttons
        viewDetailsBtns.forEach(button => {
            button.addEventListener('click', function() {
                const droneData = {
                    droneId: this.getAttribute('data-drone-id'),
                    brand: this.getAttribute('data-brand'),
                    model: this.getAttribute('data-model'),
                    price: this.getAttribute('data-price'),
                    image: this.getAttribute('data-image'),
                    description: this.getAttribute('data-description')
                };
                openModal(droneData);
            });
        });
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>