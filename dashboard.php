<?php
require 'db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Delete selected notifications
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_notifications'])) {
    if (!empty($_POST['notification_ids']) && is_array($_POST['notification_ids'])) {
        $ids = array_map('intval', $_POST['notification_ids']);
        $count = count($ids);
        $placeholders = implode(',', array_fill(0, $count, '?'));
        
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?");
        
        $types = str_repeat('i', $count + 1);
        $params = array_merge($ids, [$user_id]);
        
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = $count . " notification(s) deleted successfully!";
        }
        $stmt->close();
    }
    header("Location: dashboard.php");
    exit();
}

// Delete single notification
if (isset($_GET['delete_notif'])) {
    $notif_id = intval($_GET['delete_notif']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Notification deleted successfully!";
    }
    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Handle new pickup request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pickup_date'])) {
    $area = $_POST['area'];
    $city = $_POST['city'];
    $pickup_date = $_POST['pickup_date'];
    $time_slot = $_POST['time_slot'];
    $waste_type = $_POST['waste_type'];
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null;

    $stmt = $conn->prepare("INSERT INTO pickups (user_id, area, city, pickup_date, time_slot, waste_type, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssdd", $user_id, $area, $city, $pickup_date, $time_slot, $waste_type, $latitude, $longitude);
    
    if($stmt->execute()){
        // Notify Admin about new pickup request
        $user_name = $_SESSION['user_name'];
        $notif_title = "New Pickup Request";
        $notif_message = "User $user_name has scheduled a new pickup in $area, $city for $pickup_date ($time_slot).";
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) SELECT id, ?, ?, 'info' FROM users WHERE is_admin = TRUE LIMIT 1");
        $admin_notif->bind_param("ss", $notif_title, $notif_message);
        $admin_notif->execute();
        $admin_notif->close();
        
        // Redirect to prevent resubmission
        $_SESSION['success_message'] = "Pickup scheduled successfully with location!";
        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error scheduling pickup.";
        header("Location: dashboard.php");
        exit();
    }
    $stmt->close();
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

// Mark notification as read
if (isset($_GET['mark_read']) && isset($_GET['notif_id'])) {
    $notif_id = intval($_GET['notif_id']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Fetch recent 3 notifications
$notif_sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3";
$stmt_notif = $conn->prepare($notif_sql);
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result();

// Count unread notifications
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE";
$stmt_unread = $conn->prepare($unread_sql);
$stmt_unread->bind_param("i", $user_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['count'];
$stmt_unread->close();

// Fetch upcoming pickups - Updated to include 'Out for Pickup'
$upcoming_sql = "SELECT id, pickup_date, time_slot, waste_type, status, latitude, longitude, driver_id FROM pickups 
                 WHERE user_id = ? AND status IN ('Scheduled', 'Assigned', 'Out for Pickup') AND pickup_date >= CURDATE()
                 ORDER BY pickup_date ASC";
$stmt_upcoming = $conn->prepare($upcoming_sql);
$stmt_upcoming->bind_param("i", $user_id);
$stmt_upcoming->execute();
$upcoming_result = $stmt_upcoming->get_result();

// Fetch pickup history
// Check if user wants to see all history
$show_all_history = isset($_GET['show_all_history']) ? true : false;
$history_limit = $show_all_history ? "" : "LIMIT 3";

// Fetch pickup history
$history_sql = "SELECT id, pickup_date, time_slot, waste_type, status, driver_id FROM pickups 
                WHERE user_id = ? AND status IN ('Completed', 'Cancelled', 'Collected', 'Collected by Driver')
                ORDER BY updated_at DESC $history_limit";
$stmt_history = $conn->prepare($history_sql);
$stmt_history->bind_param("i", $user_id);
$stmt_history->execute();
$history_result = $stmt_history->get_result();
$total_history_count = $history_result->num_rows;

// Get total count for "Show All" button
if (!$show_all_history) {
    $count_sql = "SELECT COUNT(*) as total FROM pickups 
                  WHERE user_id = ? AND status IN ('Completed', 'Cancelled', 'Collected', 'Collected by Driver')";
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param("i", $user_id);
    $stmt_count->execute();
    $total_history = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();
} else {
    $total_history = $total_history_count;
}


function getStatusStep($status) {
    switch ($status) {
        case 'Scheduled': return 1;
        case 'Assigned': return 2;
        case 'Out for Pickup': return 3;
        case 'Collected': 
        case 'Collected by Driver': return 4;
        case 'Completed': return 5;
        case 'Cancelled': return -1;
        default: return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EcoWaste Solutions</title>
    <link rel="stylesheet" href="style.css?v=2">
    <link rel="stylesheet" href="dashboard-style.css?v=2">
    <link rel="stylesheet" href="admin-style.css?v=2">
    <link rel="stylesheet" href="theme.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css?v=2" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        html {
    scroll-behavior: smooth;
}
        #map {
            height: 400px;
            width: 100%;
            border-radius: 16px;
            border: 2px solid var(--border-color);
            margin-bottom: 1.5rem;
            z-index: 1;
        }
        .search-container {
            position: relative;
            margin-bottom: 1rem;
        }
        .search-input {
            width: 100%;
            padding: 1rem 3rem 1rem 1.25rem;
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 20px rgba(14, 165, 233, 0.2);
        }
        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 0.625rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            color: white;
        }
        .location-info {
            background: var(--bg-tertiary);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: none;
        }
        .location-info.show {
            display: block;
        }
        .location-info p {
            margin: 0.5rem 0;
            color: var(--text-secondary);
        }
        .location-info strong {
            color: var(--primary);
        }
        .btn-get-location {
            background: linear-gradient(135deg, var(--accent), #d97706);
            color: white;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .btn-get-location:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }
        .map-instructions {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(139, 92, 246, 0.1));
            border: 1px solid var(--primary);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .map-instructions p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
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
        
        /* All Notifications Modal */
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

        /* FIXED HORIZONTAL GRADIENT TIMELINE */
        .status-badge.status-out-for-pickup {
            background: linear-gradient(135deg, #a855f7, #7c3aed);
            color: white;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }

        .timeline-container {
            margin-top: 1rem;
            padding: 1.5rem 1rem;
            background: var(--bg-tertiary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .timeline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    padding: 0;
}

/* Background Track */
.timeline::before {
    content: '';
    position: absolute;
    top: 18px;
    left: 18px;
    right: 18px;
            height: 4px;
            background: var(--bg-secondary);
            border-radius: 10px;
            z-index: 0;
        }

        /* Animated Progress Bar */
        .timeline-progress {
    position: absolute;
    top: 18px;
    left: 18px;
            height: 4px;
            background: linear-gradient(90deg, 
                #06b6d4 0%, 
                #3b82f6 25%, 
                #8b5cf6 50%, 
                #10b981 75%, 
                #22c55e 100%);
            border-radius: 10px;
            z-index: 1;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 10px rgba(14, 165, 233, 0.4);
        }

        .timeline-step {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
        }

        .timeline-node {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-secondary);
            border: 3px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 700;
            transition: all 0.4s ease;
            color: var(--text-secondary);
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
    display: none;
}

.notification-checkbox.show {
    display: block;
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

.modal-body {
    padding: 0;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1;
    min-height: 0;
    max-height: calc(85vh - 200px);
}

        .timeline-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-align: center;
            line-height: 1.3;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        /* Completed Steps - Individual Gradient Colors */
/* Completed Steps - Individual Gradient Colors */
        .timeline-step.completed .timeline-node {
            background: linear-gradient(135deg, #10b981, #059669);
            border-color: #10b981;
            color: white;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
        }

        /* Specific step colors for variety */
        .timeline-step.completed:first-of-type .timeline-node {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            border-color: #06b6d4;
            box-shadow: 0 0 15px rgba(6, 182, 212, 0.5);
        }

        .timeline-step.completed:nth-of-type(2) .timeline-node {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-color: #3b82f6;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
        }

        .timeline-step.completed:nth-of-type(3) .timeline-node {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-color: #8b5cf6;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.5);
        }

        .timeline-step.completed:nth-of-type(4) .timeline-node {
            background: linear-gradient(135deg, #10b981, #059669);
            border-color: #10b981;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
        }

        .timeline-step.completed:nth-of-type(5) .timeline-node {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-color: #22c55e;
            color: white;
            box-shadow: 0 0 15px rgba(34, 197, 94, 0.5);
        }

        .timeline-step.completed .timeline-label {
            color: var(--text-primary);
            font-weight: 700;
        }

        /* Active Step - Pulsing Animation */
        @keyframes pulse-ring {
            0% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
            }
            70% {
                box-shadow: 0 0 0 12px rgba(245, 158, 11, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
            }
        }

        .timeline-step.active .timeline-node {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-color: #fbbf24;
            color: white;
            animation: pulse-ring 2s ease-out infinite;
            transform: scale(1.1);
            font-weight: 800;
        }

        .timeline-step.active .timeline-label {
            color: #f59e0b;
            font-weight: 800;
        }

        /* Pending Steps */
        .timeline-step.pending .timeline-node {
            background: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-secondary);
            opacity: 0.5;
        }

        .timeline-step.pending .timeline-label {
            opacity: 0.5;
        }

        /* Cancelled Timeline Styles */
        .timeline.cancelled::before {
            background: linear-gradient(90deg, var(--bg-secondary) 0%, rgba(239, 68, 68, 0.2) 100%);
        }

        .timeline-step.cancelled-node .timeline-node {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-color: #f87171;
            color: white;
            animation: pulse-ring 2s ease-out infinite;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.6);
            font-weight: 800;
        }

        .timeline-step.cancelled-node .timeline-label {
            color: #ef4444;
            font-weight: 800;
        }

        .timeline-step.skipped {
            opacity: 0.25;
        }

        .timeline-step.skipped .timeline-node {
            border-style: dashed;
        }

        /* Cancelled Progress Bar */
        .timeline.cancelled .timeline-progress {
            background: linear-gradient(90deg, #06b6d4 0%, #ef4444 100%);
        }

        /* Light Theme Adjustments */
        [data-theme="light"] .timeline-container {
            background: rgba(248, 250, 252, 0.9);
            border-color: #e2e8f0;
        }

        [data-theme="light"] .timeline::before {
            background: #e2e8f0;
        }

        [data-theme="light"] .timeline-node {
            background: white;
            border-color: #cbd5e1;
        }

        [data-theme="light"] .timeline-label {
            color: #475569;
        }

        [data-theme="light"] .timeline-step.pending .timeline-node {
            background: #f8fafc;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .timeline-container {
                padding: 1rem 0.5rem;
            }

            .timeline {
                padding: 0 5px;
            }

            .timeline::before,
            .timeline-progress {
                left: 20px;
                right: 20px;
                height: 3px;
                top: 16px;
            }

            .timeline-node {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            .timeline-label {
                font-size: 0.65rem;
            }
            .selection-mode-active .notification-item-wrapper {
    cursor: default;
}

.selection-mode-active .notification-content {
    cursor: pointer;
}

            .timeline-step.active .timeline-node {
                transform: scale(1.05);
            }
        }

        @media (max-width: 480px) {
            .timeline-node {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }

            .timeline-label {
                font-size: 0.6rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <a href="index.php" class="logo">EcoWaste</a>
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

    <!-- All Notifications Modal -->
    <div class="all-notifications-modal" id="allNotificationsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>All Notifications</h2>
            <button class="modal-close" onclick="closeAllNotifications()">&times;</button>
        </div>
        <form id="deleteNotificationsForm" method="POST" action="dashboard.php">
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
                <h1>Welcome Back, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
                <a href="#request-pickup" class="btn btn-primary">Request New Pickup</a>
            </div>

            <section class="dashboard-section">
                <h2>Upcoming Pickups</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Pickup Details</th>
                                <th>Current Status</th>
                                <th>Real-time Timeline</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($upcoming_result->num_rows > 0): ?>
                                <?php while($row = $upcoming_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="Pickup Details">
                                        <strong>📅 <?= htmlspecialchars($row['pickup_date']) ?></strong><br>
                                        <small>⏰ <?= htmlspecialchars($row['time_slot']) ?></small><br>
                                        <small>🗑️ <?= htmlspecialchars($row['waste_type']) ?></small>
                                    </td>
                                    <td data-label="Current Status">
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                        <?php if ($row['latitude'] && $row['longitude']): ?>
                                            <div style="margin-top: 8px;">
                                                <button class="btn-collect" onclick="showLocationModal(<?= $row['latitude'] ?>, <?= $row['longitude'] ?>)" style="padding: 4px 8px; font-size: 0.7rem;">📍 View Map</button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Real-time Timeline">
    <?php 
        $currentStep = getStatusStep($row['status']);
        // Calculate progress: each step is 25%
        if ($currentStep > 0 && $currentStep <= 5) {
            $progressPercent = ($currentStep - 1) * 25;
        } else {
            $progressPercent = 0;
        }
    ?>
    <div class="timeline-container">
        <div class="timeline">
            <div class="timeline-progress" style="width: <?= $progressPercent ?>%"></div>
            
            <!-- Step 1: Scheduled -->
            <div class="timeline-step <?= $currentStep >= 1 ? ($currentStep > 1 ? 'completed' : 'active') : 'pending' ?>">
                <div class="timeline-node">✓</div>
                <div class="timeline-label">Scheduled</div>
            </div>
            
            <!-- Step 2: Assigned -->
            <div class="timeline-step <?= $currentStep >= 2 ? ($currentStep > 2 ? 'completed' : 'active') : 'pending' ?>">
                <div class="timeline-node">✓</div>
                <div class="timeline-label">Assigned</div>
            </div>
            
            <!-- Step 3: En Route -->
            <div class="timeline-step <?= $currentStep >= 3 ? ($currentStep > 3 ? 'completed' : 'active') : 'pending' ?>">
                <div class="timeline-node">🚚</div>
                <div class="timeline-label">En Route</div>
            </div>
            
            <!-- Step 4: Collected -->
            <div class="timeline-step <?= $currentStep >= 4 ? ($currentStep > 4 ? 'completed' : 'active') : 'pending' ?>">
                <div class="timeline-node">📦</div>
                <div class="timeline-label">Collected</div>
            </div>
            
            <!-- Step 5: Done -->
            <div class="timeline-step <?= $currentStep >= 5 ? 'completed' : 'pending' ?>">
                <div class="timeline-node">✓</div>
                <div class="timeline-label">Done</div>
            </div>
        </div>
    </div>
</td>
                                    <td data-label="Action">
                                        <?php if ($row['status'] == 'Scheduled'): ?>
                                        <form action="cancel_pickup.php" method="POST" onsubmit="return confirmCancel(event, this)">
                                            <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn-cancel">Cancel Request</button>
                                        </form>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 0.8rem;">Driver on task</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4">You have no upcoming pickups.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <section id="history" class="dashboard-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin: 0;">Pickup History</h2>
        <?php if (!$show_all_history && $total_history > 3): ?>
            <a href="dashboard.php?show_all_history=1#history" class="btn btn-secondary" style="text-decoration: none;">
                Show All (<?= $total_history ?>)
            </a>
        <?php elseif ($show_all_history): ?>
            <a href="dashboard.php#history" class="btn btn-secondary" style="text-decoration: none;">
                Show Recent (3)
            </a>
        <?php endif; ?>
    </div>
    <div class="table-container">
                     <table>
                        <thead>
                            <tr>
                                <th>Details</th>
                                <th>Final Status</th>
                                <th>Process History</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history_result->num_rows > 0): ?>
                                <?php while($row = $history_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="Details">
                                        <strong>📅 <?= htmlspecialchars($row['pickup_date']) ?></strong><br>
                                        <small>⏰ <?= htmlspecialchars($row['time_slot']) ?></small><br>
                                        <small>🗑️ <?= htmlspecialchars($row['waste_type']) ?></small>
                                    </td>
                                    <td data-label="Final Status">
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Process History">
    <?php 
        $isCancelled = ($row['status'] == 'Cancelled');
        $isCompleted = ($row['status'] == 'Completed');
        $isCollected = ($row['status'] == 'Collected' || $row['status'] == 'Collected by Driver');
        $cancelledAfterStep = ($row['driver_id']) ? 2 : 1;
        $currentStep = getStatusStep($row['status']);
        
        // Calculate progress percent based on final status
        if ($isCompleted) {
            $progressPercent = 92.5; // All 5 steps = 100%
        } elseif ($isCollected) {
            $progressPercent = 65; // 4 steps done = 75%
        } elseif ($isCancelled) {
            $progressPercent = ($cancelledAfterStep * 25);
        } else {
            $progressPercent = 90;
        }
    ?>
    <div class="timeline-container">
        <div class="timeline <?= $isCancelled ? 'cancelled' : '' ?>">
            <div class="timeline-progress" style="width: <?= $progressPercent ?>%"></div>
            
            <!-- STEP 1: Scheduled - Always completed in history -->
            <div class="timeline-step completed">
                <div class="timeline-node">✓</div>
                <div class="timeline-label">Scheduled</div>
            </div>

            <!-- STEP 2: Assigned or Cancelled -->
            <?php if($isCancelled && $cancelledAfterStep == 1): ?>
                <div class="timeline-step cancelled-node">
                    <div class="timeline-node">✕</div>
                    <div class="timeline-label">Cancelled</div>
                </div>
            <?php else: ?>
                <div class="timeline-step completed">
                    <div class="timeline-node">✓</div>
                    <div class="timeline-label">Assigned</div>
                </div>
            <?php endif; ?>

            <!-- STEP 3: En Route or Cancelled -->
            <?php if($isCancelled && $cancelledAfterStep == 2): ?>
                <div class="timeline-step cancelled-node">
                    <div class="timeline-node">✕</div>
                    <div class="timeline-label">Cancelled</div>
                </div>
            <?php elseif($isCancelled): ?>
                <div class="timeline-step skipped">
                    <div class="timeline-node">-</div>
                    <div class="timeline-label">En Route</div>
                </div>
            <?php else: ?>
                <div class="timeline-step completed">
                    <div class="timeline-node">🚚</div>
                    <div class="timeline-label">En Route</div>
                </div>
            <?php endif; ?>

            <!-- STEP 4: Collected -->
            <?php if($isCancelled): ?>
                <div class="timeline-step skipped">
                    <div class="timeline-node">-</div>
                    <div class="timeline-label">Collected</div>
                </div>
            <?php else: ?>
                <div class="timeline-step completed">
                    <div class="timeline-node">📦</div>
                    <div class="timeline-label">Collected</div>
                </div>
            <?php endif; ?>

            <!-- STEP 5: Done -->
            <?php if($isCancelled): ?>
                <div class="timeline-step skipped">
                    <div class="timeline-node">-</div>
                    <div class="timeline-label">Done</div>
                </div>
            <?php elseif($isCompleted): ?>
                <div class="timeline-step completed">
                    <div class="timeline-node">✓</div>
                    <div class="timeline-label">Done</div>
                </div>
            <?php else: ?>
                <div class="timeline-step pending">
                    <div class="timeline-node">✓</div>
                    <div class="timeline-label">Done</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No pickup history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <section id="request-pickup" class="dashboard-section">
                <h2>Request a New Pickup</h2>
                <div class="map-instructions">
                    <p>📍 Search for your location, click "Get My Location", or click directly on the map to pin your pickup location</p>
                </div>

                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search for a location (e.g., Bhopal Railway Station)" autocomplete="off">
                    <button type="button" class="search-btn" onclick="searchLocation()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                    </button>
                </div>

                <button type="button" class="btn-get-location" onclick="getCurrentLocation()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    Get My Location
                </button>

                <div id="map"></div>

                <div class="location-info" id="locationInfo">
                    <p><strong>Selected Location:</strong></p>
                    <p>Latitude: <span id="selectedLat">-</span></p>
                    <p>Longitude: <span id="selectedLng">-</span></p>
                </div>

                 <form action="dashboard.php" method="post" class="pickup-form" onsubmit="return validatePickupForm(this)">
                    <input type="hidden" id="latitude" name="latitude" value="">
                    <input type="hidden" id="longitude" name="longitude" value="">
                    
                    <div class="form-group full-width">
                        <label for="area">Area / Street / Locality</label>
                        <input type="text" id="area" name="area" placeholder="e.g., Koramangala 4th Block" required>
                    </div>
                     <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" placeholder="e.g., Bangalore" required>
                    </div>
                    <div class="form-group">
                        <label for="date">Select Date</label>
                        <input type="date" id="date" name="pickup_date" required min="<?= date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="time">Select Time Slot</label>
                        <select id="time" name="time_slot" required>
                            <option value="">--Please choose a time--</option>
                            <option value="Morning (9am-12pm)">Morning (9am-12pm)</option>
                            <option value="Afternoon (1pm-4pm)">Afternoon (1pm-4pm)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="waste-type">Waste Type</label>
                         <select id="waste-type" name="waste_type" required>
                            <option value="">--Please choose a type--</option>
                            <option value="Recyclables">Recyclables</option>
                            <option value="General Waste">General Waste</option>
                             <option value="Organic Waste">Organic Waste</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="waste_weight">Approx. Weight (Optional)</label>
                        <input type="text" id="waste_weight" name="waste_weight" placeholder="e.g., 5-10 kg">
                    </div>
                    <button type="submit" class="btn btn-primary">Schedule Pickup</button>
                </form>
            </section>
        </div>
    </main>

    <!-- Location Modal -->
    <div id="locationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); padding: 2rem; border-radius: 24px; max-width: 800px; width: 90%; max-height: 90vh; overflow: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0; color: var(--text-primary);">Pickup Location</h3>
                <button onclick="closeLocationModal()" style="background: none; border: none; color: var(--text-primary); cursor: pointer; font-size: 1.5rem;">&times;</button>
            </div>
            <div id="viewMap" style="height: 500px; border-radius: 16px;"></div>
        </div>
    </div>

    <script src="theme.js"></script>
    <script>
        let map;
        let marker;
        let viewMapInstance;

        window.onload = function() {
            initMap();
        };

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
        window.location.href = 'dashboard.php?mark_all_read=1';
    });
}

// Mark single notification as read
function markAsRead(notifId) {
    window.location.href = `dashboard.php?mark_read=1&notif_id=${notifId}`;
}

// Delete single notification
function deleteNotification(notifId) {
    showConfirm('Delete this notification?', () => {
        window.location.href = `dashboard.php?delete_notif=${notifId}`;
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
                window.location.href = `dashboard.php?${param}=${id}`;
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
        function initMap() {
            map = L.map('map').setView([23.2599, 77.4126], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            map.on('click', function(e) {
                setMarker(e.latlng.lat, e.latlng.lng);
            });
        }

        function setMarker(lat, lng) {
            if (marker) map.removeLayer(marker);
            marker = L.marker([lat, lng]).addTo(map);
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('selectedLat').textContent = lat.toFixed(6);
            document.getElementById('selectedLng').textContent = lng.toFixed(6);
            document.getElementById('locationInfo').classList.add('show');
        }

        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        map.setView([lat, lng], 15);
                        setMarker(lat, lng);
                        alert('Location acquired successfully!');
                    },
                    function() { 
                        alert('Location access denied. Please enable location services.');
                    }
                );
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        }

        async function searchLocation() {
            const query = document.getElementById('searchInput').value;
            if (!query) {
                alert('Please enter a location to search');
                return;
            }
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`);
                const data = await response.json();
                if (data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);
                    map.setView([lat, lng], 15);
                    setMarker(lat, lng);
                } else {
                    alert('Location not found. Please try a different search term.');
                }
            } catch (e) { 
                console.error(e);
                alert('Error searching for location. Please try again.');
            }
        }

        function showLocationModal(lat, lng) {
            document.getElementById('locationModal').style.display = 'flex';
            setTimeout(() => {
                if (!viewMapInstance) {
                    viewMapInstance = L.map('viewMap').setView([lat, lng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(viewMapInstance);
                } else { 
                    viewMapInstance.setView([lat, lng], 15); 
                }
                L.marker([lat, lng]).addTo(viewMapInstance);
            }, 100);
        }

        function closeLocationModal() { 
            document.getElementById('locationModal').style.display = 'none'; 
        }

function confirmCancel(event, form) {
    event.preventDefault();
    showConfirm('Are you sure you want to cancel this pickup request?', () => {
        form.submit();
    });
    return false;
}

        function validatePickupForm(form) {
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            
            if(!lat || !lng) {
                alert('Please select your pickup location on the map first!');
                return false;
            }
            return true;
        }
    </script>
    
</body>
</html>