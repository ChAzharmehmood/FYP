<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'db_pms');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session
session_start();

// Check if the user is logged in and if they are an admin station
if (!isset($_SESSION['staff_id']) || $_SESSION['role'] != 'admin station') {
    // Redirect the user to the login page or show an error
    header('Location: index.php');
    exit();
}

// Get the admin's staff_id and police station from the session
$adminStaffId = $_SESSION['staff_id'];
$adminPoliceStation = $_SESSION['police_station_name']; // Assuming the session contains the police station name

// Handle leave approval or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['leave_id'])) {
    $leaveId = intval($_POST['leave_id']);
    $action = $_POST['action'];

    if ($action === 'approve' && isset($_POST['approved_days'])) {
        $approvedDays = intval($_POST['approved_days']);
        if ($approvedDays < 0) {
            echo "<script>alert('Approved days cannot be negative.');</script>";
        } else {
            // Update the leave request to 'Approved'
            $updateQuery = "UPDATE leave_requests 
                            SET status = 'Approved', approved_days = $approvedDays 
                            WHERE id = $leaveId";
            if ($conn->query($updateQuery)) {
                echo "<script>alert('Leave request approved successfully.');</script>";
            } else {
                echo "<script>alert('Error updating leave request.');</script>";
            }
        }
    } elseif ($action === 'reject') {
        // Update the leave request to 'Rejected' and set approved_days to 0
        $updateQuery = "UPDATE leave_requests 
                        SET status = 'Rejected', approved_days = 0 
                        WHERE id = $leaveId";
        if ($conn->query($updateQuery)) {
            echo "<script>alert('Leave request rejected successfully.');</script>";
        } else {
            echo "<script>alert('Error rejecting leave request.');</script>";
        }
    }
}

// Fetch leave requests for the admin's police station
$leaveQuery = "SELECT lr.*, s.name as staff_name, s.police_station_name 
               FROM leave_requests lr 
               INNER JOIN staff s ON lr.staff_id = s.id 
               WHERE s.police_station_name = '$adminPoliceStation'"; // Filter by admin's police station
$leaveResult = $conn->query($leaveQuery);

// Function to calculate total approved leaves for a staff member
function getTotalApprovedLeaves($staffId) {
    global $conn;
    $totalLeavesQuery = "SELECT SUM(approved_days) AS total_leaves 
                         FROM leave_requests 
                         WHERE staff_id = $staffId AND status = 'Approved'";
    $result = $conn->query($totalLeavesQuery);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['total_leaves'] ?? 0; // Return 0 if no approved leaves found
    }
    return 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Staff Leaves</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #343a40;
        }
        .navbar {
            background-color: #007bff;
            padding: 10px;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }
        .navbar a:hover {
            text-decoration: underline;
        }
        h2 {
            color: #007bff;
        }
        .table {
            background-color: white;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="admin_dashboard.php" class="btn btn-home">Home</a>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <h2>Staff Leave Requests</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Staff Name</th>
                    <th>Leave Period</th>
                    <th>Status</th>
                    <th>Requested Days</th>
                    <th>Approved Days</th>
                    
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Ensure the result is valid before processing
                if ($leaveResult && $leaveResult->num_rows > 0) {
                    while ($row = $leaveResult->fetch_assoc()) {
                        $staffName = $row['staff_name'];
                        $startDate = $row['leave_start_date'] ?? 'N/A';
                        $endDate = $row['leave_end_date'] ?? 'N/A';
                        $requestedDays = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1;
                        $approvedDays = $row['approved_days'] ?? 0;

                        echo "<tr>
                            <td>{$row['id']}</td>
                            <td>{$staffName}</td>
                            <td>{$startDate} to {$endDate}</td>
                            <td>{$row['status']}</td>
                            <td>{$requestedDays}</td>
                            <td>{$approvedDays}</td>
                            
                            <td>
                                <form method='POST' style='display: inline-block;'>
                                    <input type='hidden' name='leave_id' value='{$row['id']}'>
                                    <input type='hidden' name='action' value='approve'>
                                    <input type='number' name='approved_days' class='form-control mb-2' placeholder='Days' required>
                                    <button type='submit' class='btn btn-success btn-sm'>Approve</button>
                                </form>
                                <form method='POST' style='display: inline-block;'>
                                    <input type='hidden' name='leave_id' value='{$row['id']}'>
                                    <input type='hidden' name='action' value='reject'>
                                    <button type='submit' class='btn btn-danger btn-sm'
                                            onclick='return confirm(\"Are you sure you want to reject this leave request?\");'>
                                        Reject
                                    </button>
                                </form>
                            </td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='8' class='text-center'>No leave requests found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
