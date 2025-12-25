<?php
require 'db.php';

if (!isset($_SESSION["admin_id"])) {
    header("Location: admin_login.php");
    exit();
}

// Handle Create Driver form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_driver'])) {
    $name = $_POST['driver_name'];
    $email = $_POST['driver_email'];
    $password = password_hash($_POST['driver_password'], PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO drivers (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $password);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_dashboard.php");
    exit();
}

// Handle Assign Driver form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_driver'])) {
    $pickup_id = $_POST['pickup_id'];
    $driver_id = $_POST['driver_id'];
    if (!empty($driver_id)) {
        $stmt = $conn->prepare("UPDATE pickups SET driver_id = ?, status = 'Assigned' WHERE id = ?");
        $stmt->bind_param("ii", $driver_id, $pickup_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch all pickups with user and driver names
$pickups_sql = "SELECT p.*, u.name as user_name, d.name as driver_name 
                FROM pickups p 
                JOIN users u ON p.user_id = u.id
                LEFT JOIN drivers d ON p.driver_id = d.id
                ORDER BY p.requested_at DESC";
$pickups_result = $conn->query($pickups_sql);

// Fetch all drivers for the dropdown
$drivers_result = $conn->query("SELECT id, name FROM drivers");
$drivers_list = $drivers_result->fetch_all(MYSQLI_ASSOC);

// Fetch ALL people (Users and Drivers) into one list
$all_people_sql = "(SELECT name, email, created_at, 'User' as role FROM users WHERE is_admin = FALSE)
                   UNION ALL
                   (SELECT name, email, created_at, 'Driver' as role FROM drivers)
                   ORDER BY created_at DESC";
$people_result = $conn->query($all_people_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard-style.css">
    <link rel="stylesheet" href="admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <a href="admin_dashboard.php" class="logo">EcoWaste Admin</a>
                <ul class="nav-links">
                    <li><a href="logout.php" class="btn btn-secondary">Logout</a></li>
                </ul>
            </div>
        </nav>
    </header>
    <main class="dashboard-main">
        <div class="container">
            <div class="dashboard-header">
                <h1>Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?>!</h1>
            </div>

            <section class="dashboard-section">
                <h2>All Pickup Requests</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User & Location</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pickups_result->num_rows > 0): while($row = $pickups_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="User & Location">
                                    <?= htmlspecialchars($row['user_name']) ?><br>
                                    <small><?= htmlspecialchars($row['area']) ?>, <?= htmlspecialchars($row['city']) ?></small>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td data-label="Assigned To">
                                    <?= htmlspecialchars($row['driver_name'] ?? 'Not Assigned') ?>
                                </td>
                                <td data-label="Action">
                                    <?php if ($row['status'] == 'Scheduled'): ?>
                                    <form method="POST" action="admin_dashboard.php" class="status-form">
                                        <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                        <select name="driver_id" required>
                                            <option value="">Select Driver</option>
                                            <?php foreach ($drivers_list as $driver): ?>
                                            <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_driver">Assign</button>
                                    </form>
                                    <?php elseif ($row['status'] == 'Collected' || $row['status'] == 'Collected by Driver'): ?>
                                    <form action="update_status.php" method="POST" class="status-form">
                                        <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                        <select name="new_status">
                                            <option value="Completed">Completed</option>
                                            <option value="Cancelled">Cancel</option>
                                        </select>
                                        <button type="submit">Update</button>
                                    </form>
                                    <?php else: echo 'No action needed'; endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4">No pickup requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <section class="dashboard-section">
                <h2>Manage Drivers</h2>
                <form action="admin_dashboard.php" method="post" class="pickup-form">
                    <h3>Create New Driver</h3>
                    <div class="form-row-compact"> <div class="form-group">
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
                    </div> <button type="submit" name="create_driver" class="btn btn-primary" >Add Driver</button>
                </form>
            </section>

             <section class="dashboard-section">
                <h2>All Users & Drivers</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php if ($people_result->num_rows > 0): ?>
                                <?php while($row = $people_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="Name"><?= htmlspecialchars($row['name']) ?></td>
                                    <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
                                    <td data-label="Role">
                                        <span class="role-badge role-<?= strtolower($row['role']) ?>">
                                            <?= htmlspecialchars($row['role']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Registration Date"><?= date("M j, Y", strtotime($row['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No users or drivers found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</body>
</html>