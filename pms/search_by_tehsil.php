<?php
session_start();
include('config.php');

if (!isset($_SESSION['username'])) {
    header("Location: index.php");  // Redirect to login if user is not logged in
    exit();
}

$tehsilsList = ['Kotli Tehsil', 'Khuiratta Tehsil', 'Fatehpur Thakiala Tehsil', 'Sehnsa Tehsil', 'Charhoi Tehsil', 'Duliah Jattan Tehsil','Okra Tehsil','Dhirkot Tehsil'];

if (isset($_GET['tehsil']) && !empty($_GET['tehsil'])) {
    $tehsil = $_GET['tehsil'];

    // Fetch Reports based on Tehsil
    $query = "SELECT * FROM reports WHERE tehsil = '$tehsil'";
    $result = mysqli_query($conn, $query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search by Tehsil</title>
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
            display: block;
            margin: 20px auto;
            text-align: center;
        }

        .home-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

    <div class="form-container">
        <h2>Search by Tehsil</h2>
        
        <!-- Home Button -->
        <a href="dashboard.php" class="home-btn">Home</a>

        <form method="GET" action="search_by_tehsil.php">
            <label for="tehsil">Select Tehsil</label>
            <select name="tehsil" id="tehsil" onchange="this.form.submit()">
                <option value="">Select Tehsil</option>
                <?php
                foreach ($tehsilsList as $tehsilOption) {
                    echo '<option value="' . $tehsilOption . '" ' . (isset($_GET['tehsil']) && $_GET['tehsil'] == $tehsilOption ? 'selected' : '') . '>' . $tehsilOption . '</option>';
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
            echo '<p>No reports found for this tehsil.</p>';
        }
        ?>
    </div>

</body>
</html>
