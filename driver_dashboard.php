<?php
require 'db.php';

if (!isset($_SESSION["driver_id"])) {
    header("Location: driver_login.php");
    exit();
}
$driver_id = $_SESSION['driver_id'];
$message = '';
$message_type = '';

// Handle status update to "Collected"
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['collect_pickup'])) {
    $pickup_id = $_POST['pickup_id'];
    $stmt = $conn->prepare("UPDATE pickups SET status = 'Collected by Driver' WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("ii", $pickup_id, $driver_id);
    if ($stmt->execute()) {
        $message = "Pickup marked as collected!";
        $message_type = "success";
    } else {
        $message = "Error updating pickup status.";
        $message_type = "error";
    }
    $stmt->close();
}

// Fetch CURRENTLY ASSIGNED pickups with location
$assigned_sql = "SELECT p.*, u.name as user_name, u.email as user_email
                FROM pickups p
                JOIN users u ON p.user_id = u.id
                WHERE p.driver_id = ? AND p.status = 'Assigned'
                ORDER BY p.pickup_date ASC";
$stmt_assigned = $conn->prepare($assigned_sql);
$stmt_assigned->bind_param("i", $driver_id);
$stmt_assigned->execute();
$assigned_result = $stmt_assigned->get_result();

// Fetch PAST pickups (History)
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
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - EcoWaste</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard-style.css">
    <link rel="stylesheet" href="admin-style.css">
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
        .contact-info a:hover {
            text-decoration: underline;
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
                                        <br>
                                        <small style="color: var(--text-secondary); margin-top: 0.5rem; display: block;">
                                            📍 <?= number_format($row['latitude'], 4) ?>, <?= number_format($row['longitude'], 4) ?>
                                        </small>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">No location pinned</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Action">
                                    <form method="POST" action="driver_dashboard.php" onsubmit="return confirmCollect(event, this)">
                                        <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="collect_pickup" class="btn-collect">Mark as Collected</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5">No new pickups assigned to you.</td></tr>
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

    <!-- Location Modal -->
    <div id="locationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-secondary); padding: 2rem; border-radius: 24px; max-width: 900px; width: 90%; max-height: 90vh; overflow: auto; border: 2px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <h3 style="margin: 0; color: var(--text-primary);" id="modalTitle">Pickup Location</h3>
                    <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary); font-size: 0.9rem;" id="modalSubtitle"></p>
                </div>
                <button onclick="closeLocationModal()" style="background: var(--bg-tertiary); border: 2px solid var(--border-color); color: var(--text-primary); cursor: pointer; font-size: 1.5rem; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;">
                    &times;
                </button>
            </div>
            <div id="viewMap" style="height: 500px; border-radius: 16px; border: 2px solid var(--border-color);"></div>
            <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-tertiary); border-radius: 12px; display: flex; gap: 1rem; flex-wrap: wrap;">
                <button onclick="openInGoogleMaps()" class="btn btn-primary" style="flex: 1; min-width: 200px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                    Open in Google Maps
                </button>
                <button onclick="getDirections()" class="btn btn-secondary" style="flex: 1; min-width: 200px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                    Get Directions
                </button>
            </div>
        </div>
    </div>

    <script src="theme.js"></script>
    <script>
        let viewMapInstance;
        let currentLat, currentLng;

        function showLocationModal(lat, lng, userName, address) {
            currentLat = lat;
            currentLng = lng;
            
            const modal = document.getElementById('locationModal');
            modal.style.display = 'flex';
            
            document.getElementById('modalTitle').textContent = `Pickup Location: ${userName}`;
            document.getElementById('modalSubtitle').textContent = `📍 ${address}`;
            
            setTimeout(() => {
                if (!viewMapInstance) {
                    viewMapInstance = L.map('viewMap').setView([lat, lng], 16);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(viewMapInstance);
                } else {
                    viewMapInstance.setView([lat, lng], 16);
                }
                
                // Custom marker icon
                const customIcon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background: linear-gradient(135deg, #ef4444, #dc2626); width: 40px; height: 40px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="white" style="transform: rotate(45deg);">
                                <path d="M19 9l-7 7-7-7"/>
                            </svg>
                           </div>`,
                    iconSize: [40, 40],
                    iconAnchor: [20, 40]
                });
                
                L.marker([lat, lng], { icon: customIcon }).addTo(viewMapInstance)
                    .bindPopup(`<strong>${userName}</strong><br>${address}`)
                    .openPopup();
                
                viewMapInstance.invalidateSize();
            }, 100);
        }

        function closeLocationModal() {
            document.getElementById('locationModal').style.display = 'none';
        }

        function openInGoogleMaps() {
            window.open(`https://www.google.com/maps?q=${currentLat},${currentLng}`, '_blank');
        }

        function getDirections() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const myLat = position.coords.latitude;
                        const myLng = position.coords.longitude;
                        window.open(`https://www.google.com/maps/dir/${myLat},${myLng}/${currentLat},${currentLng}`, '_blank');
                    },
                    function() {
                        window.open(`https://www.google.com/maps/dir//${currentLat},${currentLng}`, '_blank');
                    }
                );
            } else {
                window.open(`https://www.google.com/maps/dir//${currentLat},${currentLng}`, '_blank');
            }
        }

        <?php if (!empty($message)): ?>
            showAlert('<?= addslashes($message) ?>', '<?= $message_type ?>');
        <?php endif; ?>

        function confirmCollect(event, form) {
            event.preventDefault();
            showConfirm('Confirm that you have collected this waste?', () => {
                form.submit();
            });
            return false;
        }
    </script>
</body>
</html>