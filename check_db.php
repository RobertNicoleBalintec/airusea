<?php
// check_db.php - Safe diagnostic tool (READ ONLY)
session_start();

// Try to connect to database
try {
    require_once 'db.php';
    echo "<h3 style='color: green;'>✅ Database connection successful!</h3>";
} catch (Exception $e) {
    die("<h3 style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</h3>");
}

echo "<h2>🔍 Database Structure Check</h2>";
echo "<p>This page only READS from database - no changes are made.</p>";

// 1. Check rentals table structure
echo "<h3>📊 1. Rentals Table Structure</h3>";

try {
    $stmt = $pdo->query("DESCRIBE rentals");
    $columns = $stmt->fetchAll();
    
    if (empty($columns)) {
        echo "<p style='color: red;'>❌ Rentals table not found or empty!</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; background: white;'>";
        echo "<tr style='background: #3498db; color: white;'>";
        echo "<th>Column Name</th><th>Type</th><th>Can be NULL?</th><th>Default Value</th>";
        echo "</tr>";
        
        $has_status = false;
        $has_cancelledat = false;
        
        foreach ($columns as $col) {
            $bgcolor = $col['Field'] == 'Status' || $col['Field'] == 'CancelledAt' ? '#d4edda' : 'white';
            echo "<tr style='background: " . $bgcolor . ";'>";
            echo "<td><strong>" . $col['Field'] . "</strong></td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
            
            if ($col['Field'] == 'Status') $has_status = true;
            if ($col['Field'] == 'CancelledAt') $has_cancelledat = true;
        }
        echo "</table>";
        
        // Status check
        if ($has_status) {
            echo "<p style='color: green;'>✅ Status column exists</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Status column is MISSING - needed for cancellation</p>";
        }
        
        // CancelledAt check
        if ($has_cancelledat) {
            echo "<p style='color: green;'>✅ CancelledAt column exists</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ CancelledAt column is optional (for timestamp)</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error checking rentals table: " . $e->getMessage() . "</p>";
}

// 2. Check sample data
echo "<h3>📋 2. Sample Rental Data (First 3 Records)</h3>";

try {
    $rentals = $pdo->query("SELECT * FROM rentals LIMIT 3")->fetchAll();
    
    if (empty($rentals)) {
        echo "<p>No rentals found in database.</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; background: white;'>";
        
        // Header row
        echo "<tr style='background: #2ecc71; color: white;'>";
        foreach (array_keys($rentals[0]) as $key) {
            echo "<th>" . $key . "</th>";
        }
        echo "</tr>";
        
        // Data rows
        foreach ($rentals as $rental) {
            echo "<tr>";
            foreach ($rental as $key => $value) {
                $color = '';
                if ($key == 'Status') {
                    $color = $value == 'CANCELLED' ? 'red' : ($value == 'ACTIVE' ? 'green' : 'orange');
                    echo "<td style='color: " . $color . "; font-weight: bold;'>" . ($value ?? 'NULL') . "</td>";
                } else {
                    echo "<td>" . ($value ?? 'NULL') . "</td>";
                }
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error fetching sample data: " . $e->getMessage() . "</p>";
}

// 3. Check your rentals (if logged in)
if (isset($_SESSION['UserID'])) {
    echo "<h3>👤 3. Your Rentals (UserID: " . $_SESSION['UserID'] . ")</h3>";
    
    try {
        $stmt = $pdo->prepare("
            SELECT r.RentalID, r.Status, r.RentStart, r.RentEnd, d.Brand, d.Model 
            FROM rentals r 
            LEFT JOIN drones d ON r.DroneID = d.DroneID 
            WHERE r.UserID = ?
        ");
        $stmt->execute([$_SESSION['UserID']]);
        $user_rentals = $stmt->fetchAll();
        
        if (empty($user_rentals)) {
            echo "<p>You have no rentals.</p>";
            echo "<p><a href='dashboard.php'>Rent a drone to test</a></p>";
        } else {
            echo "<table border='1' cellpadding='8' style='border-collapse: collapse; background: white;'>";
            echo "<tr style='background: #9b59b6; color: white;'>";
            echo "<th>RentalID</th><th>Drone</th><th>Status</th><th>Start Date</th><th>End Date</th><th>Can Cancel?</th>";
            echo "</tr>";
            
            foreach ($user_rentals as $r) {
                $can_cancel = (!isset($r['Status']) || $r['Status'] == 'ACTIVE') && 
                              strtotime($r['RentStart']) > time();
                $status_color = $r['Status'] == 'ACTIVE' ? 'green' : 
                               ($r['Status'] == 'CANCELLED' ? 'red' : 'orange');
                
                echo "<tr>";
                echo "<td>" . $r['RentalID'] . "</td>";
                echo "<td>" . ($r['Brand'] ?? 'Unknown') . " " . ($r['Model'] ?? '') . "</td>";
                echo "<td style='color: " . $status_color . "; font-weight: bold;'>" . ($r['Status'] ?? 'NULL') . "</td>";
                echo "<td>" . $r['RentStart'] . "</td>";
                echo "<td>" . $r['RentEnd'] . "</td>";
                echo "<td style='font-weight: bold; color: " . ($can_cancel ? 'green' : 'red') . ";'>" . 
                     ($can_cancel ? 'YES' : 'NO') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<p><strong>Note:</strong> 'Can Cancel?' shows YES only if:</p>";
            echo "<ul>";
            echo "<li>Status is ACTIVE or NULL</li>";
            echo "<li>Start date is in the future</li>";
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error fetching your rentals: " . $e->getMessage() . "</p>";
    }
}

// 4. Count total rentals
try {
    $count = $pdo->query("SELECT COUNT(*) as total FROM rentals")->fetch();
    echo "<h3>📈 4. Database Statistics</h3>";
    echo "<p>Total rentals in database: <strong>" . $count['total'] . "</strong></p>";
    
    if (isset($has_status) && $has_status) {
        $status_counts = $pdo->query("SELECT Status, COUNT(*) as count FROM rentals GROUP BY Status")->fetchAll();
        echo "<p>Status breakdown:</p>";
        echo "<ul>";
        foreach ($status_counts as $stat) {
            echo "<li>" . ($stat['Status'] ?? 'NULL') . ": " . $stat['count'] . "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    // Ignore this error
}

echo "<hr>";
echo "<h3>🎯 Next Steps Based on Results:</h3>";
echo "<ol>";
echo "<li>If <strong>Status column is missing</strong>: We'll add it safely</li>";
echo "<li>If <strong>Status column exists</strong>: We'll update chest.php</li>";
echo "<li>If <strong>you have no rentals</strong>: Rent a drone first to test</li>";
echo "</ol>";

echo "<p><a href='chest.php' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Back to My Rentals</a></p>";

// Security note
echo "<p style='color: #666; font-size: 12px; margin-top: 30px;'>";
echo "⚠️ This file is for diagnostics only. Delete it after use for security.";
echo "</p>";
?>