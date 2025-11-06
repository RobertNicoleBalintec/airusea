<?php
session_start();
require_once 'db.php'; // Make sure this connects to your 'airerusea' DB

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['Email'] ?? '';
    $password = $_POST['Password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE Email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['Password']) {
          $_SESSION['UserID'] = $user['UserID'];
          $_SESSION['Email'] = $user['Email'];
          $_SESSION['is_admin'] = strpos($user['Email'], 'airerusea@gmail.com') === 0;
          
          // Optional logger
          require_once 'logger.php';
          logEvent("User logged in: {$user['Email']}");
          
          // Redirect based on role
          if ($_SESSION['is_admin']) {
              header("Location: admin_panel.php"); // Adjust the filename as needed
          } else {
              header("Location: dashboard.php");
          }
          exit();
          
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
  <div class="container">
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
    </form>
    <p><a href="index.php">← Back to Home</a></p>
  </div>
</body>
</html>
