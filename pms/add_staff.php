<?php
session_start();
include('config.php'); // Include your database connection file

// Check if the user is logged in and has the appropriate role
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php"); // Redirect if not logged in or not an admin
    exit();
}

$successMessage = ""; // Initialize a variable for success message
$errorMessage = ""; // Initialize an error message variable

// Fetch police stations data for dropdowns
$stationsQuery = "SELECT id, police_station_name, district, tehsil FROM police_stations";
$stationsResult = mysqli_query($conn, $stationsQuery);
$stations = [];
if (mysqli_num_rows($stationsResult) > 0) {
    while ($row = mysqli_fetch_assoc($stationsResult)) {
        $stations[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get data from the form
    $name = $_POST['name'];
    $password = $_POST['password']; // Add password field
    $email = $_POST['email']; // Get email from the form
    $designation = $_POST['designation'];
    $role = $_POST['role']; // Added role field
    $station_id = $_POST['station_id']; // station_id for police station selection
    $id_card_no = $_POST['id_card_no']; // Get ID Card Number from the form

    // Fetch the police station name by id
    $stationQuery = "SELECT police_station_name FROM police_stations WHERE id = ?";
    if ($stmt = $conn->prepare($stationQuery)) {
        $stmt->bind_param("i", $station_id); // Bind the station_id parameter
        $stmt->execute();
        $stmt->bind_result($police_station_name);
        $stmt->fetch();
        $stmt->close();
    }

    // Prepare the SQL query to insert data
    if ($stmt = $conn->prepare("INSERT INTO staff (name, email, password, designation, role, police_station_name, id_card_no) VALUES (?, ?, ?, ?, ?, ?, ?)")) {
        // Bind the parameters to the query
        $stmt->bind_param("sssssss", $name, $email, $password, $designation, $role, $police_station_name, $id_card_no);

        // Execute the query
        if ($stmt->execute()) {
            $successMessage = "Staff added successfully"; // Set success message
        } else {
            $errorMessage = "Error executing query: " . $stmt->error; // Display error message if query fails
        }

        // Close the statement
        $stmt->close();
    } else {
        $errorMessage = "Error preparing the SQL query: " . $conn->error;
    }
}

// Close the database connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff - Police Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* General styles */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #1A237E;
        }

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
            background-color: #001f3f;
            color: white;
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            padding: 20px;
            border-radius: 10px;
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 40px);
            max-width: 800px;
        }

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
            background-color: #001f3f;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
        }

        .success-message, .error-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            max-width: 500px;
        }

        .success-message {
            background-color: #28a745;
            color: white;
        }

        .error-message {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <div class="welcome-banner">
            Add New Staff
        </div>

        <?php if ($successMessage): ?>
            <div class="success-message">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error-message">
                <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>

        
        <form method="POST" action="add_staff.php">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <label for="designation">Designation</label>
            <input type="text" id="designation" name="designation" required>

            <label for="id_card_no">ID Card Number</label>
            <input type="text" id="id_card_no" name="id_card_no" required>

            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="admin">Admin</option>
                <option value="staff">Staff</option>
                <option value="admin station">Admin Station</option>
            </select>

            <label for="station_id">Police Station</label>
            <select id="station_id" name="station_id" required>
                <option value="">Select Police Station</option>
                <?php foreach ($stations as $station): ?>
                    <option value="<?php echo $station['id']; ?>">
                        <?php echo $station['police_station_name'] . ' (' . $station['district'] . ', ' . $station['tehsil'] . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Add Staff</button>
            <space></space>
            <a href="http://localhost/pms/dashboard.php" class="btn btn-home btn-block" style="background-color: #001f3f; color: white; padding: 10px 15px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;">
    &#8592; Home
</a>


        </form>
    </div>

</body>
</html>
