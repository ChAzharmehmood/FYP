<?php
// Database connection
$servername = "localhost";
$username = "root";  // Default username for MySQL
$password = "";      // Default password for MySQL (empty for XAMPP/WAMP)
$dbname = "db_pms"; // Name of the database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
    if ($conn->query($sql) === TRUE) {
        $success_message = "New user added successfully!";
        header("Location: dashboard.php"); // Redirect to the same page or any other page after success
        exit();
    }
    
}

?>