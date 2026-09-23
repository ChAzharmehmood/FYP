<?php
include 'config.php'; 
session_start();

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");  // Redirect to login if user is not logged in
    exit();
}

$role = $_SESSION['role']; // Get user role (admin or staff)
$username = $_SESSION['username']; // Get logged-in username

// Check for any active alerts
$alerts_query = "SELECT * FROM alerts WHERE is_active = 1 ORDER BY created_at DESC";
$alerts_result = $conn->query($alerts_query);

// Check for query errors
if ($alerts_result === false) {
    die("Error executing query: " . $conn->error);
}

// Handle alert creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_alert'])) {
    $alert_message = $_POST['alert_message'];

    if (!empty($alert_message)) {
        // Insert the new alert into the database
        $create_alert_query = "INSERT INTO alerts (message, is_active, created_at) VALUES ('$alert_message', 1, NOW())";
        if ($conn->query($create_alert_query)) {
            $alert_success = "Alert created successfully!";
        } else {
            $alert_error = "Error creating alert: " . $conn->error;
        }
    } else {
        $alert_error = "Alert message cannot be empty!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Police Station Admin Dashboard</title>
    <style>
        /* General Styles */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f0f4f8;
            color: #ffffff;
        }

        /* Dashboard Container */
        .dashboard {
            text-align: center;
            padding: 20px;
            background-color: #001f3f;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Heading */
        .dashboard h1 {
            font-size: 2.5rem;
            color: #ffffff;
            margin: 0;
            padding: 20px;
        }

        /* Menu */
        .menu {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        /* Menu Links */
        .menu a {
            display: inline-block;
            text-decoration: none;
            font-size: 1.2rem;
            color: #ffffff;
            background-color: #003366;
            padding: 15px 30px;
            border-radius: 8px;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        /* Hover Effects for Menu Links */
        .menu a:hover {
            background-color: #00509e;
            transform: translateY(-3px);
        }

        /* Logout Button */
        .logout-btn {
            display: inline-block;
            text-decoration: none;
            font-size: 1.2rem;
            color: #ffffff;
            background-color: #d9534f;
            padding: 15px 30px;
            border-radius: 8px;
            margin-top: 20px;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        /* Hover Effects for Logout Button */
        .logout-btn:hover {
            background-color: #c9302c;
            transform: translateY(-3px);
        }

        /* Alerts Section */
        .alerts-container {
            margin-top: 30px;
            background-color: #ffeb3b;
            color: #333;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }

        .alert {
            background-color: #ffeb3b;
            color: #333;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .create-alert-form {
            margin-top: 20px;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            color: #333;
        }

        .create-alert-form textarea {
            width: 100%;
            padding: 10px;
            font-size: 1rem;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .create-alert-form button {
            background-color: #001f3f;
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .create-alert-form button:hover {
            background-color: #003366;
        }

    </style>
</head>
<body>

    <div class="dashboard">
        <h1>Police Station Admin Dashboard</h1>
        
        <!-- Menu -->
        <div class="menu">
            <a href="manage_staff.php">Manage Staff</a>
            <a href="manage_reports.php">Manage Reports</a>
            <a href="assign_duties.php">Assign Duties</a>
            <a href="view_duties.php">View Duties</a>
            <a href="admin_leave_requests.php">Manage Leave Requests</a>
        </div>

        <!-- Alert Creation Form -->
        <div class="create-alert-form">
            <h2>Create New Alert</h2>
            <?php if (isset($alert_success)) { echo "<p style='color: green;'>$alert_success</p>"; } ?>
            <?php if (isset($alert_error)) { echo "<p style='color: red;'>$alert_error</p>"; } ?>
            <form method="POST">
                <textarea name="alert_message" placeholder="Enter alert message" required></textarea><br>
                <button type="submit" name="create_alert">Create Alert</button>
            </form>
        </div>

        <!-- Display Active Alerts -->
        <div class="alerts-container">
    <h2>Active Alerts</h2>
    <?php 
    $current_time = time(); // Get current timestamp

    while ($alert = $alerts_result->fetch_assoc()) { 
        $alert_time = strtotime($alert['created_at']); // Convert alert time to timestamp
        $time_difference = $current_time - $alert_time; // Calculate time difference

        if ($time_difference <= 86400) { // 86400 seconds = 24 hours
    ?>
            <div class="alert">
                <p><strong>Alert:</strong> <?= htmlspecialchars($alert['message']); ?></p>
                <p><small>Created at: <?= $alert['created_at']; ?></small></p>
            </div>
    <?php 
        }
    } 
    ?>
</div>


        <!-- Logout -->
        <a href="index.php" class="logout-btn">Logout</a>
    </div>
   
</body>
</html>
