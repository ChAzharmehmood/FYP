<?php
session_start(); // Start the session

include('config.php'); // Include the database configuration file

// Ensure the database connection is established
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Check if the user is logged in
if (!isset($_SESSION['staff_id'])) {
    die("Error: Staff member information is not set. Please log in again.");
}

// Fetch the staff ID from the session (stored during login)
$staffId = $_SESSION['staff_id'];

// Function to fetch leave requests for the logged-in staff
function fetchLeaveRequests($staffId)
{
    global $conn; // Use the global $conn variable for database connection

    // Query to fetch leave requests based on the staff ID
    $query = "SELECT * FROM leave_requests WHERE staff_id = ?";  // Ensure column name matches
    $stmt = $conn->prepare($query); // Use prepared statements to prevent SQL injection
    if (!$stmt) {
        die("Database query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("i", $staffId); // Bind the staff ID parameter
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close(); // Close the statement
    return $result;
}

// Fetch leave requests for the staff
$leaveRequests = fetchLeaveRequests($staffId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Status - Staff Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #ecf0f1;
            color: #2c3e50;
        }
        .dashboard-container {
            padding: 20px;
        }
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
        .error-message {
            color: red;
            font-weight: bold;
        }
        .home-button {
            background-color: #001f3d; /* Navy blue */
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            display: inline-block;
            margin-bottom: 20px;
        }
        .home-button:hover {
            background-color: #0056b3; /* Darker shade of blue for hover effect */
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <a href="staff_dashboard.php" class="home-button">Home</a> <!-- Home Button -->
        <h2>Leave Status</h2>

        <table>
            <thead>
                <tr>
                    <th>Leave ID</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Approved Days</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($leaveRequests && $leaveRequests->num_rows > 0) {
                    // Display each leave request in a table row
                    while ($row = $leaveRequests->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($row['id']) . "</td>
                                <td>" . htmlspecialchars($row['leave_type']) . "</td>
                                <td>" . htmlspecialchars($row['leave_start_date']) . "</td>
                                <td>" . htmlspecialchars($row['leave_end_date']) . "</td>
                                <td>" . htmlspecialchars($row['status']) . "</td>
                                <td>" . htmlspecialchars($row['reason']) . "</td>
                                <td>" . htmlspecialchars($row['approved_days']) . "</td>
                                <td>" . htmlspecialchars($row['created_at']) . "</td>
                                <td>" . htmlspecialchars($row['updated_at']) . "</td>
                            </tr>";
                    }
                } else {
                    // If no leave requests are found
                    echo "<tr><td colspan='9' class='error-message'>No leave requests found for you.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
