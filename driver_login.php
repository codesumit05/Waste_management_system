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
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Login - EcoWaste</title>
    <link rel="stylesheet" href="login-style.css?v=2">
    <link rel="stylesheet" href="theme.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .theme-toggle-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .login-container {
            background: var(--bg-primary);
            transition: background 0.3s ease;
        }
        
        .login-box {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            box-shadow: 0 25px 60px var(--shadow-color);
        }
        
        .login-box .logo {
            background: linear-gradient(135deg, var(--success), #059669);
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
            border-color: var(--success);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--success), #059669);
        }
        
        .btn-login:hover {
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }
        
        .switch-login a {
            color: var(--text-secondary);
            border-top: 1px solid var(--border-color);
        }
        
        .switch-login a:hover {
            color: var(--success);
            background: rgba(16, 185, 129, 0.05);
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
        
        .driver-badge {
            display: inline-block;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1));
            border: 1px solid var(--success);
            color: var(--success);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="theme-toggle-container">
        <button id="theme-toggle" class="theme-toggle" aria-label="Toggle theme"></button>
    </div>
    
    <div class="login-container">
        <div class="login-box">
            <a href="index.php" class="logo">EcoWaste Driver</a>
            <div class="driver-badge">🚛 Driver Portal</div>
            <h2>Driver Login</h2>
            
            <?php if(!empty($error_message)): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
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
    
    <script src="theme.js"></script>
</body>
</html>