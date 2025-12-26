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
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EcoWaste Solutions</title>
    <link rel="stylesheet" href="login-style.css">
    <link rel="stylesheet" href="theme.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .theme-toggle-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .login-container {
        
            transition: background 0.3s ease;
        }
        
        .login-box {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            box-shadow: 0 25px 60px var(--shadow-color);
        }
        
        .login-box .logo {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-box h2 {
            color: var(--text-primary);
        }
        
        .login-box p {
            color: var(--text-secondary);
        }
        
        .input-group label {
            color: var(--text-primary);
        }
        
        .input-group input {
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 20px rgba(14, 165, 233, 0.2);
        }
        
        .links a {
            color: var(--text-secondary);
        }
        
        .links a:hover {
            color: var(--primary);
        }
        
        .switch-login a {
            color: var(--text-secondary);
            border-top: 1px solid var(--border-color);
        }
        
        .switch-login a:hover {
            color: var(--primary);
            background: rgba(14, 165, 233, 0.05);
        }
        
        .error-message {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
            border: 1px solid var(--danger);
            color: var(--danger);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="theme-toggle-container">
        <button id="theme-toggle" class="theme-toggle" aria-label="Toggle theme"></button>
    </div>
    
    <div class="login-container">
        <div class="login-box">
            <a href="index.php" class="logo">EcoWaste</a>
            <h2>Welcome Back!</h2>
            
            <?php if(!empty($error_message)): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
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
    
    <script src="theme.js"></script>
</body>
</html>