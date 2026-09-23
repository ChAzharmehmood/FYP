<?php
session_start();
include('config.php'); // Include your database connection file

// Fetch data logic for staff
function fetchStaffData() {
    global $conn;
    $query = "SELECT * FROM staff"; // Replace 'staff' with your actual staff table name
    $result = mysqli_query($conn, $query);
    return $result;
}

// Fetch police station data logic
function fetchPoliceStations() {
    global $conn;
    $query = "SELECT * FROM police_stations"; // Replace with your actual police station table name
    $result = mysqli_query($conn, $query);
    return $result;
}

// Fetch reports data
function fetchReportsData() {
    global $conn;
    $query = "SELECT * FROM reports"; // Replace with your actual reports table name
    $result = mysqli_query($conn, $query);
    return $result;
}

// Add staff logic
if (isset($_POST['add_staff'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    $query = "INSERT INTO staff (name, password, role) VALUES ('$name', '$password', '$role')";
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Staff added successfully!');</script>";
    } else {
        echo "<script>alert('Error adding staff: " . mysqli_error($conn) . "');</script>";
    }
}

// Add police station logic
if (isset($_POST['add_police_station'])) {
    $name = mysqli_real_escape_string($conn, $_POST['police_station_name']);
    $district = mysqli_real_escape_string($conn, $_POST['district']);
    $tehsil = mysqli_real_escape_string($conn, $_POST['tehsil']);

    $query = "INSERT INTO police_stations (police_station_name, district, tehsil) VALUES ('$name', '$district', '$tehsil')";
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Police Station added successfully!');</script>";
    } else {
        echo "<script>alert('Error adding Police Station: " . mysqli_error($conn) . "');</script>";
    }
}

// Add report logic
if (isset($_POST['add_report'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $submitted_by = $_SESSION['username'];

    $query = "INSERT INTO reports (title, description, submitted_by) VALUES ('$title', '$description', '$submitted_by')";
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Report added successfully!');</script>";
    } else {
        echo "<script>alert('Error adding report: " . mysqli_error($conn) . "');</script>";
    }
}

if (!isset($_SESSION['username'])) {
    header("Location: index.php");  // Redirect to login if user is not logged in
    exit();
}

$role = $_SESSION['role']; // Get user role (admin or staff)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Police Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #ecf0f1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }
        .sidebar {
            width: 200px;
            background-color: #2C3E50;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            padding-top: 20px;
            box-shadow: 2px 0px 10px rgba(0, 0, 0, 0.1);
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
            background-color: #34495e;
            padding-left: 25px;
        }
        .dashboard-container {
            margin-left: 220px;
            padding: 20px;
            width: calc(100% - 220px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }
        .dashboard-header {
            width: 100%;
            background-color: #2C3E50;
            color: white;
            text-align: center;
            font-size: 1.8rem;
            font-weight: bold;
            padding: 15px 0;
            margin-bottom: 20px;
        }
        .dashboard {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            align-items: center;
        }
        .dashboard-card {
            width: 200px;
            height: 150px;
            background-color: #3498db;
            color: white;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.3s, background-color 0.3s;
        }
        .dashboard-card:hover {
            transform: scale(1.05);
            background-color: #2980b9;
        }
        .dashboard-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .dashboard-card h3 {
            margin: 0;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <?php if ($role == 'admin') { ?>
            <a href="?view=add_staff"><i class="fas fa-user-plus"></i>Add Staff</a>
            <a href="?view=add_police_station"><i class="fas fa-building"></i>Add Police Station</a>
            <a href="?view=generate_report"><i class="fas fa-file-alt"></i>Generate Report</a>
            <a href="?view=staff"><i class="fas fa-users"></i>View Staff</a>
            <a href="?view=police_stations"><i class="fas fa-map-marker-alt"></i>View Police Stations</a>
        <?php } elseif ($role == 'staff') { ?>
            <a href="?view=view_reports"><i class="fas fa-folder-open"></i>View Reports</a>
        <?php } ?>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-header">
            Welcome to <?php echo ucfirst($role); ?> Dashboard
        </div>
        
        <!-- Dynamic Content Handling -->
        <?php
        if (isset($_GET['view'])) {
            $view = $_GET['view'];

            // Existing functionality for adding staff and police stations

            // View Reports (New Feature)
            
                $reportsData = fetchReportsData();
                echo '<h2>Submitted Reports</h2>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Title</th><th>Description</th><th>Submitted By</th></tr>';
                while ($report = mysqli_fetch_assoc($reportsData)) {
                    echo '<tr>';
                    echo '<td>' . $report['id'] . '</td>';
                    echo '<td>' . $report['title'] . '</td>';
                    echo '<td>' . $report['description'] . '</td>';
                    echo '<td>' . $report['submitted_by'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        
        ?>
    </div>
</body>
</html>
