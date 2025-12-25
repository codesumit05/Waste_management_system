<?php
require 'db.php';

// Check if the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pickup_id'])) {
    $pickup_id = intval($_POST['pickup_id']);
    $user_id = $_SESSION['user_id'];

    // Get pickup details before cancelling
    $details_stmt = $conn->prepare("SELECT driver_id, area, pickup_date FROM pickups WHERE id = ? AND user_id = ?");
    $details_stmt->bind_param("ii", $pickup_id, $user_id);
    $details_stmt->execute();
    $pickup_data = $details_stmt->get_result()->fetch_assoc();
    $details_stmt->close();

    // Security Check: Verify that the pickup belongs to the logged-in user before cancelling
    $stmt = $conn->prepare("UPDATE pickups SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pickup_id, $user_id);
    
    if ($stmt->execute()) {
        // Notify user
        $notif_title = "Pickup Cancelled";
        $notif_message = "You have cancelled your pickup from {$pickup_data['area']} scheduled for {$pickup_data['pickup_date']}.";
        $user_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
        $user_notif->bind_param("iss", $user_id, $notif_title, $notif_message);
        $user_notif->execute();
        $user_notif->close();
        
        // If driver was assigned, notify them
        if ($pickup_data['driver_id']) {
            $driver_notif_message = "Pickup from {$pickup_data['area']} scheduled for {$pickup_data['pickup_date']} has been cancelled by the user.";
            $driver_notif = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Pickup Cancelled', ?, 'warning')");
            $driver_notif->bind_param("is", $pickup_data['driver_id'], $driver_notif_message);
            $driver_notif->execute();
            $driver_notif->close();
        }
    }
    
    $stmt->close();
}

// Redirect back to the user dashboard
header("Location: dashboard.php");
exit();
?>