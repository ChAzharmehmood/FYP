<?php
session_start();
include('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");  // Redirect to login if user is not logged in
    exit();
}

$districtsList = ['Kotli District', 'Mirpur District', 'Bimber District', 'Muzaffarabad District', 'Bagh District'];

if (isset($_GET['district']) && !empty($_GET['district'])) {
    $district = $_GET['district'];

    // Fetch Reports based on District
    $query = "SELECT * FROM reports WHERE district = '$district'";
    $result = mysqli_query($conn, $query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search by District</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #001f3f;
            margin: 0;
            padding: 0;
        }

        .form-container {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h2 {
            color: #001f3f;
            text-align: center;
        }

        select, button {
            padding: 10px;
            font-size: 16px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
            background-color: #001f3f;
            color: white;
            cursor: pointer;
        }

        button:hover, select:hover {
            background-color: #0056b3;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }

        table th {
            background-color: #001f3f;
            color: white;
        }

        table td {
            background-color: #f9f9f9;
        }

        .home-btn {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #001f3f;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            text-decoration: none;
        }

        .home-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

    <div class="form-container">
        <h2>Search by District</h2>
        <!-- Home Button -->
        <a href="dashboard.php" class="home-btn">Home</a>

        <form method="GET" action="search_by_district.php">
            <label for="district">Select District</label>
            <select name="district" id="district" onchange="this.form.submit()">
                <option value="">Select District</option>
                <?php
                foreach ($districtsList as $districtOption) {
                    echo '<option value="' . $districtOption . '" ' . (isset($_GET['district']) && $_GET['district'] == $districtOption ? 'selected' : '') . '>' . $districtOption . '</option>';
                }
                ?>
            </select>
        </form>

        <?php
        if (isset($result) && mysqli_num_rows($result) > 0) {
            echo '<table>';
            echo '<thead><tr><th>ID</th><th>Police Station</th><th>District</th><th>Tehsil</th><th>Accused Name</th><th>Complainant</th><th>Report Description</th></tr></thead>';
            echo '<tbody>';
            while ($report = mysqli_fetch_assoc($result)) {
                echo '<tr>
                        <td>' . $report['id'] . '</td>
                        <td>' . $report['police_station_name'] . '</td>
                        <td>' . $report['district'] . '</td>
                        <td>' . $report['tehsil'] . '</td>
                        <td>' . $report['accused_name'] . '</td>
                        <td>' . $report['complainant'] . '</td>
                        <td>' . $report['report_description'] . '</td>
                    </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No reports found for this district.</p>';
        }
        ?>
    </div>

</body>
</html>
