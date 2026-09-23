<?php
include 'config.php';
session_start();

// Check if the user is logged in and has the role of 'admin' or 'admin station'
if (!isset($_SESSION['staff_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'admin station')) {
    header('Location: login.php');
    exit();
}

// Get police station name from session for 'admin station'
$police_station_name = $_SESSION['role'] == 'admin station' ? $_SESSION['police_station_name'] : '';

// Fetch reports based on police station name if the user is an 'admin station'
if ($_SESSION['role'] == 'admin station') {
    $query = "SELECT * FROM reports WHERE police_station_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $police_station_name);
} else {
    // Fetch all reports if the user is an admin (not limited to any police station)
    $query = "SELECT * FROM reports";
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reports</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
        }
        h2 {
            text-align: center;
            background-color: #001f3f;
            color: white;
            padding: 10px;
        }
        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: white;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #003366;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        button {
            padding: 5px 10px;
            background-color: #003366;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #00509e;
        }
        .editable {
            cursor: pointer;
        }
        .home-button {
            display: inline-block;
            margin: 10px auto;
            padding: 10px 20px;
            background-color: #005B96;
            background-color: ;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
        }
        .home-button:hover {
            background-color: #218838;
        }
    </style>

    <script>
        // Function to confirm deletion
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this report?")) {
                window.location.href = "delete_report.php?id=" + id;
            }
        }

        // Function to open the edit form
        function openEditForm(id) {
            window.location.href = "edit_report.php?id=" + id;
        }
    </script>

</head>
<body>

    <h2>Manage Reports</h2>

    <div style="text-align: center;">
        <a href="admin_dashboard.php" class="home-button">Home</a>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Police Station</th>
            <th>District</th>
            <th>Tehsil</th>
            <th>Accused</th>
            <th>Complainant</th>
            <th>Investigation Officer</th>
            <th>Status</th>  <!-- New Status Column -->
            <th>Actions</th>
        </tr>

        <?php
        // Display reports only for the logged-in police station or all if admin
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['police_station_name']}</td>
                    <td>{$row['district']}</td>
                    <td>{$row['tehsil']}</td>
                    <td>{$row['accused_name']}</td>
                    <td>{$row['complainant']}</td>
                    <td>{$row['investigation_officer']}</td>
                    <td>{$row['status']}</td>  <!-- Display Status Data -->
                    <td>
                        <button onclick='openEditForm({$row['id']})'>Edit</button>
                        <a href='javascript:void(0)' onclick='confirmDelete({$row['id']})'><button>Delete</button></a>
                    </td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='9' style='text-align: center;'>No reports found for your police station.</td></tr>";
        }
        ?>
    </table>

    <div style="text-align: center; margin-top: 20px;">
        <?php if ($_SESSION['role'] != 'admin station') { ?>
            <a href="add_report.php"><button>Add New Report</button></a>
        <?php } ?>
    </div>

</body>
</html>
