<?php
include('config.php'); // Include database connection

// Get staff name from session or use a default value
$staff_name = isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : 'Staff'; 

// Handle alert creation
$alert_status = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_alert'])) {
    $alert_message = mysqli_real_escape_string($conn, $_POST['alert_message']);
    $alert_date = date('Y-m-d H:i:s');

    // Insert alert into database
    $query = "INSERT INTO alerts (message, created_at) VALUES ('$alert_message', '$alert_date')";
    if (mysqli_query($conn, $query)) {
        $alert_status = "Alert created successfully! (Created at: $alert_date)";
    } else {
        $alert_status = "Failed to create alert.";
    }
}

// Fetch all alerts for display
$alerts_query = "SELECT * FROM alerts ORDER BY created_at DESC";
$alerts_result = mysqli_query($conn, $alerts_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Police Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Global Styles */
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #f4f4f4;
        }

        /* Sidebar Styles */
        .sidebar {
            height: 100vh;
            width: 250px;
            background-color: #001f3f;
            color: white;
            position: fixed;
            display: flex;
            flex-direction: column;
            padding-top: 30px;
        }

        .sidebar a {
            color: white;
            padding: 15px;
            text-decoration: none;
            font-size: 22px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #34495e;
        }

        .sidebar a:hover {
            background-color: #34495e;
        }

        .sidebar i {
            margin-right: 10px;
        }

        .sidebar .title {
            color: white;
            padding: 30px;
            font-size: 35px;
            text-align: center;
            font-weight: bold;
        }

        .sidebar hr {
            border: 1px solid #34495e;
            margin: 0;
        }

        /* Dashboard Content */
        .dashboard-container {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }

        /* Welcome Banner */
        .welcome-banner {
            background-color: #001f3f;
            color: white;
            padding: 50px 20px;
            font-size: 30px;
            font-weight: bold;
            text-align: center;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid #bdc3c7;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #2c3e50;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        /* Form and Button Styling */
        .input-field {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .submit-button {
            width: 100%;
            padding: 12px;
            background-color: #001f3f;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .submit-button:hover {
            background-color: #004080;
        }

        /* Alerts Section */
        .alert-box {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            background-color: yellow;
            color: black;
            border: 1px solid #f1c40f;
        }

        /* Form Styling */
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
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="title">PMS</div>
        <hr>
        <a href="generate_report.php"><i class="fas fa-file-alt"></i>Generate Report</a>
        <a href="view_reports.php"><i class="fas fa-folder-open"></i>View Reports</a>
        <a href="leave_request.php"><i class="fas fa-calendar-day"></i>Apply for Leave</a>
        <a href="leave_status.php"><i class="fas fa-check-circle"></i>Leave Status</a>
        <a href="view_staff_duty.php?staff_id=1"><i class="fas fa-tasks"></i> View Duty</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
    </div>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        Welcome to Staff Dashboard
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-container">
        <!-- Alert Box -->
        <?php if ($alert_status): ?>
            <div class="alert-box">
                <?php echo $alert_status; ?>
            </div>
        <?php endif; ?>

        <!-- Create Alert Form -->
        <h2>Create New Alert</h2>
        <form action="staff_dashboard.php" method="POST" class="create-alert-form">
            <textarea name="alert_message" placeholder="Enter your alert message here..." required></textarea>
            <button type="submit" name="create_alert">Create Alert</button>
        </form>

        <!-- View Alerts -->
        <h2>View Alerts</h2>
        <table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Alert Message</th>
            <th>Created At</th>
        </tr>
    </thead>
    <tbody>
        <?php if (mysqli_num_rows($alerts_result) > 0): ?>
            <?php while ($alert = mysqli_fetch_assoc($alerts_result)): ?>
                <?php 
                    // Get the current time and the alert's creation time
                    $current_time = new DateTime();
                    $created_at = new DateTime($alert['created_at']);
                    
                    // Calculate the time difference
                    $interval = $current_time->diff($created_at);
                    
                    // Check if the alert is older than 24 hours
                    if ($interval->days < 1): // Less than 24 hours
                ?>
                    <tr>
                        <td><?php echo $alert['id']; ?></td>
                        <td><?php echo htmlspecialchars($alert['message']); ?></td>
                        <td><?php echo $alert['created_at']; ?></td>
                    </tr>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3">No alerts found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

    </div>
</body>
</html>
