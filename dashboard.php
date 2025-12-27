<?php
require 'db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

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
        $message = "Pickup scheduled successfully with location!";
        $message_type = "success";

        // Notify Admin about new pickup request
        $user_name = $_SESSION['user_name'];
        $notif_title = "New Pickup Request";
        $notif_message = "User $user_name has scheduled a new pickup in $area, $city for $pickup_date ($time_slot).";
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) SELECT id, ?, ?, 'info' FROM users WHERE is_admin = TRUE LIMIT 1");
        $admin_notif->bind_param("ss", $notif_title, $notif_message);
        $admin_notif->execute();
        $admin_notif->close();
    } else {
        $message = "Error scheduling pickup.";
        $message_type = "error";
    }
    $stmt->close();
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

// Fetch upcoming pickups
$upcoming_sql = "SELECT id, pickup_date, time_slot, waste_type, status, latitude, longitude FROM pickups 
                 WHERE user_id = ? AND status IN ('Scheduled', 'Assigned') AND pickup_date >= CURDATE()
                 ORDER BY pickup_date ASC";
$stmt_upcoming = $conn->prepare($upcoming_sql);
$stmt_upcoming->bind_param("i", $user_id);
$stmt_upcoming->execute();
$upcoming_result = $stmt_upcoming->get_result();

// Fetch pickup history
$history_sql = "SELECT pickup_date, time_slot, waste_type, status FROM pickups 
                WHERE user_id = ? AND (status NOT IN ('Scheduled', 'Assigned') OR pickup_date < CURDATE())
                ORDER BY updated_at DESC";
$stmt_history = $conn->prepare($history_sql);
$stmt_history->bind_param("i", $user_id);
$stmt_history->execute();
$history_result = $stmt_history->get_result();
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
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        .modal-body {
            padding: 1rem;
            overflow-y: auto;
            flex: 1;
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
                                    <?php while($notif = $notifications->fetch_assoc()): ?>
                                    <div class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" 
                                         onclick="markAsRead(<?= $notif['id'] ?>)">
                                        <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                                        <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notification-time"><?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?></div>
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
            <div class="modal-body" id="allNotificationsBody">
                <!-- Will be populated by JavaScript -->
            </div>
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
                                <th>Date</th>
                                <th>Time Slot</th>
                                <th>Waste Type</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($upcoming_result->num_rows > 0): ?>
                                <?php while($row = $upcoming_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="Date"><?= htmlspecialchars($row['pickup_date']) ?></td>
                                    <td data-label="Time Slot"><?= htmlspecialchars($row['time_slot']) ?></td>
                                    <td data-label="Waste Type"><?= htmlspecialchars($row['waste_type']) ?></td>
                                    <td data-label="Status"><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                    <td data-label="Location">
                                        <?php if ($row['latitude'] && $row['longitude']): ?>
                                            <button class="btn-collect" onclick="showLocationModal(<?= $row['latitude'] ?>, <?= $row['longitude'] ?>)">View Map</button>
                                        <?php else: ?>
                                            No location
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Action">
                                        <?php if ($row['status'] == 'Scheduled'): ?>
                                        <form action="cancel_pickup.php" method="POST" onsubmit="return confirmCancel(event, this)">
                                            <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn-cancel">Cancel</button>
                                        </form>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6">You have no upcoming pickups.</td></tr>
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
                                    <td data-label="Date"><?= htmlspecialchars($row['pickup_date']) ?></td>
                                    <td data-label="Time Slot"><?= htmlspecialchars($row['time_slot']) ?></td>
                                    <td data-label="Waste Type"><?= htmlspecialchars($row['waste_type']) ?></td>
                                    <td data-label="Status">
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
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

        // Notification Functions
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }

        function markAsRead(notifId) {
            window.location.href = `dashboard.php?mark_read=1&notif_id=${notifId}`;
        }

        function markAllRead() {
            if (confirm('Mark all notifications as read?')) {
                window.location.href = 'dashboard.php?mark_all_read=1';
            }
        }

        function showAllNotifications() {
            document.getElementById('allNotificationsModal').classList.add('show');
            document.getElementById('notificationDropdown').classList.remove('show');
            fetchAllNotifications();
        }

        function closeAllNotifications() {
            document.getElementById('allNotificationsModal').classList.remove('show');
        }

        function fetchAllNotifications() {
            fetch('get_all_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const body = document.getElementById('allNotificationsBody');
                    if (data.error) {
                        body.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
                        return;
                    }
                    if (data.length === 0) {
                        body.innerHTML = '<div class="notification-empty">No notifications</div>';
                        return;
                    }
                    body.innerHTML = data.map(notif => `
                        <div class="notification-item ${!notif.is_read ? 'unread' : ''}" onclick="markAsRead(${notif.id})">
                            <div class="notification-title">${notif.title}</div>
                            <div class="notification-message">${notif.message}</div>
                            <div class="notification-time">${notif.created_at}</div>
                        </div>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('allNotificationsBody').innerHTML = '<div class="notification-empty">Error loading notifications</div>';
                });
        }

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const bell = document.querySelector('.notification-bell');
            const dropdown = document.getElementById('notificationDropdown');
            if (bell && dropdown && !bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Close modal when clicking outside
        document.getElementById('allNotificationsModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closeAllNotifications();
            }
        });

        // Map Functions - Leaflet
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
            if (marker) {
                map.removeLayer(marker);
            }
            
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
                        showAlert('Location acquired successfully!', 'success');
                    },
                    function(error) {
                        showAlert('Unable to get location. Please click on the map to set location.', 'warning');
                    }
                );
            } else {
                showAlert('Geolocation is not supported by your browser.', 'error');
            }
        }

        async function searchLocation() {
    const query = document.getElementById('searchInput').value;
    if (!query) {
        showAlert('Please enter a location to search', 'warning');
        return;
    }

    try {
        const response = await fetch(`search_location.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.error) {
            showAlert(data.error, 'error');
            return;
        }
        
        if (data && data.length > 0) {
            const lat = parseFloat(data[0].lat);
            const lng = parseFloat(data[0].lon);
            map.setView([lat, lng], 15);
            setMarker(lat, lng);
            showAlert('Location found!', 'success');
        } else {
            showAlert('Location not found. Please try a different search.', 'warning');
        }
    } catch (error) {
        console.error('Search error:', error);
        showAlert('Error searching location. Please try again.', 'error');
    }
}

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchLocation();
            }
        });

        function showLocationModal(lat, lng) {
            const modal = document.getElementById('locationModal');
            modal.style.display = 'flex';
            
            setTimeout(() => {
                if (!viewMapInstance) {
                    viewMapInstance = L.map('viewMap').setView([lat, lng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(viewMapInstance);
                } else {
                    viewMapInstance.setView([lat, lng], 15);
                }
                
                L.marker([lat, lng]).addTo(viewMapInstance)
                    .bindPopup('Pickup Location')
                    .openPopup();
                
                viewMapInstance.invalidateSize();
            }, 100);
        }

        function closeLocationModal() {
            document.getElementById('locationModal').style.display = 'none';
        }

        // Initialize map when DOM is ready
        document.addEventListener('DOMContentLoaded', initMap);

        // Show PHP messages
        <?php if (!empty($message)): ?>
            showAlert('<?= addslashes($message) ?>', '<?= $message_type ?>');
        <?php endif; ?>

        function confirmCancel(event, form) {
            event.preventDefault();
            showConfirm('Are you sure you want to cancel this pickup?', () => {
                form.submit();
            });
            return false;
        }

        function validatePickupForm(form) {
            const area = form.querySelector('#area').value.trim();
            const city = form.querySelector('#city').value.trim();
            const date = form.querySelector('#date').value;
            const time = form.querySelector('#time').value;
            const wasteType = form.querySelector('#waste-type').value;
            const latitude = form.querySelector('#latitude').value;
            const longitude = form.querySelector('#longitude').value;
            
            if (!area || !city || !date || !time || !wasteType) {
                showAlert('Please fill in all required fields', 'warning');
                return false;
            }

            if (!latitude || !longitude) {
                showAlert('Please select a location on the map', 'warning');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>
<?php 
    $stmt_upcoming->close();
    $stmt_history->close();
    $stmt_notif->close();
    $conn->close(); 
?>