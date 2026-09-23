<?php
// Start the session
session_start();

// Debugging: Check if session is initialized properly
if (!isset($_SESSION['police_station_name'])) {
    echo "Error: 'police_station_name' not set in session.";
    exit;
} elseif (empty($_SESSION['police_station_name'])) {
    echo "Error: 'police_station' is empty in session.";
    exit;
}

// Get the police station of the logged-in admin from the session
$adminPoliceStation = $_SESSION['police_station_name'];

// Database connection
include 'config.php';

// Handle staff update
if (isset($_POST['update_staff'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $designation = trim($_POST['designation']);
    $role = trim($_POST['role']);

    $stmt = $conn->prepare("UPDATE staff SET name = ?, designation = ?, role = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $designation, $role, $id);

    if ($stmt->execute()) {
        $updateMessage = "Staff member updated successfully.";
    } else {
        $updateMessage = "Error updating staff member.";
    }

    $stmt->close();
}

// Fetch staff members for the same police station as the admin
$stmt = $conn->prepare("SELECT * FROM staff WHERE police_station_name = ? AND role = 'staff'");
$stmt->bind_param("s", $adminPoliceStation);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
        }
        h2 {
            text-align: center;
            background-color: #001f3f;
            color: white;
            padding: 10px;
        }
        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: white;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #003366;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        button {
            padding: 5px 10px;
            background-color: #003366;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #00509e;
        }
        .inline-input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .home-btn {
            display: block;
            margin: 10px auto;
            padding: 10px 20px;
            background-color: #005B96;
            color: white;
            text-align: center;
            text-decoration: none;
            font-size: 16px;
            border-radius: 5px;
            width: 120px;
        }
        .home-btn:hover {
            background-color: darkgreen;
        }
    </style>
</head>
<body>
    <a href="admin_dashboard.php" class="home-btn">Home</a>
    <h2>Manage Staff</h2>
    <?php if (isset($updateMessage)) echo "<p style='text-align:center;color:green;'>$updateMessage</p>"; ?>
    <table id="staffTable">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Designation</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr id='row-{$row['id']}'>
                    <form method='POST' action='manage_staff.php'>
                        <td>{$row['id']}</td>
                        <td><input type='text' name='name' value='{$row['name']}' class='inline-input'></td>
                        <td><input type='text' name='designation' value='{$row['designation']}' class='inline-input'></td>
                        <td>
                            <select name='role' class='inline-input'>
                                <option value='staff' " . ($row['role'] == 'staff' ? 'selected' : '') . ">Staff</option>
                                <option value='admin' " . ($row['role'] == 'admin' ? 'selected' : '') . ">Admin</option>
                            </select>
                        </td>
                        <td>
                            <button type='submit' name='update_staff'>Update</button>
                            <button type='button' class='delete-btn' data-id='{$row['id']}'>Delete</button>
                        </td>
                        <input type='hidden' name='id' value='{$row['id']}'>
                    </form>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='5' style='text-align:center;'>No staff found for your police station.</td></tr>";
        }

        $stmt->close();
        ?>
    </table>
    
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const deleteButtons = document.querySelectorAll(".delete-btn");

            deleteButtons.forEach(button => {
                button.addEventListener("click", () => {
                    const staffId = button.getAttribute("data-id");

                    if (confirm("Are you sure you want to delete this staff member?")) {
                        // AJAX request to delete staff
                        fetch("delete_staff.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json"
                            },
                            body: JSON.stringify({ id: staffId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove row from table
                                const row = document.getElementById(`row-${staffId}`);
                                if (row) row.remove();
                                alert("Staff member deleted successfully.");
                            } else {
                                alert("Error deleting staff member.");
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
