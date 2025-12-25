<?php
require 'db.php'; // Includes session_start()

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle new pickup request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pickup_date'])) {
    $area = $_POST['area'];
    $city = $_POST['city'];
    $pickup_date = $_POST['pickup_date'];
    $time_slot = $_POST['time_slot'];
    $waste_type = $_POST['waste_type'];

    $stmt = $conn->prepare("INSERT INTO pickups (user_id, area, city, pickup_date, time_slot, waste_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $area, $city, $pickup_date, $time_slot, $waste_type);
    
    if($stmt->execute()){
        $message = "Pickup scheduled successfully!";
    }
    $stmt->close();
}

// Fetch upcoming pickups for the logged-in user
$upcoming_sql = "SELECT id, pickup_date, time_slot, waste_type, status FROM pickups 
                 WHERE user_id = ? AND status = 'Scheduled' AND pickup_date >= CURDATE()
                 ORDER BY pickup_date ASC";
$stmt_upcoming = $conn->prepare($upcoming_sql);
$stmt_upcoming->bind_param("i", $user_id);
$stmt_upcoming->execute();
$upcoming_result = $stmt_upcoming->get_result();

// Fetch pickup history for the logged-in user
$history_sql = "SELECT pickup_date, time_slot, waste_type, status FROM pickups 
                WHERE user_id = ? AND (status != 'Scheduled' OR pickup_date < CURDATE())
                ORDER BY updated_at DESC";
$stmt_history = $conn->prepare($history_sql);
$stmt_history->bind_param("i", $user_id);
$stmt_history->execute();
$history_result = $stmt_history->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EcoWaste Solutions</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard-style.css">
    <link rel="stylesheet" href="admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <a href="index.php" class="logo">EcoWaste</a>
                <ul class="nav-links">
                    <li><a href="logout.php" class="btn btn-secondary">Logout</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main class="dashboard-main">
        <div class="container">
            <div class="dashboard-header">
                <h1>Welcome Back, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
                <a href="#request-pickup" class="btn btn-primary">Request New Pickup</a>
            </div>
            
            <?php if(!empty($message)): ?>
                <div class="success-message"><?= $message ?></div>
            <?php endif; ?>

            <section class="dashboard-section">
                <h2>Upcoming Pickups</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time Slot</th>
                                <th>Waste Type</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($upcoming_result->num_rows > 0): ?>
                                <?php while($row = $upcoming_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['pickup_date']) ?></td>
                                    <td><?= htmlspecialchars($row['time_slot']) ?></td>
                                    <td><?= htmlspecialchars($row['waste_type']) ?></td>
                                    <td><span class="status-badge status-scheduled"><?= htmlspecialchars($row['status']) ?></span></td>
                                    <td>
                                        <form action="cancel_pickup.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this pickup?');">
                                            <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn-cancel">Cancel</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5">You have no upcoming pickups.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <section class="dashboard-section">
                <h2>Pickup History</h2>
                <div class="table-container">
                     <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time Slot</th>
                                <th>Waste Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($history_result->num_rows > 0): ?>
                                <?php while($row = $history_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['pickup_date']) ?></td>
                                    <td><?= htmlspecialchars($row['time_slot']) ?></td>
                                    <td><?= htmlspecialchars($row['waste_type']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($row['status']) ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No pickup history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <section id="request-pickup" class="dashboard-section">
                <h2>Request a New Pickup</h2>
                 <form action="dashboard.php" method="post" class="pickup-form">
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
</body>
</html>
<?php 
    $stmt_upcoming->close();
    $stmt_history->close();
    $conn->close(); 
?>