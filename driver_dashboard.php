<?php
require 'db.php';

if (!isset($_SESSION["driver_id"])) {
    header("Location: driver_login.php");
    exit();
}

$driver_id = $_SESSION['driver_id'];
$message = '';
$message_type = '';

// Delete selected notifications
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_notifications'])) {
    if (!empty($_POST['notification_ids']) && is_array($_POST['notification_ids'])) {
        $ids = array_map('intval', $_POST['notification_ids']);
        $count = count($ids);
        $placeholders = implode(',', array_fill(0, $count, '?'));
        
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id IN ($placeholders) AND driver_id = ?");
        
        $types = str_repeat('i', $count + 1);
        $params = array_merge($ids, [$driver_id]);
        
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = $count . " notification(s) deleted successfully!";
        }
        $stmt->close();
    }
    header("Location: driver_dashboard.php");
    exit();
}

// Delete single notification
if (isset($_GET['delete_notif'])) {
    $notif_id = intval($_GET['delete_notif']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("ii", $notif_id, $driver_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Notification deleted successfully!";
    }
    $stmt->close();
    header("Location: driver_dashboard.php");
    exit();
}
// Mark notification as read
if (isset($_GET['mark_read']) && isset($_GET['notif_id'])) {
    $notif_id = intval($_GET['notif_id']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("ii", $notif_id, $driver_id);
    $stmt->execute();
    $stmt->close();
    header("Location: driver_dashboard.php");
    exit();
}

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE driver_id = ?");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $stmt->close();
    header("Location: driver_dashboard.php");
    exit();
}

// Handle Intermediate State: Out for Pickup
// Handle Intermediate State: Out for Pickup
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['out_for_pickup'])) {
    $pickup_id = intval($_POST['pickup_id']);
    
    $stmt = $conn->prepare("UPDATE pickups SET status = 'Out for Pickup' WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("ii", $pickup_id, $driver_id);
    
    if ($stmt->execute()) {
        // Fetch details for user notification
        $details_stmt = $conn->prepare("SELECT user_id, area FROM pickups WHERE id = ?");
        $details_stmt->bind_param("i", $pickup_id);
        $details_stmt->execute();
        $pickup_data = $details_stmt->get_result()->fetch_assoc();
        $details_stmt->close();

        $user_notif_message = "Your driver is now out for pickup for your waste at {$pickup_data['area']}. Please be ready!";
        $user_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Driver Out for Pickup', ?, 'info')");
        $user_notif->bind_param("is", $pickup_data['user_id'], $user_notif_message);
        $user_notif->execute();
        $user_notif->close();

        $_SESSION['success_message'] = "Status updated: You are now out for pickup!";
    }
    $stmt->close();
    
    // Redirect to prevent resubmission
    header("Location: driver_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['collect_pickup'])) {
    $pickup_id = intval($_POST['pickup_id']);
    
    $details_stmt = $conn->prepare("SELECT p.*, u.name as user_name, u.id as user_id FROM pickups p JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.driver_id = ?");
    $details_stmt->bind_param("ii", $pickup_id, $driver_id);
    $details_stmt->execute();
    $pickup_data = $details_stmt->get_result()->fetch_assoc();
    $details_stmt->close();
    
    $stmt = $conn->prepare("UPDATE pickups SET status = 'Collected by Driver' WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("ii", $pickup_id, $driver_id);
    
    if ($stmt->execute() && $pickup_data) {
        $user_notif_message = "Your waste from {$pickup_data['area']} has been collected by the driver.";
        $user_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Waste Collected', ?, 'success')");
        $user_notif->bind_param("is", $pickup_data['user_id'], $user_notif_message);
        $user_notif->execute();
        $user_notif->close();
        
        $driver_notif_message = "You have successfully collected waste from {$pickup_data['user_name']} at {$pickup_data['area']}.";
        $driver_notif = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Collection Confirmed', ?, 'success')");
        $driver_notif->bind_param("is", $driver_id, $driver_notif_message);
        $driver_notif->execute();
        $driver_notif->close();
        
        $admin_notif_message = "Pickup from {$pickup_data['user_name']} at {$pickup_data['area']} has been collected.";
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) SELECT id, 'Pickup Collected', ?, 'info' FROM users WHERE is_admin = TRUE LIMIT 1");
        $admin_notif->bind_param("s", $admin_notif_message);
        $admin_notif->execute();
        $admin_notif->close();
        
        $_SESSION['success_message'] = "Pickup successfully marked as collected!";
    } else {
        $_SESSION['error_message'] = "Error: Database update failed.";
    }
    $stmt->close();
    
    // Redirect to prevent resubmission
    header("Location: driver_dashboard.php");
    exit();
}

// Display messages from session and clear them
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = "error";
    unset($_SESSION['error_message']);
}

// Fetch recent 3 notifications for dropdown
$notif_sql = "SELECT * FROM notifications WHERE driver_id = ? ORDER BY created_at DESC LIMIT 3";
$stmt_notif = $conn->prepare($notif_sql);
$stmt_notif->bind_param("i", $driver_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result();

// Count unread notifications
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE driver_id = ? AND is_read = FALSE";
$stmt_unread = $conn->prepare($unread_sql);
$stmt_unread->bind_param("i", $driver_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['count'];
$stmt_unread->close();

// UPDATED QUERY: Include 'Out for Pickup' status in active list
$assigned_sql = "SELECT p.*, u.name as user_name, u.email as user_email
                FROM pickups p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.driver_id = ? AND p.status IN ('Assigned', 'Out for Pickup') 
                ORDER BY p.pickup_date ASC";
$stmt_assigned = $conn->prepare($assigned_sql);
$stmt_assigned->bind_param("i", $driver_id);
$stmt_assigned->execute();
$assigned_result = $stmt_assigned->get_result();

// Check if driver wants to see all history
$show_all_history = isset($_GET['show_all_history']) ? true : false;
$history_limit = $show_all_history ? "" : "LIMIT 3";

$history_sql = "SELECT p.*, u.name as user_name 
                FROM pickups p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.driver_id = ? AND p.status IN ('Completed', 'Cancelled', 'Collected by Driver') 
                ORDER BY p.updated_at DESC $history_limit";
$stmt_history = $conn->prepare($history_sql);
$stmt_history->bind_param("i", $driver_id);
$stmt_history->execute();
$history_result = $stmt_history->get_result();
$total_history_count = $history_result->num_rows;

// Get total count for "Show All" button
if (!$show_all_history) {
    $count_sql = "SELECT COUNT(*) as total FROM pickups 
                  WHERE driver_id = ? AND status IN ('Completed', 'Cancelled', 'Collected by Driver')";
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param("i", $driver_id);
    $stmt_count->execute();
    $total_history = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();
} else {
    $total_history = $total_history_count;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - EcoWaste</title>
    <link rel="stylesheet" href="style.css?v=2">
    <link rel="stylesheet" href="dashboard-style.css?v=2">
    <link rel="stylesheet" href="admin-style.css?v=2">
    <link rel="stylesheet" href="theme.css?v=2">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css?v=2" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        html {
    scroll-behavior: smooth;
}
        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(139, 92, 246, 0.15));
            border: 1px solid var(--primary);
            border-radius: 12px;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .location-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.3);
        }
        .contact-info {
            background: var(--bg-tertiary);
            padding: 0.75rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        .contact-info a {
            color: var(--primary);
            text-decoration: none;
        }
        .directions-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }
        .directions-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
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
            align-items: center; justify-content: center;
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
        .btn-out {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-out:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }
        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .status-pill.assigned { background: rgba(14, 165, 233, 0.2); color: var(--primary); }
        .status-pill.out { background: rgba(139, 92, 246, 0.2); color: var(--secondary); }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <a href="driver_dashboard.php" class="logo">Driver Panel</a>
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
            $notifications->data_seek(0);
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
        <form id="deleteNotificationsForm" method="POST" action="driver_dashboard.php">
            <div class="notification-actions">
                <button type="button" 
                        class="btn-select-mode" 
                        id="selectModeBtn" 
                        onclick="enableSelectionMode()">
                    Select Multiple
                </button>
                
                <label class="select-all-label" id="selectAllLabel">
                    <input type="checkbox" 
                           class="notification-checkbox show" 
                           id="selectAllNotifications" 
                           onchange="toggleSelectAll()">
                    <span>Select All</span>
                </label>
                
                <button type="button" 
                        class="btn-cancel-select" 
                        id="cancelSelectBtn" 
                        onclick="disableSelectionMode()"
                        style="display: none;">
                    Cancel
                </button>
                
                <button type="submit" 
        class="btn-delete-selected" 
        id="deleteSelectedBtn"
        name="delete_notifications"
        >Delete Selected</button>
                
                <span class="notification-count" id="selectedCount"></span>
            </div>
            <div class="modal-body" id="allNotificationsBody"></div>
        </form>
    </div>
</div>

    <main class="dashboard-main">
        <div class="container">
            <div class="dashboard-header">
                <h1>Welcome, Driver <?= htmlspecialchars($_SESSION['driver_name']) ?>!</h1>
            </div>

            <section class="dashboard-section">
                <h2>Your Assigned Pickups</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User Details</th>
                                <th>Location</th>
                                <th>Pickup Details</th>
                                <th>Map Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($assigned_result->num_rows > 0): while($row = $assigned_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="User Details">
                                    <?php if($row['status'] == 'Out for Pickup'): ?>
                                        <span class="status-pill out">OUT FOR PICKUP</span><br>
                                    <?php else: ?>
                                        <span class="status-pill assigned">ASSIGNED</span><br>
                                    <?php endif; ?>
                                    <strong><?= htmlspecialchars($row['user_name']) ?></strong>
                                    <div class="contact-info">
                                        📧 <a href="mailto:<?= htmlspecialchars($row['user_email']) ?>"><?= htmlspecialchars($row['user_email']) ?></a>
                                    </div>
                                </td>
                                <td data-label="Location">
                                    <strong><?= htmlspecialchars($row['area']) ?></strong><br>
                                    <small><?= htmlspecialchars($row['city']) ?></small>
                                </td>
                                <td data-label="Pickup Details">
                                    📅 <?= htmlspecialchars($row['pickup_date']) ?><br>
                                    ⏰ <?= htmlspecialchars($row['time_slot']) ?><br>
                                    🗑️ <small><?= htmlspecialchars($row['waste_type']) ?></small>
                                </td>
                                <td data-label="Map Location">
                                    <?php if ($row['latitude'] && $row['longitude']): ?>
                                        <button class="location-badge" onclick="showLocationModal(<?= $row['latitude'] ?>, <?= $row['longitude'] ?>, '<?= htmlspecialchars($row['user_name']) ?>', '<?= htmlspecialchars($row['area']) ?>')">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                <circle cx="12" cy="10" r="3"/>
                                            </svg>
                                            View Map
                                        </button>
                                        <button class="directions-btn" onclick="openGoogleMapsDirections(<?= $row['latitude'] ?>, <?= $row['longitude'] ?>)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                                                <path d="M2 17l10 5 10-5"/>
                                                <path d="M2 12l10 5 10-5"/>
                                            </svg>
                                            Directions
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">No location pinned</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Action">
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <?php if($row['status'] == 'Assigned'): ?>
                                            <form method="POST" action="driver_dashboard.php">
                                                <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="out_for_pickup" class="btn-out" style="width: 100%;">Mark Out for Pickup</button>
                                            </form>
                                        <?php elseif($row['status'] == 'Out for Pickup'): ?>
                                            <form method="POST" action="driver_dashboard.php" onsubmit="return handleCollect(event, this);">
                                                <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="collect_pickup" class="btn-collect" style="width: 100%;">Mark as Collected</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5">No pending pickups assigned to you.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="history" class="dashboard-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin: 0;">Your Pickup History</h2>
        <?php if (!$show_all_history && $total_history > 3): ?>
            <a href="driver_dashboard.php?show_all_history=1#history" class="btn btn-secondary" style="text-decoration: none;">
                Show All (<?= $total_history ?>)
            </a>
        <?php elseif ($show_all_history): ?>
            <a href="driver_dashboard.php#history" class="btn btn-secondary" style="text-decoration: none;">
                Show Recent (5)
            </a>
        <?php endif; ?>
    </div>
    <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Location</th>
                                <th>Pickup Date</th>
                                <th>Final Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history_result->num_rows > 0): while($row = $history_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="User"><?= htmlspecialchars($row['user_name']) ?></td>
                                <td data-label="Location"><strong><?= htmlspecialchars($row['area']) ?></strong><br><small><?= htmlspecialchars($row['city']) ?></small></td>
                                <td data-label="Pickup Date"><?= htmlspecialchars($row['pickup_date']) ?></td>
                                <td data-label="Final Status">
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4"><?= $show_all_history ? 'No pickup history found.' : 'No recent pickup history. Click "Show All" to see older records.' ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <div id="locationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); padding: 2rem; border-radius: 24px; max-width: 900px; width: 90%; max-height: 90vh; overflow: auto; border: 2px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <h3 style="margin: 0; color: var(--text-primary);" id="modalTitle">Pickup Location</h3>
                    <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary); font-size: 0.9rem;" id="modalSubtitle"></p>
                </div>
                <button onclick="closeLocationModal()" style="background: var(--bg-tertiary); border: 2px solid var(--border-color); color: var(--text-primary); cursor: pointer; font-size: 1.5rem; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>
            <div id="viewMap" style="height: 450px; border-radius: 16px; border: 2px solid var(--border-color);"></div>
        </div>
    </div>

    <script src="theme.js"></script>
    <script>
        let viewMapInstance;
        let viewMarker;

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
        window.location.href = 'driver_dashboard.php?mark_all_read=1';
    });
}

// Mark single notification as read
function markAsRead(notifId) {
    window.location.href = `driver_dashboard.php?mark_read=1&notif_id=${notifId}`;
}

// Delete single notification
function deleteNotification(notifId) {
    showConfirm('Delete this notification?', () => {
        window.location.href = `driver_dashboard.php?delete_notif=${notifId}`;
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


// Updated handler for the Delete Selected button


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
                window.location.href = `driver_dashboard.php?${param}=${id}`;
            });
        }

        function handleCollectedCancel(event, form) {

            event.preventDefault();
            showConfirm('Are you sure you want to cancel this pickup?', () => {
                form.submit();
            });
            return false;
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

        function showLocationModal(lat, lng, userName, address) {
            document.getElementById('locationModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = `Pickup Location: ${userName}`;
            document.getElementById('modalSubtitle').textContent = `📍 ${address}`;
            
            setTimeout(() => {
                if (!viewMapInstance) {
                    viewMapInstance = L.map('viewMap').setView([lat, lng], 16);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(viewMapInstance);
                } else {
                    viewMapInstance.setView([lat, lng], 16);
                    if (viewMarker) {
                        viewMapInstance.removeLayer(viewMarker);
                    }
                }
                
                viewMarker = L.marker([lat, lng]).addTo(viewMapInstance)
                    .bindPopup(`<b>${userName}</b><br>${address}`)
                    .openPopup();
                
                setTimeout(() => viewMapInstance.invalidateSize(), 100);
            }, 100);
        }

        function closeLocationModal() {
            document.getElementById('locationModal').style.display = 'none';
        }

        function openGoogleMapsDirections(lat, lng) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;
            window.open(url, '_blank');
        }

        function handleCollect(event, form) {
            event.preventDefault();
            
            showConfirm('Is the waste collected?', () => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'collect_pickup';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
                form.submit();
            });
            
            return false;
        }

        <?php if($message): ?>
            if(window.showAlert) {
                showAlert("<?= addslashes($message) ?>", "<?= $message_type ?>");
            } else {
                alert("<?= addslashes($message) ?>");
            }
        <?php endif; ?>
    </script>
</body>
</html>
<?php 
    $stmt_assigned->close();
    $stmt_history->close();
    $stmt_notif->close();
    $conn->close(); 
?>