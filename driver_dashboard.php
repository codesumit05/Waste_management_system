<?php
require 'db.php';

// Check if driver is logged in
if (!isset($_SESSION["driver_id"])) {
    header("Location: driver_login.php");
    exit();
}
$driver_id = $_SESSION['driver_id'];

// Handle status update to "Collected"
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['collect_pickup'])) {
    $pickup_id = $_POST['pickup_id'];
    // Let's use 'Collected by Driver' for consistency
    $stmt = $conn->prepare("UPDATE pickups SET status = 'Collected by Driver' WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("ii", $pickup_id, $driver_id);
    $stmt->execute();
    $stmt->close();
    header("Location: driver_dashboard.php");
    exit();
}

// 1. Fetch CURRENTLY ASSIGNED pickups for this driver
$assigned_sql = "SELECT p.*, u.name as user_name
                FROM pickups p
                JOIN users u ON p.user_id = u.id
                WHERE p.driver_id = ? AND p.status = 'Assigned'
                ORDER BY p.pickup_date ASC";
$stmt_assigned = $conn->prepare($assigned_sql);
$stmt_assigned->bind_param("i", $driver_id);
$stmt_assigned->execute();
$assigned_result = $stmt_assigned->get_result();

// 2. Fetch PAST pickups for this driver (History)
$history_sql = "SELECT p.*, u.name as user_name
                FROM pickups p
                JOIN users u ON p.user_id = u.id
                WHERE p.driver_id = ? AND p.status IN ('Completed', 'Cancelled')
                ORDER BY p.updated_at DESC";
$stmt_history = $conn->prepare($history_sql);
$stmt_history->bind_param("i", $driver_id);
$stmt_history->execute();
$history_result = $stmt_history->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - EcoWaste</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard-style.css">
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <a href="#" class="logo">Driver Panel</a>
                <ul class="nav-links">
                    <li><a href="logout.php" class="btn btn-secondary">Logout</a></li>
                </ul>
            </div>
        </nav>
    </header>
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
                                <th>User</th>
                                <th>Location</th>
                                <th>Pickup Details</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($assigned_result->num_rows > 0): while($row = $assigned_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="User"><?= htmlspecialchars($row['user_name']) ?></td>
                                <td data-label="Location"><strong><?= htmlspecialchars($row['area']) ?></strong><br><small><?= htmlspecialchars($row['city']) ?></small></td>
                                <td data-label="Pickup Details"><?= htmlspecialchars($row['pickup_date']) ?><br><small><?= htmlspecialchars($row['waste_type']) ?></small></td>
                                <td data-label="Action">
                                    <form method="POST" action="driver_dashboard.php" onsubmit="return confirm('Confirm collection?');">
                                        <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="collect_pickup" class="btn-collect">Mark as Collected</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4">No new pickups assigned to you.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-section">
                <h2>Your Pickup History</h2>
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
                                    <span class="status-badge status-<?= strtolower($row['status']) ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4">No pickup history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>
</body>
</html>