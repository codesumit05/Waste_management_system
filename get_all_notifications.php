<?php
require 'db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["admin_id"]) && !isset($_SESSION["user_id"]) && !isset($_SESSION["driver_id"])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Determine which ID to use
if (isset($_SESSION["admin_id"])) {
    $id = $_SESSION["admin_id"];
    $column = "admin_id";
} elseif (isset($_SESSION["driver_id"])) {
    $id = $_SESSION["driver_id"];
    $column = "driver_id";
} else {
    $id = $_SESSION["user_id"];
    $column = "user_id";
}

// Fetch all notifications
$sql = "SELECT id, title, message, type, is_read, created_at 
        FROM notifications 
        WHERE $column = ? 
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $row['created_at'] = date('M j, Y g:i A', strtotime($row['created_at']));
    $notifications[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($notifications);
?>