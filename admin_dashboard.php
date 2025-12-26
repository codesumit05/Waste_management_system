<?php
require 'db.php';

if (!isset($_SESSION["admin_id"])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$success_message = '';
$error_message = '';

// Mark notification as read
if (isset($_GET['mark_read']) && isset($_GET['notif_id'])) {
    $notif_id = intval($_GET['notif_id']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $notif_id, $admin_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_dashboard.php");
    exit();
}

// Handle Create Driver form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_driver'])) {
    $name = $_POST['driver_name'];
    $email = $_POST['driver_email'];
    $password = password_hash($_POST['driver_password'], PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO drivers (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $password);
    if ($stmt->execute()) {
        $success_message = "Driver created successfully!";
        
        // Create notification for new driver
        $driver_id = $conn->insert_id;
        $notif_stmt = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Welcome to EcoWaste', 'You have been registered as a driver. You will receive pickup assignments soon.', 'info')");
        $notif_stmt->bind_param("i", $driver_id);
        $notif_stmt->execute();
        $notif_stmt->close();
        
        // Notify admin
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'New Driver Added', 'Driver $name has been successfully added to the system.', 'success')");
        $admin_notif->bind_param("i", $admin_id);
        $admin_notif->execute();
        $admin_notif->close();
    } else {
        $error_message = "Error creating driver.";
    }
    $stmt->close();
}

// Handle Assign Driver form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_driver'])) {
    $pickup_id = $_POST['pickup_id'];
    $driver_id = $_POST['driver_id'];
    if (!empty($driver_id)) {
        // Get pickup and user details
        $pickup_stmt = $conn->prepare("SELECT p.*, u.name as user_name, d.name as driver_name FROM pickups p JOIN users u ON p.user_id = u.id JOIN drivers d ON d.id = ? WHERE p.id = ?");
        $pickup_stmt->bind_param("ii", $driver_id, $pickup_id);
        $pickup_stmt->execute();
        $pickup_data = $pickup_stmt->get_result()->fetch_assoc();
        $pickup_stmt->close();
        
        $stmt = $conn->prepare("UPDATE pickups SET driver_id = ?, status = 'Assigned' WHERE id = ?");
        $stmt->bind_param("ii", $driver_id, $pickup_id);
        if ($stmt->execute()) {
            $success_message = "Driver assigned successfully!";
            
            // Create notifications for user and driver
            $user_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Driver Assigned', 'A driver has been assigned to your pickup on {$pickup_data['pickup_date']}. Driver: {$pickup_data['driver_name']}', 'success')");
            $user_notif->bind_param("i", $pickup_data['user_id']);
            $user_notif->execute();
            $user_notif->close();
            
            $driver_notif = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'New Pickup Assigned', 'You have been assigned a new pickup from {$pickup_data['user_name']} on {$pickup_data['pickup_date']} at {$pickup_data['area']}, {$pickup_data['city']}.', 'info')");
            $driver_notif->bind_param("i", $driver_id);
            $driver_notif->execute();
            $driver_notif->close();
            
            // Notify admin
            $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'Driver Assigned', 'Driver {$pickup_data['driver_name']} assigned to pickup from {$pickup_data['user_name']}.', 'info')");
            $admin_notif->bind_param("i", $admin_id);
            $admin_notif->execute();
            $admin_notif->close();
        }
        $stmt->close();
    }
}

// Handle Delete User
if (isset($_GET['delete_user'])) {
    $user_id = intval($_GET['delete_user']);
    $stmt = $conn->prepare("UPDATE users SET is_deleted = TRUE, deleted_at = NOW() WHERE id = ? AND is_admin = FALSE");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $success_message = "User deleted successfully!";
        
        // Notify admin
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'User Deleted', 'A user account has been deleted from the system.', 'warning')");
        $admin_notif->bind_param("i", $admin_id);
        $admin_notif->execute();
        $admin_notif->close();
    }
    $stmt->close();
    header("Location: admin_dashboard.php");
    exit();
}

// Handle Fire Driver
if (isset($_GET['fire_driver'])) {
    $driver_id = intval($_GET['fire_driver']);
    $stmt = $conn->prepare("UPDATE drivers SET is_deleted = TRUE, deleted_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $driver_id);
    if ($stmt->execute()) {
        $success_message = "Driver removed successfully!";
        
        // Notify driver
        $notif_stmt = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Account Status', 'Your driver account has been deactivated.', 'warning')");
        $notif_stmt->bind_param("i", $driver_id);
        $notif_stmt->execute();
        $notif_stmt->close();
        
        // Notify admin
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'Driver Removed', 'A driver has been removed from the system.', 'warning')");
        $admin_notif->bind_param("i", $admin_id);
        $admin_notif->execute();
        $admin_notif->close();
    }
    $stmt->close();
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch notifications
$notif_sql = "SELECT * FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt_notif = $conn->prepare($notif_sql);
$stmt_notif->bind_param("i", $admin_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result();

// Count unread notifications
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE admin_id = ? AND is_read = FALSE";
$stmt_unread = $conn->prepare($unread_sql);
$stmt_unread->bind_param("i", $admin_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['count'];
$stmt_unread->close();

// Get today's date
$today = date('Y-m-d');

// Fetch total pickups for today
$today_pickups_sql = "SELECT COUNT(*) as total FROM pickups WHERE DATE(pickup_date) = ?";
$stmt_today = $conn->prepare($today_pickups_sql);
$stmt_today->bind_param("s", $today);
$stmt_today->execute();
$today_result = $stmt_today->get_result();
$today_pickups = $today_result->fetch_assoc()['total'];
$stmt_today->close();

// Fetch completed pickups for today
$completed_today_sql = "SELECT COUNT(*) as total FROM pickups WHERE DATE(pickup_date) = ? AND status = 'Completed'";
$stmt_completed = $conn->prepare($completed_today_sql);
$stmt_completed->bind_param("s", $today);
$stmt_completed->execute();
$completed_result = $stmt_completed->get_result();
$completed_today = $completed_result->fetch_assoc()['total'];
$stmt_completed->close();

// Fetch pending pickups for today
$pending_today_sql = "SELECT COUNT(*) as total FROM pickups WHERE DATE(pickup_date) = ? AND status IN ('Scheduled', 'Assigned')";
$stmt_pending = $conn->prepare($pending_today_sql);
$stmt_pending->bind_param("s", $today);
$stmt_pending->execute();
$pending_result = $stmt_pending->get_result();
$pending_today = $pending_result->fetch_assoc()['total'];
$stmt_pending->close();

// Fetch all pickups with user and driver names
$pickups_sql = "SELECT p.*, u.name as user_name, d.name as driver_name 
                FROM pickups p 
                JOIN users u ON p.user_id = u.id
                LEFT JOIN drivers d ON p.driver_id = d.id
                WHERE u.is_deleted = FALSE
                ORDER BY p.requested_at DESC";
$pickups_result = $conn->query($pickups_sql);

// Fetch all active drivers for the dropdown
$drivers_result = $conn->query("SELECT id, name FROM drivers WHERE is_deleted = FALSE");
$drivers_list = $drivers_result->fetch_all(MYSQLI_ASSOC);

// Fetch active users
$users_sql = "SELECT name, email, created_at, id FROM users WHERE is_admin = FALSE AND is_deleted = FALSE ORDER BY created_at DESC";
$users_result = $conn->query($users_sql);

// Fetch active drivers
$all_drivers_sql = "SELECT name, email, created_at, id FROM drivers WHERE is_deleted = FALSE ORDER BY created_at DESC";
$all_drivers_result = $conn->query($all_drivers_sql);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard-style.css">
    <link rel="stylesheet" href="admin-style.css">
    <link rel="stylesheet" href="theme.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }
        .tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-weight: 600;
            position: relative;
            transition: all 0.3s ease;
        }
        .tab.active {
            color: var(--primary);
        }
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 0.5rem;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            width: 380px;
            max-height: 500px;
            overflow-y: auto;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 10px 40px var(--shadow-color);
            display: none;
            z-index: 1000;
        }
        .notification-dropdown.show {
            display: block;
        }
        .notification-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            color: var(--text-primary);
        }
        .notification-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .notification-item:hover {
            background: var(--bg-tertiary);
        }
        .notification-item.unread {
            background: rgba(14, 165, 233, 0.05);
        }
        .notification-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        .notification-message {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        .notification-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        .notification-empty {
            padding: 2rem;
            text-align: center;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <a href="admin_dashboard.php" class="logo">EcoWaste Admin</a>
                <button id="theme-toggle" class="theme-toggle" aria-label="Toggle theme"></button>
                <ul class="nav-links">
                    <li style="position: relative;">
                        <div class="notification-bell" onclick="toggleNotifications()">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                            <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">Notifications</div>
                            <?php if ($notifications->num_rows > 0): ?>
                                <?php while($notif = $notifications->fetch_assoc()): ?>
                                <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" 
                                     onclick="markAsRead(<?= $notif['id'] ?>)">
                                    <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                                    <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                    <div class="notification-time"><?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?></div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="notification-empty">No notifications</div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li><a href="logout.php" class="btn btn-secondary">Logout</a></li>
                </ul>
            </div>
        </nav>
    </header>
    <main class="dashboard-main">
        <div class="container">
            <div class="dashboard-header">
                <h1>Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</h1>
            </div>

            <!-- Daily Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Today's Pickups</div>
                        <div class="stat-value"><?= $today_pickups ?></div>
                    </div>
                </div>

                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Completed Today</div>
                        <div class="stat-value"><?= $completed_today ?></div>
                    </div>
                </div>

                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Pending Today</div>
                        <div class="stat-value"><?= $pending_today ?></div>
                    </div>
                </div>
            </div>

            <section class="dashboard-section">
                <h2>All Pickup Requests</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User & Location</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pickups_result->num_rows > 0): while($row = $pickups_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="User & Location">
                                    <?= htmlspecialchars($row['user_name']) ?><br>
                                    <small><?= htmlspecialchars($row['area']) ?>, <?= htmlspecialchars($row['city']) ?></small>
                                </td>
                                <td data-label="Date & Time">
                                    <?= htmlspecialchars($row['pickup_date']) ?><br>
                                    <small><?= htmlspecialchars($row['time_slot']) ?></small>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td data-label="Assigned To">
                                    <?= htmlspecialchars($row['driver_name'] ?? 'Not Assigned') ?>
                                </td>
                                <td data-label="Action">
                                    <?php if ($row['status'] == 'Scheduled'): ?>
                                    <form method="POST" action="admin_dashboard.php" class="status-form" onsubmit="return validateAssign(this)">
                                        <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                        <select name="driver_id" required>
                                            <option value="">Select Driver</option>
                                            <?php foreach ($drivers_list as $driver): ?>
                                            <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_driver">Assign</button>
                                    </form>
                                    <?php elseif ($row['status'] == 'Collected' || $row['status'] == 'Collected by Driver'): ?>
                                    <form action="update_status.php" method="POST" class="status-form" onsubmit="return validateStatus(this)">
                                        <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                        <select name="new_status">
                                            <option value="Completed">Completed</option>
                                            <option value="Cancelled">Cancel</option>
                                        </select>
                                        <button type="submit">Update</button>
                                    </form>
                                    <?php else: echo 'No action needed'; endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5">No pickup requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <section class="dashboard-section">
                <h2>Manage Drivers</h2>
                <form action="admin_dashboard.php" method="post" class="pickup-form" onsubmit="return validateDriverForm(this)">
                    <h3>Create New Driver</h3>
                    <div class="form-row-compact">
                        <div class="form-group">
                            <label for="driver_name">Driver Name</label>
                            <input type="text" id="driver_name" name="driver_name" required>
                        </div>
                        <div class="form-group">
                            <label for="driver_email">Driver Email</label>
                            <input type="email" id="driver_email" name="driver_email" required>
                        </div>
                        <div class="form-group">
                            <label for="driver_password">Password</label>
                            <input type="password" id="driver_password" name="driver_password" required>
                        </div>
                    </div>
                    <button type="submit" name="create_driver" class="btn btn-primary">Add Driver</button>
                </form>
            </section>

            <section class="dashboard-section">
                <h2>Manage Users & Drivers</h2>
                
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('users')">Users</button>
                    <button class="tab" onclick="switchTab('drivers')">Drivers</button>
                </div>

                <!-- Users Tab -->
                <div id="users-tab" class="tab-content active">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Registration Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users_result->num_rows > 0): ?>
                                    <?php while($row = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Name"><?= htmlspecialchars($row['name']) ?></td>
                                        <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
                                        <td data-label="Registration Date"><?= date("M j, Y", strtotime($row['created_at'])) ?></td>
                                        <td data-label="Action">
                                            <button class="btn-delete" onclick="confirmDelete('user', <?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>')">Delete User</button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4">No users found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Drivers Tab -->
                <div id="drivers-tab" class="tab-content">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Registration Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($all_drivers_result->num_rows > 0): ?>
                                    <?php while($row = $all_drivers_result->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Name"><?= htmlspecialchars($row['name']) ?></td>
                                        <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
                                        <td data-label="Registration Date"><?= date("M j, Y", strtotime($row['created_at'])) ?></td>
                                        <td data-label="Action">
                                            <button class="btn-delete" onclick="confirmDelete('driver', <?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>')">Fire Driver</button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4">No drivers found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script src="theme.js"></script>
    <script>
        <?php if (isset($success_message) && $success_message): ?>
            showAlert('<?= addslashes($success_message) ?>', 'success');
        <?php endif; ?>
        
        <?php if (isset($error_message) && $error_message): ?>
            showAlert('<?= addslashes($error_message) ?>', 'error');
        <?php endif; ?>

        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tab + '-tab').classList.add('active');
        }

        function confirmDelete(type, id, name) {
            const message = type === 'user' 
                ? `Are you sure you want to delete user "${name}"? This action cannot be undone.`
                : `Are you sure you want to fire driver "${name}"? This action cannot be undone.`;
            
            showConfirm(message, () => {
                const param = type === 'user' ? 'delete_user' : 'fire_driver';
                window.location.href = `admin_dashboard.php?${param}=${id}`;
            });
        }

        function validateAssign(form) {
            const driver = form.querySelector('select[name="driver_id"]').value;
            if (!driver) {
                showAlert('Please select a driver', 'warning');
                return false;
            }
            return true;
        }

        function validateStatus(form) {
            const status = form.querySelector('select[name="new_status"]').value;
            if (!status) {
                showAlert('Please select a status', 'warning');
                return false;
            }
            return true;
        }

        function validateDriverForm(form) {
            const name = form.querySelector('#driver_name').value.trim();
            const email = form.querySelector('#driver_email').value.trim();
            const password = form.querySelector('#driver_password').value.trim();
            
            if (!name || !email || !password) {
                showAlert('All fields are required', 'warning');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>