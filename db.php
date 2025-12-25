<?php
// Start a session to manage user login state
session_start();

// Database credentials
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // Default XAMPP password is empty
$DB_NAME = 'ecowaste_db';

// Create a connection
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>