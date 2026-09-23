<?php
session_start();
include('config.php'); // Include your database connection file

// Check if the user is logged in and has the appropriate role
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php"); // Redirect if not logged in or not an admin
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get data from the form
    $station_name = $_POST['station_name'];
    $district = $_POST['district'];  // Updated field to match the database
    $tehsil = $_POST['tehsil'];      // Updated field to match the database

    // Insert data into the database
    $sql = "INSERT INTO police_stations (police_station_name, district, tehsil) VALUES ('$station_name', '$district', '$tehsil')";
    
    if (mysqli_query($conn, $sql)) {
        $successMessage = "Police Station added successfully"; // Set success message
    } else {
        echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Police Station - Police Management</title>
    <style>
        /* General body styles */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        /* Dashboard container styles */
        .dashboard-container {
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
            position: relative;
        }

        .welcome-banner {
            background-color: #001f3f; /* Navy Blue */;
            color: white;
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 40px); /* Adds some responsiveness */
            max-width: 800px;
        }

        .home-button {
            background-color: #001f3f; /* Navy Blue */;
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
            background-color: #001f3f; /* Navy Blue */;
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
    </style>
</head>
<body>

    <div class="dashboard-container">
        <div class="welcome-banner">
            Add New Police Station
        </div>

        <!-- Success message display -->
        <?php if (isset($successMessage)): ?>
            <div class="success-message">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>

        <!-- Home Button -->
        <a href="dashboard.php" class="home-button">Back to Dashboard</a>

        <form method="POST" action="add_police_station.php">
            <label for="station_name">Police Station Name</label>
            <input type="text" id="station_name" name="station_name" required>

            <label for="district">District</label>
            <input type="text" id="district" name="district" required>

            <label for="tehsil">Tehsil</label>
            <input type="text" id="tehsil" name="tehsil" required>

            <button type="submit">Add Police Station</button>
        </form>
        <space></space>
            <a href="http://localhost/pms/dashboard.php" class="btn btn-home btn-block" style="background-color: #001f3f; color: white; padding: 10px 15px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;">
    &#8592; Home
</a>


    </div>
    
</body>
</html>
