<?php
// Start session to track logged-in user
session_start();

// Database connection
$host = "localhost"; // Replace with your host
$dbname = "db_pms"; // Replace with your database name
$username = "root"; // Replace with your username
$password = ""; // Replace with your password

$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Get staff_id from session
if (!isset($_SESSION['staff_id'])) {
    die("<p class='error'>Error: Staff not logged in. Please log in again.</p>");
}

$staff_id = $_SESSION['staff_id']; // Retrieve the staff ID from session
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Duty Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background-color: #001f3f; /* Navy Blue */
            color: #fff;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .home-button {
            position: absolute;
            top: 50%;
            left: 20px;
            transform: translateY(-50%);
            background-color: #001f3f; /* Navy Blue */
            color: #fff;
            padding: 10px 15px;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }
        .home-button:hover {
            background-color: #004080; /* Slightly lighter navy blue */
        }
        .content {
            padding: 20px;
        }
        .duty-card {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .duty-card p {
            margin: 5px 0;
        }
        .duty-card strong {
            color: #001f3f; /* Navy Blue */
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .no-duties {
            text-align: center;
            font-weight: bold;
            color: #555;
        }
        hr {
            border: none;
            border-top: 1px solid #ddd;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="staff_dashboard.php" class="home-button">Home</a>
            <h1>Staff Duty Details</h1>
        </div>
        <div class="content">
            <?php
            if ($staff_id > 0) {
                // Query to fetch duties for the specified staff member
                $sql = "SELECT 
                            duty_description, 
                            start_time, 
                            end_time, 
                            police_station_name, 
                            shift_type, 
                            Duty_location, 
                            checkpoint_id, 
                            assigned_date 
                        FROM duties 
                        WHERE staff_id = ?";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $staff_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<div class='duty-card'>";
                            echo "<p><strong>Description:</strong> " . htmlspecialchars($row['duty_description']) . "</p>";
                            echo "<p><strong>Start Time:</strong> " . $row['start_time'] . "</p>";
                            echo "<p><strong>End Time:</strong> " . $row['end_time'] . "</p>";
                            echo "<p><strong>Police Station:</strong> " . htmlspecialchars($row['police_station_name']) . "</p>";
                            echo "<p><strong>Shift Type:</strong> " . $row['shift_type'] . "</p>";
                            echo "<p><strong>Duty_Location:</strong> " . $row['Duty_Location'] . "</p>";
                            if ($row['Duty_Location'] == 'checkpoint') {
                                echo "<p><strong>Checkpoint ID:</strong> " . $row['checkpoint_id'] . "</p>";
                            }
                            echo "<p><strong>Assigned Date:</strong> " . $row['assigned_date'] . "</p>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p class='no-duties'>No duties found for the specified staff member.</p>";
                    }
                } else {
                    echo "<p class='error'>Failed to prepare the SQL statement. Error: " . $conn->error . "</p>";
                }

                $stmt->close();
            } else {
                echo "<p class='error'>Invalid staff ID.</p>";
            }

            $conn->close();
            ?>
        </div>
    </div>
</body>
</html>
