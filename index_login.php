<?php
session_start();
require_once 'db.php';
if (isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['Email'] ?? '';
    $password = $_POST['Password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE Email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // SMART PASSWORD CHECK: Supports BOTH bcrypt AND plain text
            $passwordValid = false;
            
            // Method 1: Try bcrypt first (for new/upgraded users)
            if (password_verify($password, $user['Password'])) {
                $passwordValid = true;
                
                // If user was using plain text, upgrade to bcrypt
                if (strlen($user['Password']) < 50) { // Plain text detected
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upgradeStmt = $pdo->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
                    $upgradeStmt->execute([$newHash, $user['UserID']]);
                }
            }
            // Method 2: Try plain text (for existing users)
            elseif ($password === $user['Password']) {
                $passwordValid = true;
                
                // AUTO-UPGRADE: Convert to bcrypt for next login
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upgradeStmt = $pdo->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
                $upgradeStmt->execute([$newHash, $user['UserID']]);
            }
            
            if ($passwordValid) {
                $_SESSION['UserID'] = $user['UserID'];
                $_SESSION['Email'] = $user['Email'];
                $_SESSION['is_admin'] = (int)$user['is_admin']; 

                require_once 'logger.php';
                logEvent("User logged in: {$user['Email']}");

                if ($_SESSION['is_admin']) {
                    header("Location: admin_panel.php");
                } else {
                    header("Location: drones.php");
                }
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please enter both email and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Airusea | Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container-login">
    <h2>Login</h2>
    <?php if (!empty($error)): ?>
      <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="POST" action="index_login.php">
      <label>Email:</label>
      <input type="email" name="Email" required>
      <label>Password:</label>
      <input type="password" name="Password" required>
      <button type="submit">Login</button>
      <p>Don't have an account yet? <a href="register.php">Sign Up</a></p>
    </form>
    <p><a href="index.php">← Back to Home</a></p>
  </div>
</body>
</html>