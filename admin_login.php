<?php
require 'db.php'; // Includes session_start()

$error_message = '';
// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Prepare and execute the query to find an admin user
    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ? AND is_admin = TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();
        // Verify the password
        if (password_verify($password, $admin['password'])) {
            // Password is correct, start the session for the admin
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            header("Location: admin_dashboard.php"); // Redirect to the admin dashboard
            exit();
        } else {
            $error_message = "Invalid credentials for admin.";
        }
    } else {
        $error_message = "Admin account not found.";
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
    <title>Admin Login - EcoWaste</title>
    <!-- We can reuse the login stylesheet -->
    <link rel="stylesheet" href="login-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <a href="index.php" class="logo">EcoWaste Admin</a>
            <h2>Administrator Login</h2>
            
            <?php if(!empty($error_message)): ?>
                <p style="color: red;"><?= $error_message ?></p>
            <?php endif; ?>

            <form action="admin_login.php" method="post">
                <div class="input-group">
                    <label for="email">Admin Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-login">Login as Admin</button>
            </form>
        </div>
    </div>
</body>
</html>