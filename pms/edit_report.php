<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = $conn->query("SELECT * FROM reports WHERE id = $id");
    $report = $result->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Process the form data and update the report
    $police_station_name = $_POST['police_station_name'];
    $district = $_POST['district'];
    $tehsil = $_POST['tehsil'];
    $accused_name = $_POST['accused_name'];
    $complainant = $_POST['complainant'];
    $investigation_officer = $_POST['investigation_officer'];
    $status = $_POST['status'];  // Getting status from the form

    $query = "UPDATE reports SET police_station_name = '$police_station_name', district = '$district', tehsil = '$tehsil', 
              accused_name = '$accused_name', complainant = '$complainant', investigation_officer = '$investigation_officer', 
              status = '$status' WHERE id = $id";

    if ($conn->query($query) === TRUE) {
        // Redirect back to the reports management page
        header("Location: manage_reports.php");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        h2 {
            text-align: center;
            background-color: #003366;
            color: white;
            padding: 20px;
            margin: 0;
        }

        .container {
            width: 80%;
            margin: 20px auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            margin: 10px 0 5px;
            font-size: 14px;
            color: #333;
        }

        input[type="text"], select {
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        input[type="submit"] {
            background-color: #003366;
            color: white;
            font-size: 16px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        input[type="submit"]:hover {
            background-color: #00509e;
        }

        .status-select {
            width: 50%;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 30px;
        }

        .back-link a {
            text-decoration: none;
            color: #003366;
            font-size: 14px;
        }

        .back-link a:hover {
            color: #00509e;
        }
    </style>
</head>
<body>

    <h2>Edit Report</h2>
    <div class="container">
        <form method="POST" action="">
            <div class="form-group">
                <label for="police_station_name">Police Station Name:</label>
                <input type="text" name="police_station_name" value="<?php echo $report['police_station_name']; ?>" required>
            </div>

            <div class="form-group">
                <label for="district">District:</label>
                <input type="text" name="district" value="<?php echo $report['district']; ?>" required>
            </div>

            <div class="form-group">
                <label for="tehsil">Tehsil:</label>
                <input type="text" name="tehsil" value="<?php echo $report['tehsil']; ?>" required>
            </div>

            <div class="form-group">
                <label for="accused_name">Accused Name:</label>
                <input type="text" name="accused_name" value="<?php echo $report['accused_name']; ?>" required>
            </div>

            <div class="form-group">
                <label for="complainant">Complainant:</label>
                <input type="text" name="complainant" value="<?php echo $report['complainant']; ?>" required>
            </div>

            <div class="form-group">
                <label for="investigation_officer">Investigation Officer:</label>
                <input type="text" name="investigation_officer" value="<?php echo $report['investigation_officer']; ?>" required>
            </div>

            <!-- Status Dropdown (Admin can change the status here) -->
            <div class="form-group status-select">
                <label for="status">Status:</label>
                <select name="status" required>
                    <option value="Pending" <?php echo ($report['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="In Progress" <?php echo ($report['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Completed" <?php echo ($report['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>

            <div class="form-group">
                <input type="submit" value="Save Changes">
            </div>
        </form>

        <div class="back-link">
            <a href="manage_reports.php">Back to Reports List</a>
        </div>
    </div>

</body>
</html>
