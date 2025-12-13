warning: in the working copy of 'log.txt', LF will be replaced by CRLF the next time Git touches it
[1mdiff --git a/admin_panel.php b/admin_panel.php[m
[1mindex 359767f..a84e2bc 100644[m
[1m--- a/admin_panel.php[m
[1m+++ b/admin_panel.php[m
[36m@@ -17,6 +17,52 @@[m [m$user_stmt->execute([$_SESSION['UserID']]);[m
 $user = $user_stmt->fetch();[m
 $display_name = !empty($user['Name']) ? $user['Name'] : $_SESSION['Email'];[m
 [m
[32m+[m[32m// Handle success/error messages from remove_drone.php and other actions[m
[32m+[m[32m$success_message = '';[m
[32m+[m[32m$error_message = '';[m
[32m+[m
[32m+[m[32mif (isset($_GET['success'])) {[m
[32m+[m[32m    switch ($_GET['success']) {[m
[32m+[m[32m        case 'phased_out':[m
[32m+[m[32m            $drone_id = isset($_GET['id']) ? intval($_GET['id']) : 'unknown';[m
[32m+[m[32m            $success_message = "✅ Drone #$drone_id has been successfully phased out.";[m
[32m+[m[32m            break;[m
[32m+[m[32m        case 'removed':[m
[32m+[m[32m            $success_message = "✅ Drone has been successfully removed.";[m
[32m+[m[32m            break;[m
[32m+[m[32m        case '1':[m
[32m+[m[32m            $success_message = "✅ Drone updated successfully.";[m
[32m+[m[32m            break;[m
[32m+[m[32m        case 'price_updated':[m
[32m+[m[32m            $new_id = isset($_GET['new_id']) ? intval($_GET['new_id']) : 'unknown';[m
[32m+[m[32m            $success_message = "✅ Price changed successfully. New drone #$new_id created and old drone phased out.";[m
[32m+[m[32m            break;[m
[32m+[m[32m    }[m
[32m+[m[32m}[m
[32m+[m
[32m+[m[32mif (isset($_GET['error'])) {[m
[32m+[m[32m    switch ($_GET['error']) {[m
[32m+[m[32m        case 'drone_has_active_rentals':[m
[32m+[m[32m            $error_message = "❌ Cannot phase out drone: It has active rentals.";[m
[32m+[m[32m            break;[m
[32m+[m[32m        case 'drone_already_phased_out':[m
[32m+[m[32m            $error_message = "❌ This drone is already phased out.";[m
[32m+[m[32m            break;[m
[32m+[m[32m        case 'drone_not_found':[m
[32m+[m[32m            $error_message = "❌ Drone not found.";[m
[32m+[m[32m            break;[m
[32m+[m[32m        case 'invalid_id':[m
[32m+[m[32m            $error_message = "❌ Invalid drone ID.";[m
[32m+[m[32m            break;[m
[32m+[m[32m        case 'db_error':[m
[32m+[m[32m            $error_message = "❌ Database error: " . (isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Unknown error');[m
[32m+[m[32m            break;[m
[32m+[m[32m        case 'general_error':[m
[32m+[m[32m            $error_message = "❌ Error: " . (isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Unknown error');[m
[32m+[m[32m            break;[m
[32m+[m[32m    }[m
[32m+[m[32m}[m
[32m+[m
 // Check if database tables exist[m
 try {[m
     $categories = $pdo->query("SELECT * FROM categories");[m
[36m@@ -46,16 +92,247 @@[m [mtry {[m
             padding-top: 0 !important;[m
             margin: 0 !important;[m
         }[m
[32m+[m[41m        [m
[32m+[m[32m        /* Status Badges */[m
[32m+[m[32m        .status-badge {[m
[32m+[m[32m            padding: 3px 10px;[m
[32m+[m[32m            border-radius: 12px;[m
[32m+[m[32m            font-size: 0.85em;[m
[32m+[m[32m            font-weight: bold;[m
[32m+[m[32m            display: inline-block;[m
[32m+[m[32m            min-width: 80px;[m
[32m+[m[32m            text-align: center;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .status-available {[m
[32m+[m[32m            background-color: #d4edda;[m
[32m+[m[32m            color: #155724;[m
[32m+[m[32m            border: 1px solid #c3e6cb;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .status-phased-out {[m
[32m+[m[32m            background-color: #f8d7da;[m
[32m+[m[32m            color: #721c24;[m
[32m+[m[32m            border: 1px solid #f5c6cb;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .status-unknown {[m
[32m+[m[32m            background-color: #f0f0f0;[m
[32m+[m[32m            color: #666;[m
[32m+[m[32m            border: 1px solid #ddd;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        /* Status filter buttons */[m
[32m+[m[32m        .status-filter {[m
[32m+[m[32m            margin-bottom: 20px;[m
[32m+[m[32m            padding: 15px;[m
[32m+[m[32m            background: #f8f9fa;[m
[32m+[m[32m            border-radius: 5px;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .status-filter h3 {[m
[32m+[m[32m            margin-top: 0;[m
[32m+[m[32m            margin-bottom: 10px;[m
[32m+[m[32m            font-size: 1.1em;[m
[32m+[m[32m            color: #2c3e50;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .filter-buttons {[m
[32m+[m[32m            display: flex;[m
[32m+[m[32m            gap: 10px;[m
[32m+[m[32m            flex-wrap: wrap;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .filter-btn {[m
[32m+[m[32m            padding: 8px 15px;[m
[32m+[m[32m            border-radius: 4px;[m
[32m+[m[32m            text-decoration: none;[m
[32m+[m[32m            font-size: 0.9em;[m
[32m+[m[32m            transition: all 0.3s ease;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .filter-btn:hover {[m
[32m+[m[32m            opacity: 0.9;[m
[32m+[m[32m            transform: translateY(-1px);[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .filter-all {[m
[32m+[m[32m            background: #3498db;[m
[32m+[m[32m            color: white;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .filter-available {[m
[32m+[m[32m            background: #d4edda;[m
[32m+[m[32m            color: #155724;[m
[32m+[m[32m            border: 1px solid #c3e6cb;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .filter-phased-out {[m
[32m+[m[32m            background: #f8d7da;[m
[32m+[m[32m            color: #721c24;[m
[32m+[m[32m            border: 1px solid #f5c6cb;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        /* Action Buttons */[m
[32m+[m[32m        .btn-edit {[m
[32m+[m[32m            background: #3498db;[m
[32m+[m[32m            color: white;[m
[32m+[m[32m            padding: 6px 12px;[m
[32m+[m[32m            border-radius: 4px;[m
[32m+[m[32m            text-decoration: none;[m
[32m+[m[32m            display: inline-block;[m
[32m+[m[32m            font-size: 0.9em;[m
[32m+[m[32m            border: none;[m
[32m+[m[32m            cursor: pointer;[m
[32m+[m[32m            text-align: center;[m
[32m+[m[32m            margin-top: 5px;[m
[32m+[m[32m            width: 100%;[m
[32m+[m[32m            box-sizing: border-box;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .btn-edit:hover {[m
[32m+[m[32m            background: #2980b9;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .btn-remove {[m
[32m+[m[32m            background: #e74c3c;[m
[32m+[m[32m            color: white;[m
[32m+[m[32m            padding: 6px 12px;[m
[32m+[m[32m            border-radius: 4px;[m
[32m+[m[32m            text-decoration: none;[m
[32m+[m[32m            display: inline-block;[m
[32m+[m[32m            font-size: 0.9em;[m
[32m+[m[32m            border: none;[m
[32m+[m[32m            cursor: pointer;[m
[32m+[m[32m            text-align: center;[m
[32m+[m[32m            width: 100%;[m
[32m+[m[32m            box-sizing: border-box;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        .btn-remove:hover {[m
[32m+[m[32m            background: #c0392b;[m
[32m+[m[32m        }[m
[32m+[m
[32m+[m[32m        /* Admin table improvements */[m
[32m+[m[32m        .admin-table td {[m
[32m+[m[32m            vertical-align: middle;[m
[32m+[m[3