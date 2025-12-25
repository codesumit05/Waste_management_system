<?php
require 'db.php';

$error_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM drivers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $driver = $result->fetch_assoc();
        if (password_verify($password, $driver['password'])) {
            $_SESSION['driver_id'] = $driver['id'];
            $_SESSION['driver_name'] = $driver['name'];
            header("Location: driver_dashboard.php");
            exit();
        } else {
            $error_message = "Invalid password.";
        }
    } else {
        $error_message = "No driver found with that email.";
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Driver Login - EcoWaste</title>
    <link rel="stylesheet" href="login-style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <a href="index.php" class="logo">EcoWaste Driver</a>
            <h2>Driver Login</h2>
            <?php if(!empty($error_message)): ?>
                <p style="color: red;"><?= $error_message ?></p>
            <?php endif; ?>
            <form action="driver_login.php" method="post">
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
            <div class="switch-login">
                <a href="login.php">Not a Driver? User Login</a>
            </div>
        </div>
    </div>
</body>
</html>