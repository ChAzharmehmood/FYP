<?php
include('config.php'); // Include your database connection file

// Check if the search form is submitted
if (isset($_POST['search'])) {
    $id_card_no = $_POST['id_card_no'];

    // Query to search for a criminal by id_card_no
    $query = "SELECT id, police_station_name, under_section, accused_name, accused_address, id_card_no, report_description, status
              FROM reports
              WHERE id_card_no = '$id_card_no'";

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        // Fetch the report details
        $report = mysqli_fetch_assoc($result);
    } else {
        $error_message = "No report found for the provided ID card number.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Criminal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }
        
        .container {
            width: 50%;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h2 {
            text-align: center;
            color: #001f3f;
            margin: 0;
        }

        .home-button {
            padding: 10px 20px;
            background-color: #001f3f;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
        }

        .home-button:hover {
            background-color: #34495e;
        }

        form {
            margin-top: 20px;
        }

        form input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            font-size: 16px;
        }

        form button {
            width: 100%;
            padding: 12px;
            background-color: #001f3f;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        form button:hover {
            background-color: #34495e;
        }

        .result {
            margin-top: 20px;
        }

        .result table {
            width: 100%;
            border-collapse: collapse;
        }

        .result table, .result th, .result td {
            border: 1px solid #bdc3c7;
        }

        .result th, .result td {
            padding: 10px;
            text-align: left;
        }

        .result th {
            background-color: #ecf0f1;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>Search Criminal</h2>
        <a href="dashboard.php" class="home-button">Home</a>
    </div>
    
    <form method="POST" action="">
        <label for="id_card_no">Enter ID Card Number:</label>
        <input type="text" name="id_card_no" required placeholder="ID Card Number">
        <button type="submit" name="search">Search</button>
    </form>

    <?php if (isset($report)): ?>
        <div class="result">
            <h3>Report Details</h3>
            <table>
                <tr>
                    <th>Police Station Name</th>
                    <td><?php echo htmlspecialchars($report['police_station_name']); ?></td>
                </tr>
                <tr>
                    <th>Under Section</th>
                    <td><?php echo htmlspecialchars($report['under_section']); ?></td>
                </tr>
                <tr>
                    <th>Accused Name</th>
                    <td><?php echo htmlspecialchars($report['accused_name']); ?></td>
                </tr>
                <tr>
                    <th>Accused Address</th>
                    <td><?php echo htmlspecialchars($report['accused_address']); ?></td>
                </tr>
                <tr>
                    <th>ID</th>
                    <td><?php echo htmlspecialchars($report['id_card_no']); ?></td>
                </tr>
                <tr>
                    <th>Report Description</th>
                    <td><?php echo htmlspecialchars($report['report_description']); ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><?php echo htmlspecialchars($report['status']); ?></td>
                </tr>
            </table>
        </div>
    <?php elseif (isset($error_message)): ?>
        <div class="error">
            <p><?php echo $error_message; ?></p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
