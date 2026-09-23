<?php 
include('config.php'); // Include database connection
session_start();

// Check if staff_id is set in the session
if (!isset($_SESSION['staff_id'])) {
    // Redirect to login page if not logged in
    header('Location: login.php');
    exit(); // Stop further script execution
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $staff_id = $_SESSION['staff_id']; // Staff ID from session
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $leave_start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $leave_end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    $query = "INSERT INTO leave_requests (staff_id, leave_type, leave_start_date, leave_end_date, reason, status) 
              VALUES ('$staff_id', '$leave_type', '$leave_start_date', '$leave_end_date', '$reason', 'pending')";

    if (mysqli_query($conn, $query)) {
        $message = "Leave request submitted successfully.";
    } else {
        $message = "Error submitting leave request: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 50%;
            margin: 50px auto;
            padding: 20px;
            background-color: #ffffff;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        h2 {
            text-align: center;
            color: #333333;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            margin: 10px 0 5px;
            color: #333333;
        }
        input, select, textarea, button {
            font-size: 16px;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }
        textarea {
            resize: none;
        }
        button {
            background-color: #003366; /* Navy Blue color for Submit button */
            color: #ffffff;
            cursor: pointer;
            border: none;
        }
        button:hover {
            background-color: #002244; /* Darker shade of navy blue */
        }
        .message {
            text-align: center;
            margin: 10px 0;
            font-weight: bold;
            color: green;
        }
        .error {
            color: red;
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
            background-color: #0056b3; /* Darker shade of blue for hover effect */
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Apply for Leave</h2>
        <?php if (isset($message)) echo "<p class='message'>$message</p>"; ?>
        <a href="staff_dashboard.php" class="home-button">Home</a> <!-- Home Button -->
        <form method="POST">
            <label for="leave_type">Leave Type:</label>
            <select id="leave_type" name="leave_type" required>
                <option value="Casual">Casual</option>
                <option value="Sick">Sick</option>
                <option value="Annual">Annual</option>
            </select>
            
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" required>
            
            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" required>
            
            <label for="reason">Reason:</label>
            <textarea id="reason" name="reason" rows="4" required></textarea>
            
            <button type="submit">Submit</button>
        </form>
    </div>
</body>
</html>
