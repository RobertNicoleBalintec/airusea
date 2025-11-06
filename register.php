<?php
// Include your database connection
include('db.php');
include('logger.php');

// Log the access (assuming logEvent is available in logger.php)
logEvent("Accessed register page.");

$error_message = "";

// Check if the form has been submitted via POST method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure name, email, and password fields are set before processing
    if (isset($_POST['name']) && isset($_POST['email']) && isset($_POST['password'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];

        // Hash the password before storing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Query to check if the email already exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        // If the email exists
        if ($stmt->rowCount() > 0) {
            $error_message = "This email is already registered.";
        } else {
            // Insert the new user into the database
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, address) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password, $phone, $address])) {
                // Registration successful, redirect to login page
                header("Location: index_login.php");
                exit();
            } else {
                $error_message = "There was an error during registration. Please try again.";
            }
        }
    } else {
        // Missing required fields
        $error_message = "Please fill out all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Airusea</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Register</h2>

        <!-- Display error message if any -->
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <label>Name:</label>
            <input type="text" name="name" required>
            <label>Email:</label>
            <input type="email" name="email" required>
            <label>Password:</label>
            <input type="password" name="password" required>
            <label>Phone:</label>
            <input type="text" name="phone">
            <label>Address:</label>
            <textarea name="address" rows="3"></textarea>
            <button type="submit">Register</button>
        </form>

        <p><a href="index_login.php">Already have an account? Login here</a></p>
    </div>
</body>
</html>
