<?php
header('Content-Type: application/json');

// Database connection
include 'config.php';

// Get the JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id'])) {
    $staffId = $data['id'];

    // Delete staff member
    $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
    $stmt->bind_param("i", $staffId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false]);
}
?>
