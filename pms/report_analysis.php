<?php
// Include your database connection file
include('config.php');

// Fetch distinct districts from the reports table
$districtQuery = "SELECT DISTINCT district FROM reports";
$districtResult = mysqli_query($conn, $districtQuery);

// Fetch distinct complainants from the reports table for the filter
$complainantQuery = "SELECT DISTINCT complainant FROM reports";
$complainantResult = mysqli_query($conn, $complainantQuery);

// Fetch distinct tehsils from the reports table for the filter
$tehsilQuery = "SELECT DISTINCT tehsil FROM reports";
$tehsilResult = mysqli_query($conn, $tehsilQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Analysis</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 90%;
            max-width: 800px;
        }
        h2 {
            color: #001f3f;
            font-size: 28px;
            margin-bottom: 30px;
            font-weight: bold;
        }
        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .filter-form select, .filter-form button, .filter-form input {
            padding: 10px;
            font-size: 16px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
            width: 100%;
        }
        .filter-form button {
            background-color: #007bff;
            color: white;
            cursor: pointer;
        }
        .filter-form button:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #777;
        }
        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Sticky heading and Back button -->
<div style="margin-top:10px ; top: 4;  padding: 20px; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
    <h2 style="margin: 0; font-size: 28px; color: #001f3f;">Report Analysis</h2>
    <a href="javascript:history.back()" style="display: inline-block; margin-top: 10px; color: #007bff; font-size: 16px; text-decoration: none;">&#8592; Back</a>
</div>


        <!-- Filter form -->
        <div class="filter-form">
            <form method="POST">
                <!-- Month and Year filter -->
                <input type="month" name="month_year">

                <!-- Complainant filter -->
                <select name="complainant">
                    <option value="">Select Complainant</option>
                    <?php
                    while ($complainantRow = mysqli_fetch_assoc($complainantResult)) {
                        echo "<option value='" . htmlspecialchars($complainantRow['complainant']) . "'>" . htmlspecialchars($complainantRow['complainant']) . "</option>";
                    }
                    ?>
                </select>

                <!-- District filter -->
                <select name="district">
                    <option value="">Select District</option>
                    <?php
                    while ($districtRow = mysqli_fetch_assoc($districtResult)) {
                        echo "<option value='" . htmlspecialchars($districtRow['district']) . "'>" . htmlspecialchars($districtRow['district']) . "</option>";
                    }
                    ?>
                </select>

                <!-- Tehsil filter -->
                <select name="tehsil">
                    <option value="">Select Tehsil</option>
                    <?php
                    while ($tehsilRow = mysqli_fetch_assoc($tehsilResult)) {
                        echo "<option value='" . htmlspecialchars($tehsilRow['tehsil']) . "'>" . htmlspecialchars($tehsilRow['tehsil']) . "</option>";
                    }
                    ?>
                </select>

                <!-- Search Button -->
                <button type="submit" name="search_reports">Search Reports</button>
            </form>
        </div>

        <?php
        if (isset($_POST['search_reports'])) {
            // Get selected month, complainant, district, and tehsil, check if they're set before accessing them
            $month_year = isset($_POST['month_year']) ? mysqli_real_escape_string($conn, $_POST['month_year']) : '';
            $complainant = isset($_POST['complainant']) ? mysqli_real_escape_string($conn, $_POST['complainant']) : '';
            $district = isset($_POST['district']) ? mysqli_real_escape_string($conn, $_POST['district']) : '';
            $tehsil = isset($_POST['tehsil']) ? mysqli_real_escape_string($conn, $_POST['tehsil']) : '';

            // Start building the query with no filters initially
            $query = "SELECT * FROM reports WHERE 1";

            // If a month is selected, filter by the month
            if (!empty($month_year)) {
                $year = substr($month_year, 0, 4);
                $month = substr($month_year, 5, 2);
                $query .= " AND YEAR(report_date) = '$year' AND MONTH(report_date) = '$month'";
            }

            // Add complainant filter if selected
            if (!empty($complainant)) {
                $query .= " AND complainant = '$complainant'";
            }

            // Add district filter if selected
            if (!empty($district)) {
                $query .= " AND district = '$district'";
            }

            // Add tehsil filter if selected
            if (!empty($tehsil)) {
                $query .= " AND tehsil = '$tehsil'";
            }

            // Fetch results from the database
            $result = mysqli_query($conn, $query);

            // Display results
            if (mysqli_num_rows($result) > 0) {
                echo "<table>
                        <tr>
                            <th>Police Station</th>
                            <th>Accused Name</th>
                            <th>Complainant</th>
                            <th>District</th>
                            <th>Tehsil</th>
                            <th>Report Date</th>
                            <th>Description</th>
                            <th>Status</th>
                        </tr>";

                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>
                            <td>" . htmlspecialchars($row['police_station_name']) . "</td>
                            <td>" . htmlspecialchars($row['accused_name']) . "</td>
                            <td>" . htmlspecialchars($row['complainant']) . "</td>
                            <td>" . htmlspecialchars($row['district']) . "</td>
                            <td>" . htmlspecialchars($row['tehsil']) . "</td>
                            <td>" . htmlspecialchars($row['report_date']) . "</td>
                            <td>" . htmlspecialchars($row['report_description']) . "</td>
                            <td>" . htmlspecialchars($row['status']) . "</td>
                          </tr>";
                }

                echo "</table>";
            } else {
                echo "<p>No reports found for the selected filters.</p>";
            }
        }
        ?>

        <!-- Footer section -->
        <div class="footer">
        <a href="http://localhost/pms/dashboard.php" class="btn btn-home btn-block" style="background-color: #001f3f; color: white; padding: 10px 15px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;">
    &#8592; Home
</a>
            <p>&copy; 2025 Report Analysis System</p>
        </div>
    </div>

</body>
</html>
