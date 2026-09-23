<?php
include 'config.php'; // Include your database connection file
session_start(); // Start session to access logged-in user info

// Check if the police station name exists in the session
if (!isset($_SESSION['police_station_name'])) {
    // If not set, display an error or redirect to login page
    die("You must be logged in to view duties.");
}

// Fetch the police station name of the logged-in admin
$admin_station_name = $_SESSION['police_station_name']; // Assume you stored police_station_name in session during login

// Fetch all duties from the 'duties' table, but only for staff from the same police station as the admin
$query = "SELECT d.id, s.name, s.designation, s.role, d.shift_type, d.Duty_location, d.checkpoint_id
          FROM duties d 
          JOIN staff s ON d.staff_id = s.id
          WHERE s.police_station_name = ?";  // Filter by police station name

// Prepare the query and bind the parameter
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("s", $admin_station_name); // Bind the admin's police station name
    $stmt->execute();
    $result = $stmt->get_result(); // Get the result of the query
} else {
    // If the query preparation fails, display an error
    die("Query preparation failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assigned Duties</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        h2 {
            color: #005B96;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        th {
            background-color: #005B96;
            color: white;
        }
        .success-message {
            color: green;
            font-weight: bold;
        }
        .error-message {
            color: red;
            font-weight: bold;
        }
        .back-link, .home-link {
            margin-top: 20px;
            display: inline-block;
            text-decoration: none;
            color: #fff;
            background-color: #005B96;
            padding: 10px 15px;
            border-radius: 5px;
        }
        .back-link:hover, .home-link:hover {
            background-color: #00477A;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>View Assigned Duties</h2>

    <?php
    // Check if there are any duties assigned
    if ($result->num_rows > 0) {
        echo '<table>';
        echo '<thead><tr><th>Staff Name</th><th>Designation</th><th>Role</th><th>Shift</th><th>Duty_location</th><th>Checkpoint</th></tr></thead>';
        echo '<tbody>';

        // Fetch and display the duties
        while ($row = $result->fetch_assoc()) {
            // Display data in a row for each assigned duty
            echo "<tr>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['designation']}</td>";
            echo "<td>{$row['role']}</td>";
            echo "<td>{$row['shift_type']}</td>";
            echo "<td>{$row['Duty_location']}</td>";

            // Check if location is 'checkpoint', if so display the checkpoint ID, else show "N/A"
            if ($row['Duty_location'] == 'checkpoint') {
                echo "<td>Checkpoint {$row['checkpoint_id']}</td>";
            } else {
                echo "<td>N/A</td>";
            }

            echo "</tr>";
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        // Display message if no duties are assigned
        echo "<p class='error-message'>No duties assigned yet.</p>";
    }
    ?>

    <a href="assign_duties.php" class="back-link">Back to Assign Duties</a>
    <a href="admin_dashboard.php" class="home-link">Home</a>
</div>

</body>
</html>
