<?php
include 'config.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "DELETE FROM reports WHERE id = $id";
    if ($conn->query($query) === TRUE) {
        // Redirect back to the reports management page
        header("Location: manage_reports.php");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
