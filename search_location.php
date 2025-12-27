<?php
header('Content-Type: application/json');

if (!isset($_GET['q']) || empty($_GET['q'])) {
    echo json_encode(['error' => 'No query provided']);
    exit;
}

$query = urlencode($_GET['q']);
$url = "https://nominatim.openstreetmap.org/search?format=json&q={$query}&limit=5";

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'EcoWaste Application/1.0 (https://ecowaste.infinityfreeapp.com)');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

// Execute cURL request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo json_encode(['error' => 'Request failed: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Return the response
if ($httpCode == 200) {
    echo $response;
} else {
    echo json_encode(['error' => 'Search service unavailable', 'code' => $httpCode]);
}
?>