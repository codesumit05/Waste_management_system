<?php
require 'db.php'; // Include the database connection

$error_message = '';
// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Prepare and execute the query
    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        // Verify the password
        if (password_verify($password, $user['password'])) {
            // Password is correct, start the session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: dashboard.php"); // Redirect to the dashboard
            exit();
        } else {
            $error_message = "Invalid password.";
        }
    } else {
        $error_message = "No user found with that email.";
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
    <title>Login - EcoWaste Solutions</title>
    <link rel="stylesheet" href="login-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <a href="index.php" class="logo">EcoWaste</a>
            <h2>Welcome Back!</h2>
            
            <?php if(!empty($error_message)): ?>
                <p style="color: red;"><?= $error_message ?></p>
            <?php endif; ?>

            <form action="login.php" method="post">
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-login">Login</button>
            </form>
            <div class="links">
                <a href="#">Forgot Password?</a>
                <a href="register.php">Create an Account</a>
            </div>

            <div class="switch-login">
                <a href="driver_login.php">Are you a Driver? Login Here</a>
            </div>
        </div>
    </div>
</body>
</html>