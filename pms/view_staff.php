<?php
session_start();
include('config.php'); // Include your database connection file

// Check if the user is logged in and has the appropriate role
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php"); // Redirect if not logged in or not an admin
    exit();
}

// Fetch all police stations for the dropdown
$stationsQuery = "SELECT id, police_station_name FROM police_stations";
$stationsResult = mysqli_query($conn, $stationsQuery);
$stations = [];
if (mysqli_num_rows($stationsResult) > 0) {
    while ($row = mysqli_fetch_assoc($stationsResult)) {
        $stations[] = $row;
    }
}

// Initialize variables
$staffList = [];
$selectedStation = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get selected police station ID
    $selectedStation = $_POST['station_id'];

    // Fetch staff members for the selected police station
    $staffQuery = "SELECT name, designation, role FROM staff WHERE police_station_name = ?";
    $stmt = $conn->prepare($staffQuery);
    
    if ($stmt === false) {
        die('Error in preparing SQL statement: ' . $conn->error);
    }

    $stmt->bind_param('s', $selectedStation); // 's' for string as police_station_name is a string
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $staffList = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $staffList = [];
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Staff - Police Management</title>
    <style>
        /* General styles */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #1A237E;
        }

        /* Main content styles */
        .dashboard-container {
            margin: 0;
            padding: 20px;
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
            position: relative;
        }

        .welcome-banner {
            background-color: #001f3f; /* Navy Blue */
            color: white;
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: absolute;
            top: 20px; /* Adjust this value to control the vertical positioning */
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 40px); /* Adds some responsiveness */
            max-width: 800px;
        }

        .home-button {
            background-color: #001f3f; /* Navy Blue */
            color: white;
            padding: 12px 25px;
            font-size: 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
            text-decoration: none;
        }

        .home-button:hover {
            background-color: #218838;
        }

        /* Form styling */
        form {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            margin-top: 100px;
        }

        form label {
            font-size: 1rem;
            margin-bottom: 8px;
            font-weight: bold;
        }

        form input, form select {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
        }

        form button {
            background-color: #001f3f; /* Navy Blue */
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
        }

        form button:hover {
            background-color: #34495e;
        }

        .success-message {
            background-color: #28a745;
            color: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            width: 100%;
            max-width: 500px;
        }

        /* Table styling */
        table {
            width: 80%;
            margin-top: 30px;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #1A237E;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

    </style>
</head>
<body>

    <div class="dashboard-container">
        <div class="welcome-banner">
            View Staff by Police Station
        </div>

        <form method="POST" action="view_staff.php">
            <label for="station_id">Select Police Station</label>
            <select id="station_id" name="station_id" required>
                <option value="">Select Police Station</option>
                <?php foreach ($stations as $station): ?>
                    <option value="<?php echo $station['police_station_name']; ?>" 
                        <?php echo $selectedStation == $station['police_station_name'] ? 'selected' : ''; ?>>
                        <?php echo $station['police_station_name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">View Staff</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <h2>Staff Members</h2>
            <?php if (!empty($staffList)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffList as $staff): ?>
                            <tr>
                                <td><?php echo $staff['name']; ?></td>
                                <td><?php echo $staff['designation']; ?></td>
                                <td><?php echo ucfirst($staff['role']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No staff members found for the selected police station.</p>
            <?php endif; ?>
        <?php endif; ?>
        <a href="http://localhost/pms/dashboard.php" class="btn btn-home btn-block" style="background-color: #001f3f; color: white; padding: 10px 15px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;">
    &#8592; Home
</a>


    </div>

</body>
</html>
