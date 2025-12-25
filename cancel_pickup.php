<?php
require 'db.php';

// Check if the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pickup_id'])) {
    $pickup_id = $_POST['pickup_id'];
    $user_id = $_SESSION['user_id'];

    // Security Check: Verify that the pickup belongs to the logged-in user before cancelling
    $stmt = $conn->prepare("UPDATE pickups SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $pickup_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to the user dashboard
header("Location: dashboard.php");
exit();
?>