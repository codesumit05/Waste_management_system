<?php
require 'db.php';

// Protect this script: Check if the admin is logged in
if (!isset($_SESSION["admin_id"])) {
    // If not an admin, send an error and exit
    http_response_code(403); // Forbidden
    echo "Access denied.";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pickup_id']) && isset($_POST['new_status'])) {
    $pickup_id = $_POST['pickup_id'];
    $new_status = $_POST['new_status'];
    
    // Validate the status to ensure it's one of the allowed values
    $allowed_statuses = ['Scheduled', 'Completed', 'Cancelled'];
    if (in_array($new_status, $allowed_statuses)) {
        $stmt = $conn->prepare("UPDATE pickups SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $pickup_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Redirect back to the admin dashboard
header("Location: admin_dashboard.php");
exit();
?>