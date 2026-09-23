<?php
session_start();
include('config.php'); // Include your database connection file

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");  // Redirect to login if user is not logged in
    exit();
}

$role = $_SESSION['role']; // Get user role (admin or staff)
$username = $_SESSION['username']; // Get logged-in username

// Check for any active alerts
$alerts_query = "SELECT * FROM alerts WHERE is_active = 1 AND created_at >= (NOW() - INTERVAL 3 HOUR) ORDER BY created_at DESC";
$alerts_result = $conn->query($alerts_query);

// Check for query errors
if ($alerts_result === false) {
    // If the query fails, display an error message
    die("Error executing query: " . $conn->error);
}

// Handle the form submission for creating an alert
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['alert_message'])) {
    $alert_message = htmlspecialchars($_POST['alert_message']);
    $created_at = date('Y-m-d H:i:s');
    
    // Insert the new alert into the database
    $insert_query = "INSERT INTO alerts (message, created_at, is_active) VALUES ('$alert_message', '$created_at', 1)";
    if ($conn->query($insert_query) === TRUE) {
        $alert_success = "Alert created successfully!";
    } else {
        $alert_error = "Error creating alert: " . $conn->error;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Police Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* General styles */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #1A237E;
        }

        /* Sidebar styles */
        .sidebar {
            width: 200px;
            background-color: #001f3f; /* Navy Blue */
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0px 10px rgba(0, 0, 0, 0.1);
            color: white;
        }

        .sidebar .pms-heading {
            text-align: center;
            font-size: 1.8rem;
            font-weight: bold;
            padding: 20px 0;
            background-color: #001f3f; /* Navy Blue */
            margin: 0;
            border-bottom: 2px solid #34495e;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            margin: 5px 0;
            display: flex;
            align-items: center;
            font-size: 1rem;
            transition: background 0.3s, padding-left 0.3s;
        }

        .sidebar a i {
            margin-right: 10px;
        }

        .sidebar a:hover {
            background-color: #001f3f; /* Navy Blue */
            padding-left: 25px;
        }

        /* Main content styles */
        .dashboard-container {
            margin-left: 220px;
            padding: 20px;
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
            position: relative;
        }

        .welcome-banner {
            background-color: #001f3f; /* Navy Blue */
            color: white;
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: absolute;
            top: 20px; /* Adjust this value to control the vertical positioning */
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 40px); /* Adds some responsiveness */
            max-width: 800px;
        }

        /* Alerts Section */
        .alerts-container {
            margin-top: 150px; /* Added margin to give space from the banner */
            background-color: #fff; 
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            width: calc(100% - 40px); /* Increased width of alerts */
            max-width: 900px;
        }

        .alert {
            background-color: #ffeb3b; /* Yellow */
            color: #333;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-size: 1.1rem;
        }

        /* Create Alert Form */
        .create-alert-form {
            margin-top: 20px;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            color: #333;
            width: 100%;
            max-width: 900px;
        }

        .create-alert-form textarea {
            width: 100%;
            padding: 10px;
            font-size: 1rem;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
        }

        .create-alert-form button {
            background-color: #001f3f;
            color: #ffffff;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
        }

        .create-alert-form button:hover {
            background-color: #003366;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- PMS Heading -->
        <div class="pms-heading">PMS</div>

        <!-- Sidebar links -->
        <?php if ($role == 'admin') { ?>
            <a href="add_staff.php"><i class="fas fa-user-plus"></i>Add Staff</a>
            <a href="add_police_station.php"><i class="fas fa-building"></i>Add Police Station</a>
            <a href="view_staff.php"><i class="fas fa-users"></i>View Staff</a>
            <a href="view_policestation.php"><i class="fas fa-map-marker-alt"></i>View Police Station</a>
            <a href="search_criminal.php" class="view-criminal-link"><i class="fas fa-search"></i>View Criminal</a>
            <a href="report_analysis.php"><i class="fas fa-chart-bar"></i>Report Analysis</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
        <?php } elseif ($role == 'staff') { ?>
            <a href="view_reports.php"><i class="fas fa-folder-open"></i>View Reports</a>
        <?php } ?>
    </div>

    <!-- Main Content -->
    <div class="dashboard-container">
        <div class="welcome-banner">
            Welcome to the Police Management System, <?php echo htmlspecialchars($username); ?>!
        </div>

        <!-- Display Active Alerts -->
        <div class="alerts-container">
            <?php while ($alert = $alerts_result->fetch_assoc()) { ?>
                <div class="alert">
                    <p><strong>Alert:</strong> <?= htmlspecialchars($alert['message']); ?></p>
                    <p><small>Created at: <?= $alert['created_at']; ?></small></p>
                </div>
            <?php } ?>
        </div>

        <!-- Alert Creation Form -->
        <?php if ($role == 'admin') { ?>
            <div class="create-alert-form">
                <?php if (isset($alert_success)) { ?>
                    <p style="color: green;"><?= $alert_success; ?></p>
                <?php } elseif (isset($alert_error)) { ?>
                    <p style="color: red;"><?= $alert_error; ?></p>
                <?php } ?>
                <form method="POST">
                    <textarea name="alert_message" placeholder="Enter alert message..." required></textarea>
                    <br><br>
                    <button type="submit">Create Alert</button>
                </form>
            </div>
        <?php } ?>
    </div>

</body>
</html>
