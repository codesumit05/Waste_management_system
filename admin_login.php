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
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - EcoWaste</title>
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
            background: linear-gradient(135deg, var(--accent), #d97706);
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
            border-color: var(--accent);
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.2);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--accent), #d97706);
        }
        
        .btn-login:hover {
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
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
        
        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.1));
            border: 1px solid var(--accent);
            color: var(--accent);
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
            <a href="index.php" class="logo">EcoWaste Admin</a>
            <div class="admin-badge">🔐 Administrator Access</div>
            <h2>Administrator Login</h2>
            
            <?php if(!empty($error_message)): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
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
    
    <script src="theme.js"></script>
</body>
</html>