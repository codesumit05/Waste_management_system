<?php
require 'db.php'; // Include the database connection

$message = '';
// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    // Hash the password for security
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Prepare an SQL statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $password);

    if ($stmt->execute()) {
        $message = "Registration successful! You can now <a href='login.php'>login</a>.";
    } else {
        // Check if it's a duplicate email error
        if ($conn->errno == 1062) {
            $message = "Error: This email is already registered.";
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - EcoWaste Solutions</title>
    <link rel="stylesheet" href="login-style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <a href="index.php" class="logo">EcoWaste</a>
            <h2>Create an Account</h2>
            <p>Join us to manage your waste efficiently.</p>
            
            <?php if(!empty($message)): ?>
                <p style="color: green;"><?= $message ?></p>
            <?php endif; ?>

            <form action="register.php" method="post">
                <div class="input-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-login">Register</button>
            </form>
            <div class="links">
                <a href="login.php">Already have an account? Login</a>
            </div>
        </div>
    </div>
</body>
</html>