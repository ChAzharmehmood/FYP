<?php
session_start(); // Start the session

require 'vendor/autoload.php'; // Include PHPMailer
require __DIR__ . '/mail_config.php';
include('config.php'); // Include database connection

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if the user is logged in and is an 'admin station'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin station') {
    header('Location: login.php');
    exit();
}

$admin_station = $_SESSION['police_station_name'];

// Function to send email notification
function sendEmail($recipientEmail, $duty_description, $start_time, $end_time, $Duty_location) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_DUTIES_USER; // Your email
        $mail->Password = SMTP_DUTIES_PASS; // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('shakelahmed765@gmail.com', 'Admin');
        $mail->addAddress($recipientEmail);

        $mail->isHTML(true);
        $mail->Subject = "New Duty Assigned";
        $mail->Body = "
            <h3>Dear Staff,</h3>
            <p>You have been assigned a new duty:</p>
            <ul>
                <li><strong>Duty Description:</strong> $duty_description</li>
                <li><strong>Start Time:</strong> $start_time</li>
                <li><strong>End Time:</strong> $end_time</li>
                <li><strong>Duty Location:</strong> $Duty_location</li>
            </ul>
            <p>Best Regards,<br>Admin</p>
        ";

        $mail->send();
        return "Duty assigned successfully, and email sent.";
    } catch (Exception $e) {
        return "Duty assigned, but email failed to send. Mailer Error: " . $mail->ErrorInfo;
    }
}

// Handle duty assignment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_duty'])) {
    $staff_id = intval($_POST['staff_id']);
    $duty_description = htmlspecialchars($_POST['duty_description']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $shift_type = htmlspecialchars($_POST['shift_type']);
    $Duty_location = htmlspecialchars($_POST['Duty_location']);
    $checkpoint_id = !empty($_POST['checkpoint_id']) ? intval($_POST['checkpoint_id']) : NULL;
    $assigned_date = $_POST['assigned_date'];

    $query = "INSERT INTO duties (staff_id, duty_description, start_time, end_time, police_station_name, shift_type, Duty_location, checkpoint_id, assigned_date) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("issssssss", $staff_id, $duty_description, $start_time, $end_time, $admin_station, $shift_type, $Duty_location, $checkpoint_id, $assigned_date);
        if ($stmt->execute()) {
            // Fetch staff email
            $staff_query = "SELECT email FROM staff WHERE id = ?";
            $stmt = $conn->prepare($staff_query);
            $stmt->bind_param("i", $staff_id);
            $stmt->execute();
            $staff_result = $stmt->get_result();
            $staff = $staff_result->fetch_assoc();
            
            $success_message = sendEmail($staff['email'], $duty_description, $start_time, $end_time, $Duty_location);
        } else {
            $error_message = "Error executing query: " . $stmt->error;
        }
    } else {
        $error_message = "Database error: Failed to prepare statement. Error: " . $conn->error;
    }
}

// Fetch staff list
$staff_query = "SELECT id, name FROM staff WHERE police_station_name = ?";
if ($stmt = $conn->prepare($staff_query)) {
    $stmt->bind_param("s", $admin_station);
    $stmt->execute();
    $staff_result = $stmt->get_result();
} else {
    die("Database error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Duty | Police Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
        }
        .container {
            width: 50%;
            margin: auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            margin-bottom: 15px;
            padding: 10px;
            width: 100%;
        }
        .btn {
            padding: 10px;
            background: #4caf50;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>

    <div class="container">
    <a href="admin_dashboard.php" class="home-btn">Home</a>
        <h2>Assign Duty</h2>

        <?php if (isset($success_message)) echo "<p style='color:green;'>$success_message</p>"; ?>
        <?php if (isset($error_message)) echo "<p style='color:red;'>$error_message</p>"; ?>

        <form method="POST">
            <select name="staff_id" class="form-control" required>
                <option value="">--Select Staff--</option>
                <?php while ($staff = $staff_result->fetch_assoc()) { ?>
                    <option value="<?= $staff['id']; ?>"><?= $staff['name']; ?></option>
                <?php } ?>
            </select>
            <textarea name="duty_description" class="form-control" placeholder="Duty Description" required></textarea>
            <input type="datetime-local" name="start_time" class="form-control" required>
            <input type="datetime-local" name="end_time" class="form-control" required>
            <select name="shift_type" class="form-control" required>
                <option value="morning">Morning</option>
                <option value="evening">Evening</option>
            </select>
            <input type="text" name="Duty_location" class="form-control" placeholder="Location" required>
            <input type="number" name="checkpoint_id" class="form-control" placeholder="Checkpoint ID">
            <input type="date" name="assigned_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
            <button type="submit" name="assign_duty" class="btn">Assign Duty</button>
        </form>
    </div>
</body>
</html>
