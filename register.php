<?php
require 'db.php'; // Include the database connection

$message = '';
$message_type = '';

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
        $message = "Registration successful! You can now <a href='login.php' style='color: var(--primary); text-decoration: underline;'>login</a>.";
        $message_type = 'success';
    } else {
        // Check if it's a duplicate email error
        if ($conn->errno == 1062) {
            $message = "Error: This email is already registered.";
            $message_type = 'error';
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = 'error';
        }
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
    <title>Register - EcoWaste Solutions</title>
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
            background: var(--bg-primary);
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
        
        .success-message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
            border: 1px solid var(--success);
            color: var(--success);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
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
            <h2>Create an Account</h2>
            <p>Join us to manage your waste efficiently.</p>
            
            <?php if(!empty($message)): ?>
                <div class="<?= $message_type ?>-message"><?= $message ?></div>
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
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
                <button type="submit" class="btn-login">Register</button>
            </form>
            <div class="links">
                <a href="login.php">Already have an account? Login</a>
            </div>
        </div>
    </div>
    
    <script src="theme.js"></script>
</body>
</html>