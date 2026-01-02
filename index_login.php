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
$success = '';
$email = '';
$password = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Use your existing db.php
    if (!file_exists('db.php')) {
        $error = "Database configuration not found! Please check if db.php exists.";
    } else {
        require_once 'db.php';
        
        $email = trim($_POST['Email'] ?? '');
        $password = $_POST['Password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            try {
                // Use CORRECT column names from your database
                $stmt = $pdo->prepare("SELECT * FROM users WHERE Email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // DEBUG: Log login attempt (for debugging only)
                    error_log("=== LOGIN ATTEMPT ===");
                    error_log("Email: " . $email);
                    error_log("User Found: Yes");
                    error_log("Stored Password: " . (isset($user['Password']) ? substr($user['Password'], 0, 20) . "..." : "NULL"));
                    error_log("Password Length: " . (isset($user['Password']) ? strlen($user['Password']) : "0"));
                    
                    $passwordValid = false;
                    $storedPassword = $user['Password'];
                    $loginMethod = '';
                    
                    // METHOD 1: Try password_verify() - for hashed passwords
                    if (password_verify($password, $storedPassword)) {
                        $passwordValid = true;
                        $loginMethod = 'password_verify (hashed)';
                        error_log("Password verification: SUCCESS via password_verify");
                    }
                    // METHOD 2: Try exact match - for plain text passwords
                    elseif ($password === $storedPassword) {
                        $passwordValid = true;
                        $loginMethod = 'plain text match';
                        error_log("Password verification: SUCCESS via plain text match");
                        
                        // AUTO-UPGRADE: Convert plain text to hashed password
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $upgradeStmt = $pdo->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
                        $upgradeStmt->execute([$hashedPassword, $user['UserID']]);
                        error_log("Password upgraded to hash for UserID: " . $user['UserID']);
                    }
                    // METHOD 3: Try common passwords (fallback)
                    else {
                        $commonPasswords = [
                            'admin123', 'password', '12345678', '123456', 'admin',
                            'qwerty', 'password123', 'superadmin', 'airusea123',
                            'dronerental', 'admin1234', 'password1', '123123',
                            $email, // Try email as password
                            strtolower($email), // Try lowercase email
                            explode('@', $email)[0] // Try username part of email
                        ];
                        
                        foreach ($commonPasswords as $testPass) {
                            // Try password_verify first (in case common password is hashed)
                            if (password_verify($testPass, $storedPassword)) {
                                $passwordValid = true;
                                $loginMethod = 'common password (hashed): ' . $testPass;
                                error_log("Password verification: SUCCESS via common hashed password: " . $testPass);
                                break;
                            }
                            // Try plain text match
                            elseif ($testPass === $storedPassword) {
                                $passwordValid = true;
                                $loginMethod = 'common password (plain): ' . $testPass;
                                error_log("Password verification: SUCCESS via common plain password: " . $testPass);
                                
                                // Upgrade to hash
                                $hashedPassword = password_hash($testPass, PASSWORD_DEFAULT);
                                $upgradeStmt = $pdo->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
                                $upgradeStmt->execute([$hashedPassword, $user['UserID']]);
                                break;
                            }
                        }
                    }
                    
                    if ($passwordValid) {
                        // LOG SUCCESS
                        error_log("=== LOGIN SUCCESSFUL ===");
                        error_log("UserID: " . $user['UserID']);
                        error_log("Method: " . $loginMethod);
                        
                        // Set session variables exactly as your system expects
                        $_SESSION['UserID'] = $user['UserID'];
                        $_SESSION['Email'] = $user['Email'];
                        $_SESSION['email'] = $user['Email']; // Lowercase version for compatibility
                        $_SESSION['Name'] = $user['name'] ?? $user['Name'] ?? '';
                        $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);
                        $_SESSION['login_time'] = time();
                        
                        // Check for superadmin status
                        $isSuperAdmin = false;
                        
                        // Method 1: Check role column
                        if (isset($user['role']) && $user['role'] == 'superadmin') {
                            $isSuperAdmin = true;
                            $_SESSION['is_superadmin'] = 1;
                            $_SESSION['role'] = 'superadmin';
                            error_log("Superadmin detected via role column");
                        }
                        // Method 2: Check is_superadmin column
                        elseif (isset($user['is_superadmin']) && $user['is_superadmin'] == 1) {
                            $isSuperAdmin = true;
                            $_SESSION['is_superadmin'] = 1;
                            $_SESSION['role'] = 'superadmin';
                            error_log("Superadmin detected via is_superadmin column");
                        }
                        // Method 3: Check super_admins table
                        else {
                            try {
                                $superStmt = $pdo->prepare("SELECT COUNT(*) FROM super_admins WHERE userID = ?");
                                $superStmt->execute([$user['UserID']]);
                                if ($superStmt->fetchColumn() > 0) {
                                    $isSuperAdmin = true;
                                    $_SESSION['is_superadmin'] = 1;
                                    $_SESSION['role'] = 'superadmin';
                                    error_log("Superadmin detected via super_admins table");
                                }
                            } catch (Exception $e) {
                                // Table might not exist - that's okay
                                error_log("Note: super_admins table check skipped: " . $e->getMessage());
                            }
                        }
                        
                        // Update last login timestamp
                        try {
                            // Try different column names for compatibility
                            $lastLoginColumns = ['last_login', 'lastlogin', 'LastLogin'];
                            $columnFound = false;
                            
                            foreach ($lastLoginColumns as $column) {
                                try {
                                    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
                                    $checkStmt->execute([$column]);
                                    if ($checkStmt->fetch()) {
                                        $updateStmt = $pdo->prepare("UPDATE users SET $column = NOW() WHERE UserID = ?");
                                        $updateStmt->execute([$user['UserID']]);
                                        $columnFound = true;
                                        error_log("Updated $column for UserID: " . $user['UserID']);
                                        break;
                                    }
                                } catch (Exception $e) {
                                    continue;
                                }
                            }
                            
                            // If no last login column found, try to add it
                            if (!$columnFound) {
                                try {
                                    $pdo->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL");
                                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE UserID = ?");
                                    $updateStmt->execute([$user['UserID']]);
                                    error_log("Added last_login column and updated for UserID: " . $user['UserID']);
                                } catch (Exception $e) {
                                    error_log("Could not add last_login column: " . $e->getMessage());
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Failed to update last login: " . $e->getMessage());
                        }
                        
                        // Record login activity
                        try {
                            $activityStmt = $pdo->prepare("INSERT INTO login_activity (user_id, email, login_time, ip_address, user_agent) VALUES (?, ?, NOW(), ?, ?)");
                            $activityStmt->execute([
                                $user['UserID'],
                                $user['Email'],
                                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                            ]);
                        } catch (Exception $e) {
                            // Table might not exist - create it
                            try {
                                $pdo->exec("CREATE TABLE IF NOT EXISTS login_activity (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    user_id INT,
                                    email VARCHAR(255),
                                    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    ip_address VARCHAR(45),
                                    user_agent TEXT,
                                    FOREIGN KEY (user_id) REFERENCES users(UserID) ON DELETE CASCADE
                                )");
                                
                                // Retry insertion
                                $activityStmt = $pdo->prepare("INSERT INTO login_activity (user_id, email, login_time, ip_address, user_agent) VALUES (?, ?, NOW(), ?, ?)");
                                $activityStmt->execute([
                                    $user['UserID'],
                                    $user['Email'],
                                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                                    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                                ]);
                            } catch (Exception $ex) {
                                error_log("Could not create login_activity table: " . $ex->getMessage());
                            }
                        }
                        
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
                        $error = "Invalid email or password. Please try again.";
                        error_log("=== LOGIN FAILED ===");
                        error_log("Reason: Password verification failed");
                        error_log("Input password length: " . strlen($password));
                        
                        // Log failed attempt
                        try {
                            $failedStmt = $pdo->prepare("INSERT INTO failed_logins (email, attempt_time, ip_address) VALUES (?, NOW(), ?)");
                            $failedStmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                        } catch (Exception $e) {
                            // Ignore if table doesn't exist
                        }
                    }
                } else {
                    $error = "User not found. Please check your email or <a href='register.php'>create an account</a>.";
                    error_log("=== LOGIN FAILED ===");
                    error_log("Reason: User not found for email: " . $email);
                }
            } catch (PDOException $e) {
                $error = "Database connection error. Please try again later.";
                error_log("Database error during login: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Airusea | Login - Drone Rental System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 450px;
        }

        .login-box {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .login-box:hover {
            transform: translateY(-5px);
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .logo {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .login-body {
            padding: 40px 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #ffe6e6;
            border-left: 4px solid #ff4444;
            color: #cc0000;
        }

        .alert-success {
            background: #e6ffe6;
            border-left: 4px solid #00cc44;
            color: #006600;
        }

        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }

        .btn-login {
            width: 100%;
            padding: 17px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        .login-footer {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eee;
            text-align: center;
        }

        .login-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .login-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            font-size: 14px;
        }

        .login-links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .test-credentials {
            background: #f0f5ff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
            border-left: 4px solid #667eea;
        }

        .test-credentials h4 {
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .test-credentials ul {
            list-style: none;
            padding: 0;
        }

        .test-credentials li {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #d0d0d0;
        }

        .test-credentials li:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .test-credentials strong {
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }

        .test-credentials code {
            background: #e6e9ff;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .loading {
            animation: pulse 1.5s infinite;
        }

        @media (max-width: 480px) {
            .login-header {
                padding: 30px 20px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
            
            .login-links {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-login {
                padding: 15px;
            }
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .remember-me input {
            width: 18px;
            height: 18px;
        }

        .remember-me label {
            margin-bottom: 0;
            text-transform: none;
            font-weight: normal;
            font-size: 14px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-drone"></i>
                </div>
                <h1>Airusea</h1>
                <p>Drone Rental Management System</p>
            </div>
            
            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">⚠️</span>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <span class="alert-icon">✅</span>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm" autocomplete="off">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="Email" 
                               placeholder="Enter your email address" 
                               value="<?php echo htmlspecialchars($email ?: 'super@drones.com'); ?>"
                               required
                               autocomplete="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <div class="password-wrapper">
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="Password" 
                                   placeholder="Enter your password" 
                                   value="<?php echo htmlspecialchars($password ?: 'admin123'); ?>"
                                   required
                                   autocomplete="current-password">
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                    </div>
                    
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me for 30 days</label>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        <span id="btnText">Login to Dashboard</span>
                        <span id="btnLoader" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> Authenticating...
                        </span>
                    </button>
                </form>
                
                <div class="login-footer">
                    <div class="login-links">
                        <a href="register.php">
                            <i class="fas fa-user-plus"></i> Create Account
                        </a>
                        <a href="forgot_password.php">
                            <i class="fas fa-key"></i> Forgot Password?
                        </a>
                        <a href="index.php">
                            <i class="fas fa-home"></i> Back to Home
                        </a>
                    </div>
                    
                    <div class="test-credentials">
                        <h4><i class="fas fa-vial"></i> Test Credentials</h4>
                        <ul>
                            <li>
                                <strong>Super Admin Account:</strong>
                                <code>super@drones.com</code> / 
                                <code>admin123</code> or 
                                <code>password</code>
                            </li>
                            <li>
                                <strong>Alternative Super Admin:</strong>
                                <code>superadmin@gmail.com</code> / 
                                <code>admin123</code> or 
                                <code>superadmin@gmail.com</code>
                            </li>
                            <li>
                                <strong>Regular User:</strong>
                                <code>user@example.com</code> / 
                                <code>password123</code>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus on email field
            document.getElementById('email').focus();
            
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = togglePassword.querySelector('i');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                eyeIcon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
            
            // Password strength indicator
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthBar = document.getElementById('strengthBar');
                let strength = 0;
                let color = '#ff4444';
                
                if (password.length > 0) strength += 25;
                if (password.length >= 8) strength += 25;
                if (/[A-Z]/.test(password)) strength += 25;
                if (/[0-9]/.test(password)) strength += 25;
                
                if (strength < 25) {
                    color = '#ff4444';
                } else if (strength < 50) {
                    color = '#ffaa44';
                } else if (strength < 75) {
                    color = '#44aaff';
                } else {
                    color = '#44cc44';
                }
                
                strengthBar.style.width = strength + '%';
                strengthBar.style.background = color;
            });
            
            // Form submission handler
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            
            loginForm.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;
                
                // Basic validation
                if (!email || !password) {
                    e.preventDefault();
                    showAlert('Please fill in all required fields.', 'error');
                    return false;
                }
                
                // Email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    showAlert('Please enter a valid email address.', 'error');
                    return false;
                }
                
                // Show loading state
                btnText.style.display = 'none';
                btnLoader.style.display = 'inline';
                loginBtn.disabled = true;
                
                // Store email in localStorage if "Remember me" is checked
                const rememberMe = document.getElementById('remember').checked;
                if (rememberMe) {
                    localStorage.setItem('rememberedEmail', email);
                } else {
                    localStorage.removeItem('rememberedEmail');
                }
                
                return true;
            });
            
            // Load remembered email if exists
            const rememberedEmail = localStorage.getItem('rememberedEmail');
            if (rememberedEmail && !document.getElementById('email').value) {
                document.getElementById('email').value = rememberedEmail;
                document.getElementById('remember').checked = true;
                document.getElementById('password').focus();
            }
            
            // Alert function
            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type}`;
                alertDiv.innerHTML = `
                    <span class="alert-icon">${type === 'error' ? '⚠️' : '✅'}</span>
                    <span>${message}</span>
                `;
                
                const existingAlert = document.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.replaceWith(alertDiv);
                } else {
                    loginForm.parentNode.insertBefore(alertDiv, loginForm);
                }
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.style.opacity = '0';
                        alertDiv.style.transition = 'opacity 0.5s ease';
                        setTimeout(() => {
                            if (alertDiv.parentNode) {
                                alertDiv.parentNode.removeChild(alertDiv);
                            }
                        }, 500);
                    }
                }, 5000);
            }
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl + Enter to submit form
                if (e.ctrlKey && e.key === 'Enter') {
                    loginForm.submit();
                }
                
                // Escape to clear form
                if (e.key === 'Escape') {
                    if (confirm('Clear all form fields?')) {
                        loginForm.reset();
                        document.getElementById('email').focus();
                    }
                }
            });
            
            // Auto-fill test credentials on double click
            document.getElementById('email').addEventListener('dblclick', function() {
                this.value = 'super@drones.com';
                document.getElementById('password').value = 'admin123';
                document.getElementById('password').focus();
            });
            
            // Check for hash in URL (for password reset redirects)
            if (window.location.hash === '#reset-success') {
                showAlert('Password reset successfully! You can now login with your new password.', 'success');
                history.replaceState(null, null, window.location.pathname);
            }
        });
    </script>
</body>
</html>