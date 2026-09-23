<?php
// Database connection
$host = 'localhost'; // Change as per your setup
$db = 'db_pms'; // Replace with your database name
$user = 'root'; // Replace with your DB user
$pass = ''; // Replace with your DB password

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all districts for the dropdown
$districts_result = $conn->query("SELECT DISTINCT district FROM police_stations");
$districts = $districts_result->fetch_all(MYSQLI_ASSOC);

// Fetch all tehsils for the dropdown
$tehsils_result = $conn->query("SELECT DISTINCT tehsil FROM police_stations");
$tehsils = $tehsils_result->fetch_all(MYSQLI_ASSOC);

// Initialize query and where clause
$where = '';

// Check if district filter is applied
if (isset($_GET['district']) && !empty($_GET['district']) && !isset($_GET['tehsil'])) {
    $district = $conn->real_escape_string($_GET['district']);
    $where = "WHERE district = '$district'"; // Filter by district only
}
// Check if both district and tehsil filter are applied
elseif (isset($_GET['district']) && !empty($_GET['district']) && isset($_GET['tehsil']) && !empty($_GET['tehsil'])) {
    $district = $conn->real_escape_string($_GET['district']);
    $tehsil = $conn->real_escape_string($_GET['tehsil']);
    $where = "WHERE district = '$district' AND tehsil = '$tehsil'"; // Filter by both district and tehsil
}

$query = "SELECT * FROM police_stations $where";
$stations_result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Police Stations</title>

    <style>
        /* Reset some default styling */
        body, h1, table, form {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        /* General body styling */
        body {
            background-color: #f4f4f9;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        /* Header styling */
        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #003366; /* Navy Blue */
        }

        /* Form styling */
        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        select, button {
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        button {
            background-color: #001f3f; /* Navy Blue */; /* Navy Blue */
            color: white;
            cursor: pointer;
            border: none;
        }

        button:hover {
            background-color: #002244; /* Darker Navy Blue */
        }

        /* Table styling */
        table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }

        table th {
            background-color: #003366; /* Navy Blue */
            color: white;
        }

        table tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }

        table tbody tr:hover {
            background-color: #f1f1f1;
        }

        /* Responsive design for smaller screens */
        @media (max-width: 600px) {
            form {
                width: 100%;
                padding: 15px;
            }

            table th, table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <h1>View Police Stations</h1>

    <!-- Form for district and tehsil selection -->
    <form method="get" action="view_policestation.php">
        <label for="district">Select District:</label>
        <select name="district" id="district">
            <option value="">--Select District--</option>
            <?php foreach ($districts as $district) : ?>
                <option value="<?php echo $district['district']; ?>" <?php echo isset($_GET['district']) && $_GET['district'] == $district['district'] ? 'selected' : ''; ?>>
                    <?php echo $district['district']; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="tehsil">Select Tehsil:</label>
        <select name="tehsil" id="tehsil">
            <option value="">--Select Tehsil--</option>
            <?php foreach ($tehsils as $tehsil) : ?>
                <option value="<?php echo $tehsil['tehsil']; ?>" <?php echo isset($_GET['tehsil']) && $_GET['tehsil'] == $tehsil['tehsil'] ? 'selected' : ''; ?>>
                    <?php echo $tehsil['tehsil']; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="action" value="search_by_district">Search by District & Tehsil</button>
        <button type="submit" name="action" value="search_by_district_only">Search by District Only</button>
        <a href="#" onclick="goBack();" class="btn btn-home btn-block" style="background-color: #001f3f; color: white; padding: 10px 15px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;">
    &#8592; Return
</a>

<script>
function goBack() {
    if (document.referrer) {
        window.location.href = document.referrer; // Go to the previous page
    } else {
        window.location.href = "http://localhost/ams1/ams1"; // Fallback URL
    }
}
</script>
<a href="http://localhost/pms/dashboard.php" class="btn btn-home btn-block" style="background-color: #001f3f; color: white; padding: 10px 15px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;">
    &#8592; Home
</a>
    </form>

    <hr>

    <!-- Only show table if a search was performed -->
    <?php if (isset($_GET['district']) || isset($_GET['tehsil'])): ?>
        <?php if ($stations_result->num_rows > 0): ?>
            <!-- Display police stations in a table -->
            <table>
                <thead>
                    <tr>
                        <th>Police Station Name</th>
                        <th>District</th>
                        <th>Tehsil</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $stations_result->fetch_assoc()) : ?>
                        <tr>
                            <td><?php echo $row['police_station_name']; ?></td>
                            <td><?php echo $row['district']; ?></td>
                            <td><?php echo $row['tehsil']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No police stations found. Please refine your search.</p>
        <?php endif; ?>
    <?php endif; ?>
 
</body>
</html>

<?php
$conn->close();
?>
