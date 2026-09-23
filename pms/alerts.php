<?php
include 'config.php';
session_start();

// Function to expire alerts older than 3 hours
function expireOldAlerts($conn) {
    $query = "UPDATE alerts SET is_active = 0 WHERE created_at < NOW() - INTERVAL 3 HOUR";
    $conn->query($query);
}

// Create Alert (for any dashboard)
if (isset($_POST['create_alert'])) {
    $alert_message = $_POST['alert_message'];

    // Insert the alert into the database
    $query = "INSERT INTO alerts (alert_message) VALUES ('$alert_message')";
    if ($conn->query($query) === TRUE) {
        $_SESSION['alert_message'] = "Alert created successfully.";
    } else {
        $_SESSION['alert_message'] = "Error creating alert: " . $conn->error;
    }
}

// Expire old alerts on every page load
expireOldAlerts($conn);

// Fetch active alerts (only those created within the last 3 hours)
$alerts_query = "SELECT * FROM alerts WHERE is_active = 1 ORDER BY created_at DESC";
$alerts_result = $conn->query($alerts_query);
?>
