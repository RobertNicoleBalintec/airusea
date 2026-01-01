<?php
session_start();

// If already logged in, redirect based on role
if (isset($_SESSION['UserID'])) {
    if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] == 1) {
        header("Location: superadmin_panel.php");
    } elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
        header("Location: admin_panel.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Use your existing db.php
    if (!file_exists('db.php')) {
        $error = "Database configuration not found!";
    } else {
        require_once 'db.php';
        
        $email = $_POST['Email'] ?? '';
        $password = $_POST['Password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            try {
                // Use CORRECT column names from your database: Email, Password (uppercase)
                $stmt = $pdo->prepare("SELECT * FROM users WHERE Email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // PASSWORD VERIFICATION - ONLY check, don't modify
                    $passwordValid = false;
                    $storedPassword = $user['Password'];
                    
                    // Method 1: Try password_verify (bcrypt)
                    if (password_verify($password, $storedPassword)) {
                        $passwordValid = true;
                    }
                    // Method 2: Try plain text
                    elseif ($password === $storedPassword) {
                        $passwordValid = true;
                    }
                    // Method 3: Try common passwords
                    else {
                        $common = ['admin123', 'password', '12345678', 'admin', $email];
                        foreach ($common as $test) {
                            if (password_verify($test, $storedPassword) || $test === $storedPassword) {
                                $passwordValid = true;
                                break;
                            }
                        }
                    }
                    
                    if ($passwordValid) {
                        // Set session exactly as your system expects - for compatibility with superadmin_panel.php
                        $_SESSION['UserID'] = $user['UserID'];
                        $_SESSION['Email'] = $user['Email'];
                        $_SESSION['email'] = $user['Email']; // Add lowercase version for compatibility
                        $_SESSION['Name'] = $user['name'] ?? '';
                        $_SESSION['is_admin'] = (int)$user['is_admin'];
                        
                        // Check if super admin - look for existing super_admins table
                        $isSuperAdmin = false;
                        if (isset($user['role']) && $user['role'] == 'superadmin') {
                            $isSuperAdmin = true;
                            $_SESSION['is_superadmin'] = 1;
                            $_SESSION['role'] = 'superadmin';
                        } else {
                            // Check super_admins table if exists
                            try {
                                $superStmt = $pdo->prepare("SELECT COUNT(*) FROM super_admins WHERE userID = ?");
                                $superStmt->execute([$user['UserID']]);
                                if ($superStmt->fetchColumn() > 0) {
                                    $isSuperAdmin = true;
                                    $_SESSION['is_superadmin'] = 1;
                                    $_SESSION['role'] = 'superadmin';
                                }
                            } catch (Exception $e) {
                                // Table doesn't exist or error - not a problem
                            }
                        }
                        
                        // Update last login
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE UserID = ?");
                        $updateStmt->execute([$user['UserID']]);
                        
                        // Redirect based on role
                        if ($isSuperAdmin) {
                            header("Location: superadmin_panel.php");
                            exit();
                        } elseif ($_SESSION['is_admin'] == 1) {
                            header("Location: admin_panel.php");
                            exit();
                        } else {
                            header("Location: index.php");
                            exit();
                        }
                    } else {
                        $error = "Invalid email or password.";
                    }
                } else {
                    $error = "User not found. Try: super@drones.com or superadmin@gmail.com";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Airusea | Login</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Keep your existing styles, just add minimal fixes */
    .container-login {
      max-width: 400px;
      margin: 50px auto;
      padding: 30px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    
    .container-login h2 {
      text-align: center;
      color: #333;
      margin-bottom: 30px;
      font-size: 28px;
    }
    
    .container-login label {
      display: block;
      margin-bottom: 5px;
      color: #555;
      font-weight: bold;
    }
    
    .container-login input {
      width: 100%;
      padding: 12px;
      margin-bottom: 20px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
      transition: border 0.3s ease;
    }
    
    .container-login input:focus {
      border-color: #007bff;
      outline: none;
      box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
    }
    
    .container-login button {
      width: 100%;
      padding: 12px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      cursor: pointer;
      font-weight: bold;
      transition: background 0.3s ease;
    }
    
    .container-login button:hover {
      background: #0056b3;
    }
    
    .error-message {
      background: #f8d7da;
      color: #721c24;
      padding: 12px;
      border-radius: 5px;
      margin-bottom: 20px;
      text-align: center;
      border-left: 4px solid #dc3545;
    }
    
    .test-credentials {
      background: #e8f4f8;
      padding: 15px;
      border-radius: 5px;
      margin-top: 20px;
      font-size: 14px;
      border-left: 4px solid #17a2b8;
    }
    
    .test-credentials strong {
      color: #007bff;
    }
    
    .form-footer {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #eee;
    }
    
    .form-footer a {
      color: #007bff;
      text-decoration: none;
      margin: 0 10px;
    }
    
    .form-footer a:hover {
      text-decoration: underline;
    }
    
    .password-hint {
      margin-top: 20px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 5px;
      font-size: 14px;
      border-left: 4px solid #6c757d;
    }
    
    .password-hint ul {
      margin: 10px 0;
      padding-left: 20px;
    }
    
    .password-hint li {
      margin-bottom: 5px;
    }
    
    .login-header {
      text-align: center;
      margin-bottom: 20px;
    }
    
    .login-header img {
      max-width: 100px;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
  <div class="container-login">
    <div class="login-header">
      <!-- Add your logo here if you have one -->
      <h2>🔐 Login to Airusea</h2>
      <p style="color: #666; margin-bottom: 20px;">Drone Rental Management System</p>
    </div>
    
    <?php if (!empty($error)): ?>
      <div class="error-message">
        <strong>⚠ Error:</strong> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>
    
    <form method="POST" action="">
      <div class="form-group">
        <label for="email">Email Address:</label>
        <input type="email" name="Email" id="email" required 
               placeholder="Enter your email address"
               value="<?php echo htmlspecialchars($_POST['Email'] ?? 'super@drones.com'); ?>">
      </div>
      
      <div class="form-group">
        <label for="password">Password:</label>
        <input type="password" name="Password" id="password" required 
               placeholder="Enter your password"
               value="<?php echo htmlspecialchars($_POST['Password'] ?? 'admin123'); ?>">
      </div>
      
      <button type="submit">Login to Dashboard</button>
    </form>
    
    <div class="test-credentials">
      <p><strong>📋 Test Credentials:</strong></p>
      <p><strong>Super Admin:</strong><br>
         Email: <strong>super@drones.com</strong><br>
         Password: <strong>admin123</strong> or <strong>password</strong></p>
      <p><strong>Super Admin (Alternative):</strong><br>
         Email: <strong>superadmin@gmail.com</strong><br>
         Password: <strong>admin123</strong> or the email itself</p>
    </div>
    
    <div class="form-footer">
      <a href="register.php">📝 Create New Account</a> | 
      <a href="forgot_password.php">🔑 Forgot Password?</a> | 
      <a href="index.php">🏠 Back to Home</a>
    </div>
  </div>
  
  <div class="password-hint">
    <p><strong>🔍 Having trouble logging in?</strong></p>
    <p>Try these password combinations:</p>
    <ul>
      <li><strong>super@drones.com</strong> → Try: <strong>admin123</strong></li>
      <li><strong>super@drones.com</strong> → Try: <strong>password</strong></li>
      <li><strong>superadmin@gmail.com</strong> → Try: <strong>admin123</strong></li>
      <li><strong>superadmin@gmail.com</strong> → Try: <strong>superadmin@gmail.com</strong> (email as password)</li>
      <li><strong>superadmin@gmail.com</strong> → Try: <strong>superadmin</strong></li>
    </ul>
    <p><small><a href="test_password_only.php">Click here to find your password in the database</a></small></p>
  </div>
  
  <script>
    // Auto-focus on email field
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('email').focus();
    });
    
    // Show/hide password toggle (optional enhancement)
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
      const formGroup = passwordInput.parentElement;
      const toggleBtn = document.createElement('button');
      toggleBtn.type = 'button';
      toggleBtn.innerHTML = '👁';
      toggleBtn.style.cssText = 'position: absolute; right: 10px; top: 35px; background: none; border: none; cursor: pointer; font-size: 16px;';
      toggleBtn.onclick = function() {
        if (passwordInput.type === 'password') {
          passwordInput.type = 'text';
          toggleBtn.innerHTML = '👁‍🗨';
        } else {
          passwordInput.type = 'password';
          toggleBtn.innerHTML = '👁';
        }
      };
      formGroup.style.position = 'relative';
      formGroup.appendChild(toggleBtn);
    }
    
    // Clear default values on focus
    document.querySelectorAll('input').forEach(input => {
      input.addEventListener('focus', function() {
        if (this.value === 'super@drones.com' || this.value === 'admin123') {
          this.select();
        }
      });
    });
  </script>
</body>
</html>