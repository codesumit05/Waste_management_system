<?php
require 'db.php';

// Protect this script: Check if the admin is logged in
if (!isset($_SESSION["admin_id"])) {
    http_response_code(403);
    echo "Access denied.";
    exit();
}

$admin_id = $_SESSION["admin_id"];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pickup_id']) && isset($_POST['new_status'])) {
    $pickup_id = intval($_POST['pickup_id']);
    $new_status = $_POST['new_status'];
    
    // Validate the status to ensure it's one of the allowed values
    $allowed_statuses = ['Scheduled', 'Completed', 'Cancelled'];
    if (in_array($new_status, $allowed_statuses)) {
        // Get pickup details before updating
        $details_stmt = $conn->prepare("SELECT user_id, driver_id, area, pickup_date FROM pickups WHERE id = ?");
        $details_stmt->bind_param("i", $pickup_id);
        $details_stmt->execute();
        $pickup_data = $details_stmt->get_result()->fetch_assoc();
        $details_stmt->close();
        
        // Update status
        $stmt = $conn->prepare("UPDATE pickups SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $pickup_id);
        $stmt->execute();
        $stmt->close();
        
        // Send notifications based on status
        if ($new_status == 'Completed') {
            // Notify user
            $notif_title = "Pickup Completed";
            $notif_message = "Your waste pickup from {$pickup_data['area']} on {$pickup_data['pickup_date']} has been completed successfully. Thank you for using EcoWaste!";
            $user_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'success')");
            $user_notif->bind_param("iss", $pickup_data['user_id'], $notif_title, $notif_message);
            $user_notif->execute();
            $user_notif->close();
            
            // Notify driver if assigned
            if ($pickup_data['driver_id']) {
                $driver_notif_message = "Pickup from {$pickup_data['area']} has been marked as completed by admin.";
                $driver_notif = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Pickup Completed', ?, 'success')");
                $driver_notif->bind_param("is", $pickup_data['driver_id'], $driver_notif_message);
                $driver_notif->execute();
                $driver_notif->close();
            }
            
            // Notify admin
            $admin_notif_message = "Pickup from {$pickup_data['area']} has been successfully marked as completed.";
            $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'Status Updated', ?, 'success')");
            $admin_notif->bind_param("is", $admin_id, $admin_notif_message);
            $admin_notif->execute();
            $admin_notif->close();
            
        } elseif ($new_status == 'Cancelled') {
            // Notify user
            $notif_title = "Pickup Cancelled";
            $notif_message = "Your waste pickup from {$pickup_data['area']} on {$pickup_data['pickup_date']} has been cancelled.";
            $user_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'warning')");
            $user_notif->bind_param("iss", $pickup_data['user_id'], $notif_title, $notif_message);
            $user_notif->execute();
            $user_notif->close();
            
            // Notify driver if assigned
            if ($pickup_data['driver_id']) {
                $driver_notif_message = "Pickup from {$pickup_data['area']} has been cancelled by admin.";
                $driver_notif = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Pickup Cancelled', ?, 'warning')");
                $driver_notif->bind_param("is", $pickup_data['driver_id'], $driver_notif_message);
                $driver_notif->execute();
                $driver_notif->close();
            }
            
            // Notify admin
            $admin_notif_message = "Pickup from {$pickup_data['area']} has been cancelled.";
            $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, 'Status Updated', ?, 'warning')");
            $admin_notif->bind_param("is", $admin_id, $admin_notif_message);
            $admin_notif->execute();
            $admin_notif->close();
        }
    }
}

// Redirect back to the admin dashboard
header("Location: admin_dashboard.php");
exit();
?>