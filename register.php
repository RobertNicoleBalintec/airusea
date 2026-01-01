<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $want_to_be_owner = isset($_POST['want_to_be_owner']) ? 1 : 0;

    // VALIDATION (from your flowchart)
    $errors = [];

    // 1. Check name
    if (empty($name)) {
        $errors[] = "Name is required";
    }

    // 2. Check email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already registered";
        }
    }

    // 3. Check phone (11-digit starting with 09 - from your flowchart)
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^09[0-9]{9}$/', $phone)) {
        $errors[] = "Phone must be 11 digits starting with 09";
    } else {
        // Check if phone exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Phone number already registered";
        }
    }

    // 4. Check password (≥ 8 chars - from your flowchart)
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }

    // 5. Check password confirmation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // 6. Check address (from your flowchart)
    if (empty($address)) {
        $errors[] = "Address is required";
    }

    // If no errors, create user
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Determine role
            $role = 'regular';
            $roleID = 1;
            
            // If user wants to be owner, they need super-admin approval
            if ($want_to_be_owner) {
                $role = 'owner_pending'; // Special status
                $roleID = 2; // Pending owner
            }

            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password, address, role, roleID, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $stmt->execute([$name, $email, $phone, $hashed_password, $address, $role, $roleID]);
            $userID = $pdo->lastInsertId();

            // If user wants to be owner, create owner request
            if ($want_to_be_owner) {
                $stmt = $pdo->prepare("
                    INSERT INTO owner_requests (userID, status, requested_at) 
                    VALUES (?, 'pending', NOW())
                ");
                $stmt->execute([$userID]);
                
                $success = "Registration successful! Your owner request has been submitted for super-admin approval.";
            } else {
                $success = "Registration successful! You can now login.";
            }

            // Log the event
            require_once 'logger.php';
            logEvent("New user registered: $email");

            // Clear form
            $name = $email = $phone = $address = '';
            $want_to_be_owner = 0;

        } catch (PDOException $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up | Airusea Drone Rental</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container-register {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        textarea {
            height: 80px;
            resize: vertical;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        .btn-submit {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn-submit:hover {
            background: #0056b3;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container-register">
        <h2>Create Your Account</h2>
        <p>Join Airusea Drone Rental System</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <p><a href="index_login.php">Click here to login</a></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Phone Number *</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" 
                       placeholder="09171234567" pattern="09[0-9]{9}" required>
                <small>Must be 11 digits starting with 09</small>
            </div>

            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" minlength="8" required>
                <small>Minimum 8 characters</small>
            </div>

            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label>Address *</label>
                <textarea name="address" required><?php echo htmlspecialchars($address ?? ''); ?></textarea>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" name="want_to_be_owner" id="want_to_be_owner" value="1"
                    <?php echo (isset($want_to_be_owner) && $want_to_be_owner) ? 'checked' : ''; ?>>
                <label for="want_to_be_owner">
                    <strong>I want to become a Drone Owner</strong><br>
                    <small>Check this if you have drones to rent out. Requires super-admin approval.</small>
                </label>
            </div>

            <button type="submit" class="btn-submit">Create Account</button>
        </form>

        <div class="login-link">
            <p>Already have an account? <a href="index_login.php">Login here</a></p>
            <p><a href="index.php">← Back to Home</a></p>
        </div>
    </div>

    <script>
        // Real-time validation
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });

        // Password strength check
        document.querySelector('input[name="password"]').addEventListener('input', function(e) {
            const password = this.value;
            const strengthText = document.getElementById('password-strength') || 
                                 (function() {
                                    const el = document.createElement('small');
                                    el.id = 'password-strength';
                                    el.style.display = 'block';
                                    el.style.marginTop = '5px';
                                    this.parentNode.appendChild(el);
                                    return el;
                                 }).call(this);
            
            if (password.length === 0) {
                strengthText.textContent = '';
            } else if (password.length < 8) {
                strengthText.textContent = '❌ Too short (min 8 characters)';
                strengthText.style.color = 'red';
            } else if (password.length >= 8) {
                strengthText.textContent = '✅ Good length';
                strengthText.style.color = 'green';
            }
        });
    </script>
</body>
</html>