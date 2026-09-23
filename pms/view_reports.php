<?php
session_start();
include('config.php'); // Ensure your database connection is included

// Check if the user is logged in
if (!isset($_SESSION['staff_id'])) {
    echo "You must log in to view reports.";
    exit;
}

// Fetch the logged-in staff details
$staff_id = $_SESSION['staff_id'];

// Query to fetch reports related to the logged-in staff's police station
$query = "
    SELECT r.* 
    FROM reports r
    JOIN staff s ON r.police_station_name = s.police_station_name
    WHERE s.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $staff_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports</title>
    <style>
        /* Body Styling */
        body {
            background-color: white;
            color: black;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
            font-size: 2rem;
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #2c3e50;
        }

        th {
            background-color: #2c3e50;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #ecf0f1;
        }

        /* Home Button Styling */
        .home-button {
            display: inline-block;
            margin: 20px 0;
            padding: 10px 20px;
            background-color: #001f3d; /* Navy blue */
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
        }

        .home-button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <h1>Reports</h1>
    <a href="staff_dashboard.php" class="home-button">Home</a>
    
    <?php if ($result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Police Station Name</th>
                    <th>Under Section</th>
                    <th>Accused Name</th>
                    <th>Accused Address</th>
                    <th>Complainant</th>
                    <th>Investigation Officer</th>
                    <th>Report Description</th>
                    <th>District</th>
                    <th>Tehsil</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['police_station_name']) ?></td>
                        <td><?= htmlspecialchars($row['under_section']) ?></td>
                        <td><?= htmlspecialchars($row['accused_name']) ?></td>
                        <td><?= htmlspecialchars($row['accused_address']) ?></td>
                        <td><?= htmlspecialchars($row['complainant']) ?></td>
                        <td><?= htmlspecialchars($row['investigation_officer']) ?></td>
                        <td><?= htmlspecialchars($row['report_description']) ?></td>
                        <td><?= htmlspecialchars($row['district']) ?></td>
                        <td><?= htmlspecialchars($row['tehsil']) ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No reports available for your police station.</p>
    <?php endif; ?>
</body>
</html>
