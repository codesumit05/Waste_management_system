<?php
require 'db.php';

if (!isset($_SESSION["admin_id"])) {
    header("Location: admin_login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$success_message = '';
$error_message = '';
// Display messages from session and clear them
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
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

// Delete selected notifications
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_notifications'])) {
    if (!empty($_POST['notification_ids']) && is_array($_POST['notification_ids'])) {
        $ids = array_map('intval', $_POST['notification_ids']);
        $count = count($ids);
        $placeholders = implode(',', array_fill(0, $count, '?'));
        
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id IN ($placeholders) AND admin_id = ?");
        
        $types = str_repeat('i', $count + 1);
        $params = array_merge($ids, [$admin_id]);
        
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = $count . " notification(s) deleted successfully!";
        }
        $stmt->close();
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Delete single notification
if (isset($_GET['delete_notif'])) {
    $notif_id = intval($_GET['delete_notif']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $notif_id, $admin_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Notification deleted successfully!";
    }
    $stmt->close();
    header("Location: admin_dashboard.php");
    exit();
}
// Handle Create Driver form submission
// UPDATED: Handle Create Driver with Session Messaging
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_driver'])) {
    $name = $_POST['driver_name'];
    $email = $_POST['driver_email'];
    $mobile = $_POST['driver_mobile']; 
    $password = password_hash($_POST['driver_password'], PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("INSERT INTO drivers (name, email, mobile, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $mobile, $password);
    
    if ($stmt->execute()) {
        // Store in SESSION so it survives the redirect
        $_SESSION['success_message'] = "Driver $name created successfully!";
        
        // Notifications...
        $driver_id = $conn->insert_id;
        $notif_stmt = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Welcome', 'Account registered.', 'info')");
        $notif_stmt->bind_param("i", $driver_id);
        $notif_stmt->execute();

        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'New Driver Added', 'Driver $name has been successfully added to the system.', 'success')");
        $admin_notif->bind_param("i", $admin_id);
        $admin_notif->execute();
        $admin_notif->close();
    } else {
        if ($conn->errno == 1062) {
            $_SESSION['error_message'] = "Error: Email or Mobile already exists.";
        } else {
            $_SESSION['error_message'] = "Database Error: " . $stmt->error;
        }
    }
    $stmt->close();
    header("Location: admin_dashboard.php");
    exit();
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
        header("Location: admin_dashboard.php");
    exit();
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

// Handle Restore User
if (isset($_GET['restore_user'])) {
    $user_id = intval($_GET['restore_user']);
    $stmt = $conn->prepare("UPDATE users SET is_deleted = FALSE, deleted_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "User restored successfully!";
        
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'User Restored', 'A user account has been restored.', 'success')");
        $admin_notif->bind_param("i", $admin_id);
        $admin_notif->execute();
        $admin_notif->close();
    }
    $stmt->close();
    header("Location: admin_dashboard.php");
    exit();
}

// Handle Restore Driver
if (isset($_GET['restore_driver'])) {
    $driver_id = intval($_GET['restore_driver']);
    $stmt = $conn->prepare("UPDATE drivers SET is_deleted = FALSE, deleted_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $driver_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Driver restored successfully!";
        
        $notif_stmt = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Account Restored', 'Your driver account has been reactivated.', 'success')");
        $notif_stmt->bind_param("i", $driver_id);
        $notif_stmt->execute();
        $notif_stmt->close();
        
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'Driver Restored', 'A driver has been restored to the system.', 'success')");
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
$users_sql = "SELECT name, email, mobile, created_at, id FROM users WHERE is_admin = FALSE AND is_deleted = FALSE ORDER BY created_at DESC";
$users_result = $conn->query($users_sql);

// Fetch deleted users
$deleted_users_sql = "SELECT name, email, mobile, created_at, deleted_at, id FROM users WHERE is_admin = FALSE AND is_deleted = TRUE ORDER BY deleted_at DESC";
$deleted_users_result = $conn->query($deleted_users_sql);

// Fetch active drivers
$all_drivers_sql = "SELECT name, email, mobile, created_at, id FROM drivers WHERE is_deleted = FALSE ORDER BY created_at DESC";
$all_drivers_result = $conn->query($all_drivers_sql);

// Fetch fired drivers
$fired_drivers_sql = "SELECT name, email, mobile, created_at, deleted_at, id FROM drivers WHERE is_deleted = TRUE ORDER BY deleted_at DESC";
$fired_drivers_result = $conn->query($fired_drivers_sql);
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
        /* Center align headers and cells for ALL admin tables */
.table-container th, 
.table-container td {
    text-align: center;
    vertical-align: middle;
}

/* Ensure forms and selection boxes inside table cells are centered */
.status-form {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}

/* Specific styling for the Action column buttons to keep them centered */
.table-container td[data-label="Action"] {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.8rem;
    flex-wrap: wrap; /* Allows buttons to wrap on smaller screens */
}

/* Mobile Responsive adjustment: Keep labels left, values right */
@media (max-width: 768px) {
    .table-container td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-align: right;
        padding-left: 50%; /* Space for the label */
        position: relative;
    }
    
    .table-container td::before {
        content: attr(data-label);
        position: absolute;
        left: 1rem;
        width: 45%;
        text-align: left;
        font-weight: 700;
        color: var(--primary);
    }
    
    .status-form {
        justify-content: flex-end;
    }
}
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
    gap: 1.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
    flex-wrap: wrap;
        }
        .sub-tab {
            padding: 0.875rem 0;
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-weight: 600;
    font-size: 0.95rem;
    position: relative;
    transition: all 0.3s ease;
        }
        .sub-tab:hover {
    color: var(--primary);
}
        .sub-tab.active {
           
            color: var(--primary);
            
        }
        .sub-tab.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    box-shadow: 0 2px 8px rgba(14, 165, 233, 0.4);
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
    overflow: hidden;
}
        .modal-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
.modal-body {
    padding: 0;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1;
    min-height: 0;
    max-height: calc(85vh - 200px); /* Ensure scrollable area */
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
.notification-actions {
    display: flex;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    align-items: center;
    flex-wrap: wrap;
    flex-shrink: 0; /* Prevent from shrinking */
    background: var(--bg-secondary); /* Match modal background */
}

.notification-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
    display: none; /* Hidden by default */
}

.notification-checkbox.show {
    display: block;
}

.notification-item-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.2s ease;
}

.notification-item-wrapper:hover {
    background: var(--bg-tertiary);
}

.notification-item-wrapper.unread {
    background: rgba(14, 165, 233, 0.05);
}

.notification-content {
    flex: 1;
    cursor: pointer;
}

.notification-delete-btn {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.25rem;
    transition: all 0.3s ease;
    font-size: 1.2rem;
    line-height: 1;
}

.notification-delete-btn:hover {
    color: #ef4444;
    transform: scale(1.1);
}

.btn-select-mode {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-select-mode:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
}

.btn-cancel-select {
    background: var(--bg-tertiary);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-cancel-select:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-delete-selected {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: none;
}

.btn-delete-selected.show {
    display: inline-block;
}

.btn-delete-selected:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-delete-selected:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.select-all-label {
    display: none;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    cursor: pointer;
    user-select: none;
}

.select-all-label.show {
    display: flex;
}

.notification-count {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-left: auto;
}

.selection-mode-active .notification-item-wrapper {
    cursor: default;
}

.selection-mode-active .notification-content {
    cursor: pointer;
}

.search-box {
    width: 100%;
    padding: 0.875rem 1.25rem;
    background: var(--bg-tertiary);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.search-box:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.search-box::placeholder {
    color: var(--text-secondary);
}

.no-results {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.btn-restore {
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

.btn-restore:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
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
            <?php 
            $notifications->data_seek(0); // Reset pointer
            while($notif = $notifications->fetch_assoc()): 
            ?>
            <div class="notification-item-wrapper <?= !$notif['is_read'] ? 'unread' : '' ?>">
                <div class="notification-content" onclick="markAsRead(<?= $notif['id'] ?>)">
                    <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                    <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                    <div class="notification-time"><?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?></div>
                </div>
                <button class="notification-delete-btn" 
                        onclick="event.stopPropagation(); deleteNotification(<?= $notif['id'] ?>)"
                        title="Delete notification">
                    ×
                </button>
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
        <form id="deleteNotificationsForm" method="POST" action="admin_dashboard.php">
            <div class="notification-actions">
      
                <button type="button" 
                        class="btn-select-mode" 
                        id="selectModeBtn" 
                        onclick="enableSelectionMode()">
                    Select Multiple
                </button>
                
                <!-- Selection mode controls (hidden by default) -->
                <label class="select-all-label" id="selectAllLabel">
                    <input type="checkbox" 
                           class="notification-checkbox show" 
                           id="selectAllNotifications" 
                           onchange="toggleSelectAll()">
                    <span>Select All</span>
                </label>
                
                <button type="submit"
        class="btn-delete-selected"
        id="deleteSelectedBtn"
        name= "delete_notifications"
        onclick="confirmDeleteSelected(event)">
    Delete Selected
</button>

                
                <span class="notification-count" id="selectedCount"></span>
            </div>
            <div class="modal-body" id="allNotificationsBody"></div>
        </form>
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
                                                <form action="update_status.php" method="POST" style="display:inline;"><input type="hidden" name="pickup_id" value="<?= $row['id'] ?>"><input type="hidden" name="new_status" value="Completed"><button type="submit" class="btn-complete">Complete</button></form>
                                                    <form action="update_status.php" method="POST" style="display:inline;" onsubmit="return handleCollectedCancel(event, this)"><input type="hidden" name="pickup_id" value="<?= $row['id'] ?>"><input type="hidden" name="new_status" value="Cancelled"><button type="submit" style="position:relative; left:15px;" class="btn-delete">Cancel</button></form>
                                                
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
       <!-- Drivers Tab with Sub-tabs -->
<div id="drivers-tab" class="main-tab-content">
    <section class="dashboard-section">
        <h2>Manage Drivers</h2>
        
        <div class="sub-tabs">
            
            <button class="sub-tab active" onclick="switchDriverSubTab('driver-list')">Driver List</button>
            <button class="sub-tab" onclick="switchDriverSubTab('add-driver')">Add Driver</button>
            <button class="sub-tab" onclick="switchDriverSubTab('fired-drivers')">Fired Drivers</button>
        </div>

        <!-- Add Driver Sub-tab -->
<div id="add-driver-subtab" class="sub-tab-content">
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
            <label for="driver_mobile">Mobile Number</label>
            <input type="text" id="driver_mobile" name="driver_mobile" required pattern="[0-9]{10,15}" placeholder="e.g. 9876543210">
        </div>
        <div class="form-group">
            <label for="driver_password">Password</label>
            <input type="password" id="driver_password" name="driver_password" required>
        </div>
        <button type="submit" name="create_driver" class="btn btn-primary" style="grid-column: 1 / -1;">Add Driver</button>
    </form>
</div>

        <!-- Driver List Sub-tab -->
        <div id="driver-list-subtab" class="sub-tab-content active">
            <div class="search-container">
                <input type="text" 
                       class="search-box" 
                       id="driverSearch" 
                       placeholder="Search drivers by name or email..." 
                       onkeyup="searchTable('driverSearch', 'driverTable')">
            </div>
            <div class="table-container">
                <table id="driverTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
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
                                <td data-label="Mobile"><?= htmlspecialchars($row['mobile']) ?>
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
                <div id="driverTable-no-results" class="no-results" style="display: none;">
                    No drivers found matching your search.
                </div>
            </div>
        </div>

        <!-- Fired Drivers Sub-tab -->
        <div id="fired-drivers-subtab" class="sub-tab-content">
            <div class="search-container">
                <input type="text" 
                       class="search-box" 
                       id="firedDriverSearch" 
                       placeholder="Search fired drivers by name or email..." 
                       onkeyup="searchTable('firedDriverSearch', 'firedDriverTable')">
            </div>
            <div class="table-container">
                <table id="firedDriverTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Registration Date</th>
                            <th>Fired Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($fired_drivers_result->num_rows > 0): ?>
                            <?php while($row = $fired_drivers_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Name"><?= htmlspecialchars($row['name']) ?></td>
                                <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
                                <td data-label="Mobile"><?= htmlspecialchars($row['mobile']) ?></td>
                                <td data-label="Registration Date"><?= date("M j, Y", strtotime($row['created_at'])) ?></td>
                                <td data-label="Fired Date"><?= date("M j, Y", strtotime($row['deleted_at'])) ?></td>
                                <td data-label="Action">
                                    <button class="btn-restore" onclick="confirmRestore('driver', <?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>')">Restore</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No fired drivers.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div id="firedDriverTable-no-results" class="no-results" style="display: none;">
                    No fired drivers found matching your search.
                </div>
            </div>
        </div>
    </section>
</div>

            <!-- Users Tab with Sub-tabs -->
           <!-- Users Tab with Sub-tabs -->
<div id="users-tab" class="main-tab-content">
    <section class="dashboard-section">
        <h2>Manage Users</h2>
        
        <div class="sub-tabs">
            <button class="sub-tab active" onclick="switchUserSubTab('active-users')">Active Users</button>
            <button class="sub-tab" onclick="switchUserSubTab('deleted-users')">Deleted Users</button>
        </div>

        <!-- Active Users Sub-tab -->
        <div id="active-users-subtab" class="sub-tab-content active">
            <div class="search-container">
                <input type="text" 
                       class="search-box" 
                       id="userSearch" 
                       placeholder="Search users by name or email..." 
                       onkeyup="searchTable('userSearch', 'userTable')">
            </div>
            <div class="table-container">
                <table id="userTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
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
                                <td data-label="Mobile"><?= htmlspecialchars($row['mobile']) ?></td>
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
                <div id="userTable-no-results" class="no-results" style="display: none;">
                    No users found matching your search.
                </div>
            </div>
        </div>

        <!-- Deleted Users Sub-tab -->
        <div id="deleted-users-subtab" class="sub-tab-content">
            <div class="search-container">
                <input type="text" 
                       class="search-box" 
                       id="deletedUserSearch" 
                       placeholder="Search deleted users by name or email..." 
                       onkeyup="searchTable('deletedUserSearch', 'deletedUserTable')">
            </div>
            <div class="table-container">
                <table id="deletedUserTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Registration Date</th>
                            <th>Deleted Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($deleted_users_result->num_rows > 0): ?>
                            <?php while($row = $deleted_users_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Name"><?= htmlspecialchars($row['name']) ?></td>
                                <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
                                <td data-label="Mobile"><?= htmlspecialchars($row['mobile']) ?></td>
                                <td data-label="Registration Date"><?= date("M j, Y", strtotime($row['created_at'])) ?></td>
                                <td data-label="Deleted Date"><?= date("M j, Y", strtotime($row['deleted_at'])) ?></td>
                                <td data-label="Action">
                                    <button class="btn-restore" onclick="confirmRestore('user', <?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>')">Restore</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No deleted users.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div id="deletedUserTable-no-results" class="no-results" style="display: none;">
                    No deleted users found matching your search.
                </div>
            </div>
        </div>
    </section>
</div>
        </div>
    </main>

    <script src="theme.js"></script>
    <script>
        // Search functionality
function searchTable(searchInputId, tableId) {
    const input = document.getElementById(searchInputId);
    const filter = input.value.toUpperCase();
    const table = document.getElementById(tableId);
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = tbody.getElementsByTagName('tr');
    const noResults = document.getElementById(tableId + '-no-results');
    
    let visibleCount = 0;
    
    for (let i = 0; i < rows.length; i++) {
        // Skip "no data" placeholder rows (rows with 1 cell spanning the whole table)
        if (rows[i].cells.length === 1 && rows[i].cells[0].getAttribute('colspan')) {
            rows[i].style.display = 'none';
            continue;
        }
        
        // Target Name (Index 0), Email (Index 1), and Mobile (Index 2)
        const nameCell = rows[i].cells[0];
        const emailCell = rows[i].cells[1];
        const mobileCell = rows[i].cells[2];
        
        if (nameCell && emailCell && mobileCell) {
            const nameText = (nameCell.textContent || nameCell.innerText).toUpperCase();
            const emailText = (emailCell.textContent || emailCell.innerText).toUpperCase();
            const mobileText = (mobileCell.textContent || mobileCell.innerText).toUpperCase();
            
            // Show row if query matches any of the three fields
            if (nameText.indexOf(filter) > -1 || 
                emailText.indexOf(filter) > -1 ||
                mobileText.indexOf(filter) > -1) {
                rows[i].style.display = '';
                visibleCount++;
            } else {
                rows[i].style.display = 'none';
            }
        }
    }
    
    // Manage visibility of the 'No Results' message container
    if (visibleCount === 0 && filter !== '') {
        table.style.display = 'none';
        if (noResults) noResults.style.display = 'block';
    } else {
        table.style.display = '';
        if (noResults) noResults.style.display = 'none';
    }
}

// Restore confirmation
function confirmRestore(type, id, name) {
    const message = type === 'user' 
        ? `Are you sure you want to restore user "${name}"?`
        : `Are you sure you want to restore driver "${name}"?`;
    
    showConfirm(message, () => {
        const param = type === 'user' ? 'restore_user' : 'restore_driver';
        window.location.href = `admin_dashboard.php?${param}=${id}`;
    });
}

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
// Enhanced fetchAllNotifications with better error handling
function fetchAllNotifications() {
    console.log('Fetching all notifications...');
    
    const body = document.getElementById('allNotificationsBody');
    const actionsDiv = document.querySelector('.notification-actions');
    
    // Show loading state
    body.innerHTML = '<div class="notification-empty">Loading notifications...</div>';
    
    fetch('get_all_notifications.php')
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Get as text first to see raw response
        })
        .then(text => {
            console.log('Raw response:', text);
            
            // Try to parse as JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response');
            }
        })
        .then(data => {
            console.log('Parsed data:', data);
            
            // Check for error in response
            if (data.error) {
                body.innerHTML = `<div class="notification-empty">Error: ${data.error}</div>`;
                if (actionsDiv) actionsDiv.style.display = 'none';
                return;
            }
            
            // Check if empty
            if (!Array.isArray(data) || data.length === 0) {
                body.innerHTML = '<div class="notification-empty">No notifications</div>';
                if (actionsDiv) actionsDiv.style.display = 'none';
                return;
            }
            
            // Show actions
            if (actionsDiv) actionsDiv.style.display = 'flex';
            
            // Render notifications
            body.innerHTML = data.map(notif => `
                <div class="notification-item-wrapper ${!notif.is_read ? 'unread' : ''}">
                    <input type="checkbox" 
                           class="notification-checkbox" 
                           name="notification_ids[]" 
                           value="${notif.id}"
                           onclick="event.stopPropagation();" 
                           onchange="updateDeleteButton()">
                    <div class="notification-content" onclick="handleNotifClick(event, ${notif.id})">
                        <div class="notification-title">${escapeHtml(notif.title)}</div>
                        <div class="notification-message">${escapeHtml(notif.message)}</div>
                        <div class="notification-time">${escapeHtml(notif.created_at)}</div>
                    </div>
                    <button type="button" 
                            class="notification-delete-btn" 
                            onclick="event.stopPropagation(); deleteNotification(${notif.id})"
                            title="Delete notification">
                        ×
                    </button>
                </div>
            `).join('');
            
            // Reset selection mode
            disableSelectionMode();
        })
        .catch(error => {
            console.error('Fetch error:', error);
            body.innerHTML = `<div class="notification-empty">Error loading notifications: ${error.message}</div>`;
            if (actionsDiv) actionsDiv.style.display = 'none';
        });
}

// Helper function to escape HTML and prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show all notifications modal
function showAllNotifications() {
    const modal = document.getElementById('allNotificationsModal');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (modal) {
        modal.classList.add('show');
        fetchAllNotifications();
    } else {
        console.error('Modal not found!');
    }
    
    if (dropdown) {
        dropdown.classList.remove('show');
    }
}

// Close modal
function closeAllNotifications() {
    const modal = document.getElementById('allNotificationsModal');
    if (modal) {
        modal.classList.remove('show');
    }
    disableSelectionMode();
}

// Toggle notifications dropdown
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Mark all as read
function markAllRead() {
    showConfirm('Mark all notifications as read?', () => {
        window.location.href = 'admin_dashboard.php?mark_all_read=1';
    });
}

// Mark single notification as read
function markAsRead(notifId) {
    window.location.href = `admin_dashboard.php?mark_read=1&notif_id=${notifId}`;
}

// Delete single notification
function deleteNotification(notifId) {
    showConfirm('Delete this notification?', () => {
        window.location.href = `admin_dashboard.php?delete_notif=${notifId}`;
    });
}

// Selection mode state
let selectionModeActive = false;

// Enable selection mode
function enableSelectionMode() {
    selectionModeActive = true;
    
    const checkboxes = document.querySelectorAll('.modal-body .notification-checkbox');
    const selectModeBtn = document.getElementById('selectModeBtn');
    const selectAllLabel = document.getElementById('selectAllLabel');
    const cancelBtn = document.getElementById('cancelSelectBtn');
    const modalBody = document.getElementById('allNotificationsBody');
    
    checkboxes.forEach(cb => cb.classList.add('show'));
    
    if (selectModeBtn) selectModeBtn.style.display = 'none';
    if (selectAllLabel) selectAllLabel.classList.add('show');
    if (cancelBtn) cancelBtn.style.display = 'inline-block';
    if (modalBody) modalBody.classList.add('selection-mode-active');
    
    updateDeleteButton();
}

// Disable selection mode
function disableSelectionMode() {
    selectionModeActive = false;
    
    const checkboxes = document.querySelectorAll('.modal-body .notification-checkbox');
    const selectAll = document.getElementById('selectAllNotifications');
    const selectModeBtn = document.getElementById('selectModeBtn');
    const selectAllLabel = document.getElementById('selectAllLabel');
    const cancelBtn = document.getElementById('cancelSelectBtn');
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const selectedCount = document.getElementById('selectedCount');
    const modalBody = document.getElementById('allNotificationsBody');
    
    checkboxes.forEach(cb => {
        cb.classList.remove('show');
        cb.checked = false;
    });
    
    if (selectAll) selectAll.checked = false;
    if (selectModeBtn) selectModeBtn.style.display = 'inline-block';
    if (selectAllLabel) selectAllLabel.classList.remove('show');
    if (cancelBtn) cancelBtn.style.display = 'none';
    if (deleteBtn) deleteBtn.classList.remove('show');
    if (selectedCount) selectedCount.textContent = '';
    if (modalBody) modalBody.classList.remove('selection-mode-active');
}

// Toggle select all
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAllNotifications');
    const checkboxes = document.querySelectorAll('.modal-body .notification-checkbox');
    
    if (selectAll && checkboxes) {
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateDeleteButton();
    }
}
function confirmDeleteSelected(event) {
    event.preventDefault();

    const checkedBoxes = document.querySelectorAll(
        '.modal-body .notification-checkbox:checked'
    );

    if (checkedBoxes.length === 0) {
        showAlert("Please select at least one notification.", 'warning');
        return false;
    }

    const form = document.getElementById('deleteNotificationsForm');
    
    showConfirm(`Delete ${checkedBoxes.length} selected notification(s)?`, () => {
        // Create a temporary input to trigger the form submission with the correct button name
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_notifications';
        input.value = '1';
        form.appendChild(input);
        
        // Submit the form
        form.submit();
    });

    return false;
}



// Update delete button state
function updateDeleteButton() {
    if (!selectionModeActive) return;
    
    const checkboxes = document.querySelectorAll('.modal-body .notification-checkbox');
    const checkedBoxes = document.querySelectorAll('.modal-body .notification-checkbox:checked');
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const selectedCount = document.getElementById('selectedCount');
    const selectAll = document.getElementById('selectAllNotifications');
    
    if (checkedBoxes.length > 0) {
        if (deleteBtn) deleteBtn.classList.add('show');
        if (selectedCount) selectedCount.textContent = `${checkedBoxes.length} selected`;
    } else {
        if (deleteBtn) deleteBtn.classList.remove('show');
        if (selectedCount) selectedCount.textContent = '';
    }
    
    if (selectAll && checkboxes.length > 0) {
        selectAll.checked = checkedBoxes.length === checkboxes.length;
        selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
    }
}

// Handle notification click
function handleNotifClick(event, notifId) {
    if (selectionModeActive) {
        const wrapper = event.currentTarget.closest('.notification-item-wrapper');
        const checkbox = wrapper.querySelector('.notification-checkbox');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateDeleteButton();
        }
    } else {
        markAsRead(notifId);
    }
}


document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notificationDropdown');
    const bell = document.querySelector('.notification-bell');
    
    if (dropdown && bell && !bell.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Close modal when clicking outside
document.getElementById('allNotificationsModal')?.addEventListener('click', function(event) {
    if (event.target === this) {
        closeAllNotifications();
    }
});

console.log('Notification functions loaded successfully');

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
    const mobile = form.querySelector('#driver_mobile').value.trim(); // Added
    const password = form.querySelector('#driver_password').value.trim();
    
    if (!name || !email || !mobile || !password) {
        showAlert('All fields (including mobile) are required', 'warning');
        return false;
    }
    
    return true;
}
    function handleNotifClick(event, notifId) {
    if (selectionModeActive) {
        // Toggle the checkbox for this item instead of navigating
        const wrapper = event.currentTarget.closest('.notification-item-wrapper');
        const checkbox = wrapper.querySelector('.notification-checkbox');
        checkbox.checked = !checkbox.checked;
        updateDeleteButton();
    } else {
        // Normal behavior: navigate to mark as read
        markAsRead(notifId);
    }
}
// Add this inside your <script> tag
window.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($success_message)): ?>
        showAlert("<?= addslashes($success_message) ?>", "success");
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        showAlert("<?= addslashes($error_message) ?>", "error");
    <?php endif; ?>
});

    </script>
</body>
</html>