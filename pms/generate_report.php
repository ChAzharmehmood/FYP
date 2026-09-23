<?php
// Include your database connection file
include('config.php'); 

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$success_message = "";
$error_message = "";

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    // Get form values
    $police_station_name = $_POST['police_station_name'];
    $under_section = $_POST['under_section'];
    $accused_name = $_POST['accused_name'];
    $accused_address = $_POST['accused_address'];
    $complainant = $_POST['complainant'];
   
    $investigation_officer = $_POST['investigation_officer'];
    $report_description = $_POST['report_description'];
    $district = $_POST['district'];  
    $tehsil = $_POST['tehsil'];      
    $id_card_no = $_POST['id_card_no']; 

    // Get current date in YYYY-MM-DD format
    $current_date = date("Y-m-d");

    // Check if database connection exists
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // Use Prepared Statements for security
    $query = "INSERT INTO reports (police_station_name, under_section, accused_name, accused_address, complainant, investigation_officer, report_description, district, tehsil, id_card_no, report_date) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssssssssss", 
            $police_station_name, $under_section, $accused_name, $accused_address, 
            $complainant, $investigation_officer, $report_description, 
            $district, $tehsil, $id_card_no, $current_date
        );

        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Report generated successfully!";
        } else {
            $error_message = "Error generating report: " . mysqli_stmt_error($stmt);
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Error preparing statement: " . mysqli_error($conn);
    }

    // Close database connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report - Police Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }
        h2 {
            text-align: center;
            color: #001f3f;
            padding-top: 30px;
        }
        .message {
            text-align: center;
            font-size: 18px;
            padding: 10px;
            margin: 10px auto;
            width: 80%;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        form {
            max-width: 800px;
            margin: 0 auto;
            background: #ecf0f1;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #001f3f;
        }
        form input, form textarea, form select, form button {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            font-size: 16px;
        }
        form textarea {
            resize: vertical;
            min-height: 100px;
        }
        form button {
            background-color: #001f3f;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 15px;
        }
        form button:hover {
            background-color: #34495e;
        }
        @media screen and (max-width: 768px) {
            form {
                padding: 20px;
                margin: 10px;
            }
            form input, form select, form button {
                font-size: 14px;
                padding: 10px;
            }
        }
    </style>
    <script>
        const tehsilOptions = {
            "Kotli District": ["Kotli Tehsil", "Khuiratta Tehsil", "Fatehpur Thakiala Tehsil", "Sehnsa Tehsil", "Charhoi Tehsil", "Duliah Jattan Tehsil"],
            "Bimber District": ["Bhimber Tehsil", "Barnala Tehsil", "Samahni Tehsil"],
            "Bagh District": ["Bagh Tehsil", "Dhirkot Tehsil", "Hari Ghel Tehsil", "Rera Tehsil", "Birpani Tehsil"],
            "Muzafarabad": ["Okra Tehsil", "Muzaffarabad Tehsil", "Nasirabad Tehsil"],
            "Haveli": ["Haveli_Kahuta", "Mumtazabad", "Khursidabad"]
        };

        function updateTehsilOptions() {
            const districtSelect = document.getElementById("district");
            const tehsilSelect = document.getElementById("tehsil");

            tehsilSelect.innerHTML = '<option value="">Select Tehsil</option>';
            const selectedDistrict = districtSelect.value;

            if (tehsilOptions[selectedDistrict]) {
                tehsilOptions[selectedDistrict].forEach(tehsil => {
                    const option = document.createElement("option");
                    option.value = tehsil;
                    option.textContent = tehsil;
                    tehsilSelect.appendChild(option);
                });
            }
        }
    </script>
</head>
<body>
    <h2>Generate Report</h2>

    <!-- Display success or error messages -->
    <?php if (!empty($success_message)): ?>
        <div class="message success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php
    // Include your database connection file
    include('config.php'); 

    // Query to fetch police station names
    $query = "SELECT DISTINCT police_station_name FROM police_stations";
    $result = mysqli_query($conn, $query);

    // Initialize an array to store the police station names
    $police_stations = [];

    if ($result) {
        // Fetch each row and add the police station name to the array
        while ($row = mysqli_fetch_assoc($result)) {
            $police_stations[] = $row['police_station_name'];
        }
    } else {
        // Handle query error
        echo "Error fetching police stations: " . mysqli_error($conn);
    }

    // Close the database connection
    mysqli_close($conn);
    ?>

    <form method="POST" action="">
        <label>Police Station Name:</label>
        <select name="police_station_name" required>
            <option value="">Select Police Station</option>
            <?php foreach ($police_stations as $station): ?>
                <option value="<?php echo htmlspecialchars($station); ?>"><?php echo htmlspecialchars($station); ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label for="crimeSection">Select Crime Section:</label>
        <select id="crimeSection" name="under_section">
            <option value="302">Section 302</option>
            <option value="307">Section 307</option>
            <option value="376">Section 376</option>
            <option value="354">Section 354</option>
            <option value="420">Section 420</option>
            <option value="498A">Section 498A</option>
            <option value="408">Section 408</option>
            <option value="279">Section 279</option>
            <option value="304A">Section 304A</option>
            <option value="366">Section 366</option>
            <option value="506">Section 506</option>
            <option value="379">Section 379</option>
            <option value="409">Section 409</option>
            <option value="other">Other</option>
        </select><br>

        <label>Accused Name:</label>
        <input type="text" name="accused_name" required><br>

        <label>Accused Address:</label>
        <textarea name="accused_address" required></textarea><br>

        <label>Crime Type:</label>
        <select name="complainant" required>
            <option value="">Select Crime Type</option>
            <option value="Murder">Murder</option>
            <option value="Robbery">Robbery</option>
            <option value="Kidnapping">Kidnapping</option>
            <option value="Assault">Assault</option>
            <option value="Theft">Theft</option>
            <option value="Burglary">Burglary</option>
            <option value="Fraud">Fraud</option>
            <option value="Cyber Crime">Cyber Crime</option>
            <option value="Drug Trafficking">Drug Trafficking</option>
            <option value="Human Trafficking">Human Trafficking</option>
            <option value="Extortion">Extortion</option>
            <option value="Domestic Violence">Domestic Violence</option>
            <option value="Rape">Rape</option>
            <option value="Homicide">Homicide</option>
            <option value="Terrorism">Terrorism</option>
            <option value="Corruption">Corruption</option>
            <option value="Bribery">Bribery</option>
            <option value="Arson">Arson</option>
            <option value="Blackmail">Blackmail</option>
            <option value="Harassment">Harassment</option>
        </select><br>

        <label>Investigation Officer:</label>
        <input type="text" name="investigation_officer" required><br>

        <label>Report Description:</label>
        <textarea name="report_description" required></textarea><br>

        <label>District:</label>
        <select name="district" id="district" required onchange="updateTehsilOptions()">
            <option value="">Select District</option>
            <option value="Kotli District">Kotli District</option>
            <option value="Bimber District">Bimber District</option>
            <option value="Bagh District">Bagh District</option>
            <option value="Muzafarabad">Muzafarabad District</option>
            <option value="Haveli">Haveli</option>
        </select><br>

        <label>Tehsil:</label>
        <select name="tehsil" id="tehsil" required>
            <option value="">Select Tehsil</option>
        </select><br>

        <label>ID Card Number:</label>
        <input type="text" name="id_card_no" placeholder="Enter ID Card Number" required><br>

        <button type="submit" name="generate_report">Generate Report</button>
        <a href="http://localhost/pms/staff_dashboard.php" class="btn btn-home btn-block" style="background-color: #001f3f; color: white; padding: 10px 15px; text-align: center; text-decoration: none; display: inline-block; border-radius: 5px;">
            &#8592; Home
        </a>
    </form>
</body>
</html>
