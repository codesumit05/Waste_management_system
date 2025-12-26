<?php
require 'db.php';

// Ensure driver is logged in
if (!isset($_SESSION["driver_id"])) {
    header("Location: driver_login.php");
    exit();
}

$driver_id = $_SESSION['driver_id'];
$message = '';
$message_type = '';

// Mark notification as read
if (isset($_GET['mark_read']) && isset($_GET['notif_id'])) {
    $notif_id = intval($_GET['notif_id']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("ii", $notif_id, $driver_id);
    $stmt->execute();
    $stmt->close();
    header("Location: driver_dashboard.php");
    exit();
}

/**
 * PURE SERVER-SIDE LOGIC
 * This handles the "Mark as Collected" action.
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['collect_pickup'])) {
    $pickup_id = intval($_POST['pickup_id']);
    
    // Get pickup details for notifications
    $details_stmt = $conn->prepare("SELECT p.*, u.name as user_name, u.id as user_id FROM pickups p JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.driver_id = ?");
    $details_stmt->bind_param("ii", $pickup_id, $driver_id);
    $details_stmt->execute();
    $pickup_data = $details_stmt->get_result()->fetch_assoc();
    $details_stmt->close();
    
    // Update the database status directly
    $stmt = $conn->prepare("UPDATE pickups SET status = 'Collected by Driver' WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("ii", $pickup_id, $driver_id);
    
    if ($stmt->execute() && $pickup_data) {
        $message = "Pickup successfully marked as collected!";
        $message_type = "success";
        
        // Notify the user about the collection
        $user_notif_message = "Your waste from {$pickup_data['area']} has been collected by the driver.";
        $user_notif = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Waste Collected', ?, 'success')");
        $user_notif->bind_param("is", $pickup_data['user_id'], $user_notif_message);
        $user_notif->execute();
        $user_notif->close();
        
        // Notify driver about successful collection
        $driver_notif_message = "You have successfully collected waste from {$pickup_data['user_name']} at {$pickup_data['area']}.";
        $driver_notif = $conn->prepare("INSERT INTO notifications (driver_id, title, message, type) VALUES (?, 'Collection Confirmed', ?, 'success')");
        $driver_notif->bind_param("is", $driver_id, $driver_notif_message);
        $driver_notif->execute();
        $driver_notif->close();
        
        // Notify admin
        $admin_notif_message = "Pickup from {$pickup_data['user_name']} at {$pickup_data['area']} has been collected.";
        $admin_notif = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) SELECT id, 'Pickup Collected', ?, 'info' FROM users WHERE is_admin = TRUE LIMIT 1");
        $admin_notif->bind_param("s", $admin_notif_message);
        $admin_notif->execute();
        $admin_notif->close();
    } else {
        $message = "Error: Database update failed.";
        $message_type = "error";
    }
    $stmt->close();
}

// Fetch notifications
$notif_sql = "SELECT * FROM notifications WHERE driver_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt_notif = $conn->prepare($notif_sql);
$stmt_notif->bind_param("i", $driver_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result();

// Count unread notifications
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE driver_id = ? AND is_read = FALSE";
$stmt_unread = $conn->prepare($unread_sql);
$stmt_unread->bind_param("i", $driver_id);
$stmt_unread->execute();
$unread_count = $stmt_unread->get_result()->fetch_assoc()['count'];
$stmt_unread->close();

// Get assigned pickups with coordinates and user email
$assigned_sql = "SELECT p.*, u.name as user_name, u.email as user_email
                FROM pickups p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.driver_id = ? AND p.status = 'Assigned' 
                ORDER BY p.pickup_date ASC";
$stmt_assigned = $conn->prepare($assigned_sql);
$stmt_assigned->bind_param("i", $driver_id);
$stmt_assigned->execute();
$assigned_result = $stmt_assigned->get_result();

// Get history
$history_sql = "SELECT p.*, u.name as user_name 
                FROM pickups p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.driver_id = ? AND p.status IN ('Completed', 'Cancelled', 'Collected by Driver') 
                ORDER BY p.updated_at DESC LIMIT 10";
$stmt_history = $conn->prepare($history_sql);
$stmt_history->bind_param("i", $driver_id);
$stmt_history->execute();
$history_result = $stmt_history->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - EcoWaste</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard-style.css">
    <link rel="stylesheet" href="admin-style.css">
    <link rel="stylesheet" href="theme.css">
    <style>
        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(139, 92, 246, 0.15));
            border: 1px solid var(--primary);
            border-radius: 12px;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .location-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.3);
        }
        .contact-info {
            background: var(--bg-tertiary);
            padding: 0.75rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        .contact-info a {
            color: var(--primary);
            text-decoration: none;
        }
        .directions-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }
        .directions-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
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
            max-height: 500px;
            overflow-y: auto;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 10px 40px var(--shadow-color);
            display: none;
            z-index: 1000;
        }
        .notification-dropdown.show {
            display: block;
        }
        .notification-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            color: var(--text-primary);
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
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <a href="#" class="logo">Driver Panel</a>
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
                            <div class="notification-header">Notifications</div>
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
                    </li>
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
                                <th>User Details</th>
                                <th>Location</th>
                                <th>Pickup Details</th>
                                <th>Map Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($assigned_result->num_rows > 0): while($row = $assigned_result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="User Details">
                                    <strong><?= htmlspecialchars($row['user_name']) ?></strong>
                                    <div class="contact-info">
                                        📧 <a href="mailto:<?= htmlspecialchars($row['user_email']) ?>"><?= htmlspecialchars($row['user_email']) ?></a>
                                    </div>
                                </td>
                                <td data-label="Location">
                                    <strong><?= htmlspecialchars($row['area']) ?></strong><br>
                                    <small><?= htmlspecialchars($row['city']) ?></small>
                                </td>
                                <td data-label="Pickup Details">
                                    📅 <?= htmlspecialchars($row['pickup_date']) ?><br>
                                    ⏰ <?= htmlspecialchars($row['time_slot']) ?><br>
                                    🗑️ <small><?= htmlspecialchars($row['waste_type']) ?></small>
                                </td>
                                <td data-label="Map Location">
                                    <?php if ($row['latitude'] && $row['longitude']): ?>
                                        <button class="location-badge" onclick="showLocationModal(<?= $row['latitude'] ?>, <?= $row['longitude'] ?>, '<?= htmlspecialchars($row['user_name']) ?>', '<?= htmlspecialchars($row['area']) ?>')">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                <circle cx="12" cy="10" r="3"/>
                                            </svg>
                                            View on Map
                                        </button>
                                        <button class="directions-btn" onclick="openGoogleMapsDirections(<?= $row['latitude'] ?>, <?= $row['longitude'] ?>)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                                                <path d="M2 17l10 5 10-5"/>
                                                <path d="M2 12l10 5 10-5"/>
                                            </svg>
                                            Get Directions
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">No location pinned</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Action">
                                    <form method="POST" action="driver_dashboard.php" onsubmit="return handleCollect(event, this);">
                                        <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="collect_pickup" class="btn-collect" style="width: 100%;">Mark as Collected</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5">No pending pickups assigned to you.</td></tr>
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
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
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

    <!-- Location Modal -->
    <div id="locationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); padding: 2rem; border-radius: 24px; max-width: 900px; width: 90%; max-height: 90vh; overflow: auto; border: 2px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <h3 style="margin: 0; color: var(--text-primary);" id="modalTitle">Pickup Location</h3>
                    <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary); font-size: 0.9rem;" id="modalSubtitle"></p>
                </div>
                <button onclick="closeLocationModal()" style="background: var(--bg-tertiary); border: 2px solid var(--border-color); color: var(--text-primary); cursor: pointer; font-size: 1.5rem; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">&times;</button>
            </div>
            <div id="viewMap" style="height: 450px; border-radius: 16px; border: 2px solid var(--border-color);"></div>
        </div>
    </div>

    <script src="theme.js"></script>
    <script>
        window.gm_authFailure = function() {
            console.error('Google Maps authentication failed');
            if(window.showAlert) {
                showAlert('Google Maps failed to load. Please check API key configuration.', 'error');
            }
        };
    </script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBGfAE5JUWf6gMj80B5SOmF_aJ9blsRFes&loading=async&callback=initGoogleMaps"></script>
    
    <script>
        let viewMapInstance;
        let currentMarker;

        function initGoogleMaps() {
            console.log('Google Maps initialized');
        }

        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }

        document.addEventListener('click', function(event) {
            const bell = document.querySelector('.notification-bell');
            const dropdown = document.getElementById('notificationDropdown');
            if (bell && dropdown && !bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        function markAsRead(notifId) {
            window.location.href = `driver_dashboard.php?mark_read=1&notif_id=${notifId}`;
        }

        async function showLocationModal(lat, lng, userName, address) {
            document.getElementById('locationModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = `Pickup Location: ${userName}`;
            document.getElementById('modalSubtitle').textContent = `📍 ${address}`;
            
            setTimeout(async () => {
                const { Map } = await google.maps.importLibrary("maps");
                const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
                
                if (!viewMapInstance) {
                    viewMapInstance = new Map(document.getElementById('viewMap'), {
                        center: { lat: lat, lng: lng },
                        zoom: 16,
                        mapTypeControl: true,
                        streetViewControl: true,
                        fullscreenControl: true,
                        mapId: "DRIVER_VIEW_MAP"
                    });
                } else {
                    viewMapInstance.setCenter({ lat: lat, lng: lng });
                }
                
                if (currentMarker) {
                    currentMarker.map = null;
                }

                currentMarker = new AdvancedMarkerElement({
                    map: viewMapInstance,
                    position: { lat: lat, lng: lng },
                    title: address
                });
            }, 100);
        }

        function closeLocationModal() {
            document.getElementById('locationModal').style.display = 'none';
        }

        function openGoogleMapsDirections(lat, lng) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;
            window.open(url, '_blank');
        }

        function handleCollect(event, form) {
            event.preventDefault();
            
            showConfirm('Is the waste collected?', () => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'collect_pickup';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
                form.submit();
            });
            
            return false;
        }

        <?php if($message): ?>
            if(window.showAlert) {
                showAlert("<?= addslashes($message) ?>", "<?= $message_type ?>");
            } else {
                alert("<?= addslashes($message) ?>");
            }
        <?php endif; ?>
    </script>
</body>
</html>
<?php 
    $stmt_assigned->close();
    $stmt_history->close();
    $stmt_notif->close();
    $conn->close(); 
?>