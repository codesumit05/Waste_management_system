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
    } else {
        $message = "Error scheduling pickup.";
        $message_type = "error";
    }
    $stmt->close();
}

// Fetch upcoming pickups
$upcoming_sql = "SELECT id, pickup_date, time_slot, waste_type, status, latitude, longitude FROM pickups 
                 WHERE user_id = ? AND status = 'Scheduled' AND pickup_date >= CURDATE()
                 ORDER BY pickup_date ASC";
$stmt_upcoming = $conn->prepare($upcoming_sql);
$stmt_upcoming->bind_param("i", $user_id);
$stmt_upcoming->execute();
$upcoming_result = $stmt_upcoming->get_result();

// Fetch pickup history
$history_sql = "SELECT pickup_date, time_slot, waste_type, status FROM pickups 
                WHERE user_id = ? AND (status != 'Scheduled' OR pickup_date < CURDATE())
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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard-style.css">
    <link rel="stylesheet" href="admin-style.css">
    <link rel="stylesheet" href="theme.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <a href="index.php" class="logo">EcoWaste</a>
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
                                    <td data-label="Status"><span class="status-badge status-scheduled"><?= htmlspecialchars($row['status']) ?></span></td>
                                    <td data-label="Location">
                                        <?php if ($row['latitude'] && $row['longitude']): ?>
                                            <button class="btn-collect" onclick="showLocationModal(<?= $row['latitude'] ?>, <?= $row['longitude'] ?>)">View Map</button>
                                        <?php else: ?>
                                            No location
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Action">
                                        <form action="cancel_pickup.php" method="POST" onsubmit="return confirmCancel(event, this)">
                                            <input type="hidden" name="pickup_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn-cancel">Cancel</button>
                                        </form>
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
                
                <div class="map-instructions">
                    <p>📍 Click "Get My Location" or click on the map to pin your exact pickup location</p>
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

        // Initialize map
        function initMap() {
            // Default to Bhopal, India
            map = L.map('map').setView([23.2599, 77.4126], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add click event to map
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

        // Initialize map on page load
        document.addEventListener('DOMContentLoaded', initMap);

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
    $conn->close(); 
?>