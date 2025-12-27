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

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
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
        
        $driver_id = $conn->insert_id;
        $notif_stmt = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Welcome to EcoWaste', 'You have been registered as a driver. You will receive pickup assignments soon.', 'info')");
        $notif_stmt->bind_param("i", $driver_id);
        $notif_stmt->execute();
        $notif_stmt->close();
        
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
        $pickup_stmt = $conn->prepare("SELECT p.*, u.name as user_name, d.name as driver_name FROM pickups p JOIN users u ON p.user_id = u.id JOIN drivers d ON d.id = ? WHERE p.id = ?");
        $pickup_stmt->bind_param("ii", $driver_id, $pickup_id);
        $pickup_stmt->execute();
        $pickup_data = $pickup_stmt->get_result()->fetch_assoc();
        $pickup_stmt->close();
        
        $stmt = $conn->prepare("UPDATE pickups SET driver_id = ?, status = 'Assigned' WHERE id = ?");
        $stmt->bind_param("ii", $driver_id, $pickup_id);
        if ($stmt->execute()) {
            $success_message = "Driver assigned successfully!";
            
            $user_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Driver Assigned', 'A driver has been assigned to your pickup on {$pickup_data['pickup_date']}. Driver: {$pickup_data['driver_name']}', 'success')");
            $user_notif->bind_param("i", $pickup_data['user_id']);
            $user_notif->execute();
            $user_notif->close();
            
            $driver_notif = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'New Pickup Assigned', 'You have been assigned a new pickup from {$pickup_data['user_name']} on {$pickup_data['pickup_date']} at {$pickup_data['area']}, {$pickup_data['city']}.', 'info')");
            $driver_notif->bind_param("i", $driver_id);
            $driver_notif->execute();
            $driver_notif->close();
            
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
        
        $notif_stmt = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Account Status', 'Your driver account has been deactivated.', 'warning')");
        $notif_stmt->bind_param("i", $driver_id);
        $notif_stmt->execute();
        $notif_stmt->close();
        
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'Driver Removed', 'A driver has been removed from the system.', 'warning')");
        $admin_notif->bind_param("i", $admin_id);
        $admin_notif->execute();
        $admin_notif->close();
    }
    $stmt->close();
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch recent 3 notifications
$notif_sql = "SELECT * FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 3";
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

// Dashboard Statistics
$today_pickups_sql = "SELECT COUNT(*) as total FROM pickups WHERE DATE(pickup_date) = CURDATE()";
$today_pickups_res = $conn->query($today_pickups_sql);
$today_pickups = $today_pickups_res->fetch_assoc()['total'];

$completed_today_sql = "SELECT COUNT(*) as total FROM pickups WHERE status = 'Completed' AND DATE(updated_at) = CURDATE()";
$completed_today_res = $conn->query($completed_today_sql);
$completed_today = $completed_today_res->fetch_assoc()['total'];

$pending_today_sql = "SELECT COUNT(*) as total FROM pickups WHERE DATE(pickup_date) = CURDATE() AND status IN ('Scheduled', 'Assigned', 'Out for Pickup')";
$pending_today_res = $conn->query($pending_today_sql);
$pending_today = $pending_today_res->fetch_assoc()['total'];

$collected_today_sql = "SELECT COUNT(*) as total FROM pickups WHERE status IN ('Collected', 'Collected by Driver') AND DATE(updated_at) = CURDATE()";
$collected_today_res = $conn->query($collected_today_sql);
$collected_today = $collected_today_res->fetch_assoc()['total'];

// Monthly statistics
$current_month = date('Y-m');
$monthly_pickups_sql = "SELECT COUNT(*) as total FROM pickups WHERE DATE_FORMAT(pickup_date, '%Y-%m') = ?";
$stmt_monthly = $conn->prepare($monthly_pickups_sql);
$stmt_monthly->bind_param("s", $current_month);
$stmt_monthly->execute();
$monthly_pickups = $stmt_monthly->get_result()->fetch_assoc()['total'];
$stmt_monthly->close();

$monthly_completed_sql = "SELECT COUNT(*) as total FROM pickups WHERE DATE_FORMAT(pickup_date, '%Y-%m') = ? AND status = 'Completed'";
$stmt_monthly_completed = $conn->prepare($monthly_completed_sql);
$stmt_monthly_completed->bind_param("s", $current_month);
$stmt_monthly_completed->execute();
$monthly_completed = $stmt_monthly_completed->get_result()->fetch_assoc()['total'];
$stmt_monthly_completed->close();

// Waste type statistics
$waste_stats_sql = "SELECT waste_type, COUNT(*) as count FROM pickups WHERE status = 'Completed' GROUP BY waste_type";
$waste_stats = $conn->query($waste_stats_sql);

// Driver performance
$driver_performance_sql = "SELECT d.name, COUNT(p.id) as completed FROM drivers d LEFT JOIN pickups p ON d.id = p.driver_id AND p.status = 'Completed' WHERE d.is_deleted = FALSE GROUP BY d.id, d.name ORDER BY completed DESC LIMIT 10";
$driver_performance = $conn->query($driver_performance_sql);

// Fetch pickups by status
$scheduled_sql = "SELECT p.*, u.name as user_name, d.name as driver_name FROM pickups p JOIN users u ON p.user_id = u.id LEFT JOIN drivers d ON p.driver_id = d.id WHERE u.is_deleted = FALSE AND p.status = 'Scheduled' ORDER BY p.requested_at DESC";
$scheduled_result = $conn->query($scheduled_sql);

$assigned_sql = "SELECT p.*, u.name as user_name, d.name as driver_name FROM pickups p JOIN users u ON p.user_id = u.id LEFT JOIN drivers d ON p.driver_id = d.id WHERE u.is_deleted = FALSE AND p.status = 'Assigned' ORDER BY p.requested_at DESC";
$assigned_result = $conn->query($assigned_sql);

// Added: Out for Pickup Query
$out_for_pickup_sql = "SELECT p.*, u.name as user_name, d.name as driver_name FROM pickups p JOIN users u ON p.user_id = u.id LEFT JOIN drivers d ON p.driver_id = d.id WHERE u.is_deleted = FALSE AND p.status = 'Out for Pickup' ORDER BY p.requested_at DESC";
$out_for_pickup_result = $conn->query($out_for_pickup_sql);

$collected_sql = "SELECT p.*, u.name as user_name, d.name as driver_name FROM pickups p JOIN users u ON p.user_id = u.id LEFT JOIN drivers d ON p.driver_id = d.id WHERE u.is_deleted = FALSE AND p.status IN ('Collected', 'Collected by Driver') ORDER BY p.requested_at DESC";
$collected_result = $conn->query($collected_sql);

$completed_sql = "SELECT p.*, u.name as user_name, d.name as driver_name FROM pickups p JOIN users u ON p.user_id = u.id LEFT JOIN drivers d ON p.driver_id = d.id WHERE u.is_deleted = FALSE AND p.status IN ('Completed', 'Cancelled') ORDER BY p.updated_at DESC";
$completed_result = $conn->query($completed_sql);

// Count pickups by status
$scheduled_count = $scheduled_result->num_rows;
$assigned_count = $assigned_result->num_rows;
$out_for_pickup_count = $out_for_pickup_result->num_rows; // Added count
$collected_count = $collected_result->num_rows;
$completed_count = $completed_result->num_rows;

// Fetch all active drivers
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
    <link rel="stylesheet" href="style.css?v=2">
    <link rel="stylesheet" href="dashboard-style.css?v=2">
    <link rel="stylesheet" href="admin-style.css?v=2">
    <link rel="stylesheet" href="theme.css?v=2">
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

        .btn-complete {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .main-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        .main-tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-weight: 600;
            position: relative;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .main-tab.active {
            color: var(--primary);
        }
        .main-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary);
        }
        .main-tab-content {
            display: none;
        }
        .main-tab-content.active {
            display: block;
        }
        
        .sub-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .sub-tab {
            padding: 0.625rem 1.25rem;
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-secondary);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        .sub-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }
        .sub-tab-content {
            display: none;
        }
        .sub-tab-content.active {
            display: block;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: var(--bg-tertiary);
            padding: 1.5rem;
            border-radius: 16px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .stat-box:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: 0 8px 20px var(--shadow-color);
        }
        .stat-box h4 {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .stat-box .value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            font-family: 'Space Grotesk', sans-serif;
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
            max-height: 450px;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 10px 40px var(--shadow-color);
            display: none;
            z-index: 1000;
            overflow: hidden;
            flex-direction: column;
        }
        .notification-dropdown.show {
            display: flex;
        }
        .notification-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-body {
            flex: 1;
            overflow-y: auto;
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
        .notification-footer {
            padding: 0.75rem 1.25rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }
        .view-all-btn {
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .view-all-btn:hover {
            text-decoration: underline;
        }
        .mark-all-read-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }
        .mark-all-read-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
        }
        
        .all-notifications-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        .all-notifications-modal.show {
            display: flex;
        }
        .modal-content {
            background: var(--bg-secondary);
            border-radius: 24px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 60px var(--shadow-color);
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        .modal-body {
            padding: 1rem;
            overflow-y: auto;
            flex: 1;
        }
        .modal-close {
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            color: var(--text-primary);
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .modal-close:hover {
            border-color: var(--primary);
            color: var(--primary);
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
                            <div class="notification-header">
                                Notifications
                                <button class="mark-all-read-btn" onclick="markAllRead()">Mark All</button>
                            </div>
                            <div class="notification-body">
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
                            <?php if ($notifications->num_rows > 0): ?>
                            <div class="notification-footer">
                                <div class="view-all-btn" onclick="showAllNotifications()">View All Notifications</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li><a href="logout.php" class="btn btn-secondary">Logout</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="all-notifications-modal" id="allNotificationsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>All Notifications</h2>
                <button class="modal-close" onclick="closeAllNotifications()">&times;</button>
            </div>
            <div class="modal-body" id="allNotificationsBody"></div>
        </div>
    </div>

    <main class="dashboard-main">
        <div class="container">
            <div class="dashboard-header">
                <h1>Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</h1>
            </div>

            <div class="main-tabs">
                <button class="main-tab active" onclick="switchMainTab('dashboard')">Dashboard</button>
                <button class="main-tab" onclick="switchMainTab('pickups')">Pickup Requests</button>
                <button class="main-tab" onclick="switchMainTab('drivers')">Manage Drivers</button>
                <button class="main-tab" onclick="switchMainTab('users')">Manage Users</button>
            </div>

            <div id="dashboard-tab" class="main-tab-content active">
                <section class="dashboard-section">
                    <h2>Today's Overview</h2>
                    <div class="stats-row">
                        <div class="stat-box">
                            <h4>Scheduled for Today</h4>
                            <div class="value"><?= $today_pickups ?></div>
                        </div>
                        <div class="stat-box">
                            <h4>Collected Today</h4>
                            <div class="value"><?= $collected_today ?></div>
                        </div>
                        <div class="stat-box">
                            <h4>Completed Today</h4>
                            <div class="value"><?= $completed_today ?></div>
                        </div>
                        <div class="stat-box">
                            <h4>Pending Today</h4>
                            <div class="value"><?= $pending_today ?></div>
                        </div>
                    </div>
                </section>
                 <section class="dashboard-section">
                    <h2>Monthly Statistics</h2>
                    <div class="stats-row">
                        <div class="stat-box"><h4>Total Monthly</h4><div class="value"><?= $monthly_pickups ?></div></div>
                        <div class="stat-box"><h4>Completed Monthly</h4><div class="value"><?= $monthly_completed ?></div></div>
                        <div class="stat-box"><h4>Success Rate</h4><div class="value"><?= $monthly_pickups > 0 ? round(($monthly_completed / $monthly_pickups) * 100) : 0 ?>%</div></div>
                    </div>
                </section>

                <section class="dashboard-section">
                    <h2>Waste Collection by Type</h2>
                    <div class="stats-row">
                        <?php if ($waste_stats->num_rows > 0): while($waste = $waste_stats->fetch_assoc()): ?>
                            <div class="stat-box"><h4><?= htmlspecialchars($waste['waste_type']) ?></h4><div class="value"><?= $waste['count'] ?></div></div>
                        <?php endwhile; else: ?>
                            <div class="stat-box"><h4>No Data</h4><div class="value">0</div></div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="dashboard-section">
                    <h2>Top Driver Performance</h2>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Driver Name</th><th>Completed Pickups</th></tr></thead>
                            <tbody>
                                <?php if ($driver_performance->num_rows > 0): while($driver = $driver_performance->fetch_assoc()): ?>
                                    <tr><td data-label="Driver Name"><?= htmlspecialchars($driver['name']) ?></td><td data-label="Completed Pickups"><?= $driver['completed'] ?></td></tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="2">No driver data available</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div id="pickups-tab" class="main-tab-content">
                <section class="dashboard-section">
                    <h2>All Pickup Requests</h2>
                    
                    <div class="sub-tabs">
                        <button class="sub-tab active" onclick="switchPickupSubTab('scheduled')">Scheduled (<?= $scheduled_count ?>)</button>
                        <button class="sub-tab" onclick="switchPickupSubTab('assigned')">Assigned (<?= $assigned_count ?>)</button>
                        <button class="sub-tab" onclick="switchPickupSubTab('out-for-pickup')">Out for Pickup (<?= $out_for_pickup_count ?>)</button>
                        <button class="sub-tab" onclick="switchPickupSubTab('collected')">Collected (<?= $collected_count ?>)</button>
                        <button class="sub-tab" onclick="switchPickupSubTab('completed')">Completed (<?= $completed_count ?>)</button>
                    </div>

                    <div id="scheduled-subtab" class="sub-tab-content active">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User & Location</th>
                                        <th>Date & Time</th>
                                        <th>Waste Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($scheduled_result->num_rows > 0): ?>
                                        <?php while($row = $scheduled_result->fetch_assoc()): ?>
                                        <tr>
                                            <td data-label="User & Location">
                                                <?= htmlspecialchars($row['user_name']) ?><br>
                                                <small><?= htmlspecialchars($row['area']) ?>, <?= htmlspecialchars($row['city']) ?></small>
                                            </td>
                                            <td data-label="Date & Time">
                                                <?= htmlspecialchars($row['pickup_date']) ?><br>
                                                <small><?= htmlspecialchars($row['time_slot']) ?></small>
                                            </td>
                                            <td data-label="Waste Type"><?= htmlspecialchars($row['waste_type']) ?></td>
                                            <td data-label="Action">
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
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4">No scheduled pickups.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="assigned-subtab" class="sub-tab-content">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User & Location</th>
                                        <th>Date & Time</th>
                                        <th>Waste Type</th>
                                        <th>Assigned Driver</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($assigned_result->num_rows > 0): ?>
                                        <?php while($row = $assigned_result->fetch_assoc()): ?>
                                        <tr>
                                            <td data-label="User & Location">
                                                <?= htmlspecialchars($row['user_name']) ?><br>
                                                <small><?= htmlspecialchars($row['area']) ?>, <?= htmlspecialchars($row['city']) ?></small>
                                            </td>
                                            <td data-label="Date & Time">
                                                <?= htmlspecialchars($row['pickup_date']) ?><br>
                                                <small><?= htmlspecialchars($row['time_slot']) ?></small>
                                            </td>
                                            <td data-label="Waste Type"><?= htmlspecialchars($row['waste_type']) ?></td>
                                            <td data-label="Assigned Driver"><?= htmlspecialchars($row['driver_name']) ?></td>
                                            <td data-label="Action">
                                                <form action="update_status.php" method="POST" style="display:inline;" onsubmit="return handleCollectedCancel(event, this)">
                                                    <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="new_status" value="Cancelled">
                                                    <button type="submit" class="btn-delete">Cancel</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5">No assigned pickups.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Added: Out for Pickup Sub-tab -->
                    <div id="out-for-pickup-subtab" class="sub-tab-content">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User & Location</th>
                                        <th>Date & Time</th>
                                        <th>Waste Type</th>
                                        <th>Driver (On the way)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($out_for_pickup_result->num_rows > 0): ?>
                                        <?php while($row = $out_for_pickup_result->fetch_assoc()): ?>
                                        <tr>
                                            <td data-label="User & Location">
                                                <?= htmlspecialchars($row['user_name']) ?><br>
                                                <small><?= htmlspecialchars($row['area']) ?>, <?= htmlspecialchars($row['city']) ?></small>
                                            </td>
                                            <td data-label="Date & Time">
                                                <?= htmlspecialchars($row['pickup_date']) ?><br>
                                                <small><?= htmlspecialchars($row['time_slot']) ?></small>
                                            </td>
                                            <td data-label="Waste Type"><?= htmlspecialchars($row['waste_type']) ?></td>
                                            <td data-label="Driver (On the way)"><?= htmlspecialchars($row['driver_name']) ?></td>
                                            <td data-label="Action">
                                                <form action="update_status.php" method="POST" style="display:inline;" onsubmit="return handleCollectedCancel(event, this)">
                                                    <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="new_status" value="Cancelled">
                                                    <button type="submit" class="btn-delete">Cancel</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5">No pickups currently out for collection.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="collected-subtab" class="sub-tab-content">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User & Location</th>
                                        <th>Date & Time</th>
                                        <th>Waste Type</th>
                                        <th>Driver</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($collected_result->num_rows > 0): ?>
                                        <?php while($row = $collected_result->fetch_assoc()): ?>
                                        <tr>
                                            <td data-label="User & Location">
                                                <?= htmlspecialchars($row['user_name']) ?><br>
                                                <small><?= htmlspecialchars($row['area']) ?>, <?= htmlspecialchars($row['city']) ?></small>
                                            </td>
                                            <td data-label="Date & Time">
                                                <?= htmlspecialchars($row['pickup_date']) ?><br>
                                                <small><?= htmlspecialchars($row['time_slot']) ?></small>
                                            </td>
                                            <td data-label="Waste Type"><?= htmlspecialchars($row['waste_type']) ?></td>
                                            <td data-label="Driver"><?= htmlspecialchars($row['driver_name']) ?></td>
                                            <td data-label="Action">
                                                <div class="action-buttons">
                                                    <form action="update_status.php" method="POST" style="display:inline;">
                                                        <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="new_status" value="Completed">
                                                        <button type="submit" class="btn-complete">Complete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5">No collected pickups.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="completed-subtab" class="sub-tab-content">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User & Location</th>
                                        <th>Date & Time</th>
                                        <th>Waste Type</th>
                                        <th>Driver</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($completed_result->num_rows > 0): ?>
                                        <?php while($row = $completed_result->fetch_assoc()): ?>
                                        <tr>
                                            <td data-label="User & Location">
                                                <?= htmlspecialchars($row['user_name']) ?><br>
                                                <small><?= htmlspecialchars($row['area']) ?>, <?= htmlspecialchars($row['city']) ?></small>
                                            </td>
                                            <td data-label="Date & Time">
                                                <?= htmlspecialchars($row['pickup_date']) ?><br>
                                                <small><?= htmlspecialchars($row['time_slot']) ?></small>
                                            </td>
                                            <td data-label="Waste Type"><?= htmlspecialchars($row['waste_type']) ?></td>
                                            <td data-label="Driver"><?= htmlspecialchars($row['driver_name'] ?? 'N/A') ?></td>
                                            <td data-label="Status">
                                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                                    <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5">No completed pickups.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
            
                        <!-- Drivers Tab with Sub-tabs -->
            <div id="drivers-tab" class="main-tab-content">
                <section class="dashboard-section">
                    <h2>Manage Drivers</h2>
                    
                    <div class="sub-tabs">
                        <button class="sub-tab active" onclick="switchDriverSubTab('add-driver')">Add Driver</button>
                        <button class="sub-tab" onclick="switchDriverSubTab('driver-list')">Driver List</button>
                    </div>

                    <!-- Add Driver Sub-tab -->
                    <div id="add-driver-subtab" class="sub-tab-content active">
                        <form action="admin_dashboard.php" method="post" class="pickup-form" onsubmit="return validateDriverForm(this)">
                            <h3 style="grid-column: 1 / -1;">Create New Driver</h3>
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
                            <button type="submit" name="create_driver" class="btn btn-primary" style="grid-column: 1 / -1;">Add Driver</button>
                        </form>
                    </div>

                    <!-- Driver List Sub-tab -->
                    <div id="driver-list-subtab" class="sub-tab-content">
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
                                        <?php $all_drivers_result->data_seek(0); ?>
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

            <!-- Users Tab with Sub-tabs -->
            <div id="users-tab" class="main-tab-content">
                <section class="dashboard-section">
                    <h2>Manage Users</h2>
                    
                    <div class="sub-tabs">
                        <button class="sub-tab active" onclick="switchUserSubTab('active-users')">Active Users</button>
                    </div>

                    <!-- Active Users Sub-tab -->
                    <div id="active-users-subtab" class="sub-tab-content active">
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
                </section>
            </div>
        </div>
    </main>

    <script src="theme.js"></script>
    <script>
        function switchMainTab(tabName) {
            document.querySelectorAll('.main-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.main-tab-content').forEach(content => content.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }

        function switchPickupSubTab(subTabName) {
            const parent = document.getElementById('pickups-tab');
            parent.querySelectorAll('.sub-tab').forEach(tab => tab.classList.remove('active'));
            parent.querySelectorAll('.sub-tab-content').forEach(content => content.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById(subTabName + '-subtab').classList.add('active');
        }
        
        
        <?php if (isset($success_message) && $success_message): ?>
            showAlert('<?= addslashes($success_message) ?>', 'success');
        <?php endif; ?>
        
        <?php if (isset($error_message) && $error_message): ?>
            showAlert('<?= addslashes($error_message) ?>', 'error');
        <?php endif; ?>

        // Main tab switching
        function switchMainTab(tabName) {
            document.querySelectorAll('.main-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.main-tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }

        // Sub-tab switching for pickups
        function switchPickupSubTab(subTabName) {
            const parent = document.getElementById('pickups-tab');
            parent.querySelectorAll('.sub-tab').forEach(tab => tab.classList.remove('active'));
            parent.querySelectorAll('.sub-tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(subTabName + '-subtab').classList.add('active');
        }

        // Sub-tab switching for drivers
        function switchDriverSubTab(subTabName) {
            const parent = document.getElementById('drivers-tab');
            parent.querySelectorAll('.sub-tab').forEach(tab => tab.classList.remove('active'));
            parent.querySelectorAll('.sub-tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(subTabName + '-subtab').classList.add('active');
        }

        // Sub-tab switching for users
        function switchUserSubTab(subTabName) {
            const parent = document.getElementById('users-tab');
            parent.querySelectorAll('.sub-tab').forEach(tab => tab.classList.remove('active'));
            parent.querySelectorAll('.sub-tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(subTabName + '-subtab').classList.add('active');
        }

        // Notification functions
        function toggleNotifications() {
            document.getElementById('notificationDropdown').classList.toggle('show');
        }

        function showAllNotifications() {
            document.getElementById('allNotificationsModal').classList.add('show');
            document.getElementById('notificationDropdown').classList.remove('show');
            fetchAllNotifications();
        }

        function closeAllNotifications() {
            document.getElementById('allNotificationsModal').classList.remove('show');
        }

        function markAllRead() {
            showConfirm('Mark all notifications as read?', () => {
                window.location.href = 'admin_dashboard.php?mark_all_read=1';
            });
        }

        function markAsRead(notifId) {
            window.location.href = `admin_dashboard.php?mark_read=1&notif_id=${notifId}`;
        }

        function fetchAllNotifications() {
            fetch('get_all_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const body = document.getElementById('allNotificationsBody');
                    if (data.error) {
                        body.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
                        return;
                    }
                    if (data.length === 0) {
                        body.innerHTML = '<div class="notification-empty">No notifications</div>';
                        return;
                    }
                    body.innerHTML = data.map(notif => `
                        <div class="notification-item ${!notif.is_read ? 'unread' : ''}" onclick="markAsRead(${notif.id})">
                            <div class="notification-title">${notif.title}</div>
                            <div class="notification-message">${notif.message}</div>
                            <div class="notification-time">${notif.created_at}</div>
                        </div>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('allNotificationsBody').innerHTML = '<div class="notification-empty">Error loading notifications</div>';
                });
        }

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const bell = document.querySelector('.notification-bell');
            const dropdown = document.getElementById('notificationDropdown');
            if (bell && dropdown && !bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Close modal when clicking outside
        document.getElementById('allNotificationsModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closeAllNotifications();
            }
        });

        function confirmDelete(type, id, name) {
            const message = type === 'user' 
                ? `Are you sure you want to delete user "${name}"? This action cannot be undone.`
                : `Are you sure you want to fire driver "${name}"? This action cannot be undone.`;
            
            showConfirm(message, () => {
                const param = type === 'user' ? 'delete_user' : 'fire_driver';
                window.location.href = `admin_dashboard.php?${param}=${id}`;
            });
        }

        function handleCollectedCancel(event, form) {
            event.preventDefault();
            showConfirm('Are you sure you want to cancel this pickup?', () => {
                form.submit();
            });
            return false;
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