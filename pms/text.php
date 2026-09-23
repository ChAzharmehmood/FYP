<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Police Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f4f7;
            display: flex;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background-color: #2c3e50;
            color: white;
            padding-top: 20px;
            position: fixed;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            padding: 15px;
            font-size: 18px;
            transition: background-color 0.3s ease, color 0.3s ease;
            border-left: 5px solid transparent;
        }

        .sidebar a i {
            margin-right: 10px;
            font-size: 20px;
        }

        .sidebar a:hover {
            background-color: #3498db;
            color: #f1c40f;
            border-left: 5px solid #f1c40f;
        }

        .sidebar a.active {
            background-color: #2980b9;
            color: #f1c40f;
            border-left: 5px solid #f1c40f;
        }

        .content {
            margin-left: 270px;
            padding: 20px;
            width: calc(100% - 270px);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        h2 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-container {
            width: 60%;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-top: 5px solid #3498db;
        }

        .form-container input,
        .form-container select,
        .form-container textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        .form-container input:focus,
        .form-container textarea:focus {
            border-color: #3498db;
            outline: none;
        }

        .form-container button {
            width: 100%;
            padding: 14px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .form-container button:hover {
            background-color: #2980b9;
            transform: scale(1.05);
        }

        .dashboard-options {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            gap: 20px;
        }

        .dashboard-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 200px;
            height: 220px;
            border-top: 5px solid #3498db;
        }

        .dashboard-card i {
            font-size: 50px;
            color: #3498db;
            margin-bottom: 10px;
            transition: color 0.3s ease;
        }

        .dashboard-card:hover i {
            color: #f1c40f;
        }

        .dashboard-card h3 {
            font-size: 22px;
            margin-top: 15px;
            color: #34495e;
        }

        .dashboard-card p {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .dashboard-card button {
            margin-top: 15px;
            padding: 10px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .dashboard-card button:hover {
            background-color: #2980b9;
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .form-container {
                width: 90%;
            }

            .dashboard-card {
                width: 45%;
            }
        }

        @media (max-width: 480px) {
            .sidebar {
                width: 200px;
            }

            .dashboard-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="?section=add_staff" class="<?= (isset($_GET['section']) && $_GET['section'] == 'add_staff') ? 'active' : '' ?>"><i class="fas fa-user-plus"></i> Add Staff</a>
        <a href="?section=add_criminal" class="<?= (isset($_GET['section']) && $_GET['section'] == 'add_criminal') ? 'active' : '' ?>"><i class="fas fa-user-secret"></i> Add Criminal</a>
        <a href="?section=add_police_station" class="<?= (isset($_GET['section']) && $_GET['section'] == 'add_police_station') ? 'active' : '' ?>"><i class="fas fa-building"></i> Add Police Station</a>
        <a href="?section=generate_reports" class="<?= (isset($_GET['section']) && $_GET['section'] == 'generate_reports') ? 'active' : '' ?>"><i class="fas fa-folder-open"></i> Generate Reports</a>
    </div>

    <!-- Content Area -->
    <div class="content">

        <?php if (isset($_GET['section']) && $_GET['section'] == 'add_staff') { ?>

            <h2>Add Staff</h2>

            <!-- Add Staff Form -->
            <div class="form-container">
                <form action="" method="POST">
                    <input type="text" name="name" placeholder="Staff Name" required>
                    <input type="text" name="designation" placeholder="Designation" required>
                    <input type="text" name="police_station" placeholder="Police Station" required>
                    <input type="text" name="qualification" placeholder="Qualification" required>
                    <input type="text" name="experience" placeholder="Experience" required>
                    <input type="text" name="contact_number" placeholder="Contact Number" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <textarea name="address" placeholder="Address" rows="4" required></textarea>
                    <input type="date" name="date_of_joining" required>

                    <button type="submit" name="submit_staff">Add Staff</button>
                </form>
            </div>

        <?php } else { ?>

            <h2>Admin Dashboard - Manage Police Records</h2>

            <div class="dashboard-options">
                <div class="dashboard-card">
                    <i class="fas fa-users"></i>
                    <h3>View Staff</h3>
                    <p>View and manage the police staff records.</p>
                    <button onclick="window.location.href='?section=view_staff'">View Now</button>
                </div>

                <div class="dashboard-card">
                    <i class="fas fa-gavel"></i>
                    <h3>View Criminals</h3>
                    <p>View and manage criminal records.</p>
                    <button onclick="window.location.href='?section=view_criminals'">View Now</button>
                </div>

                <div class="dashboard-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>View Police Stations</h3>
                    <p>View and manage police station records.</p>
                    <button onclick="window.location.href='?section=view_police_stations'">View Now</button>
                </div>

                <div class="dashboard-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>View Reports</h3>
                    <p>View generated reports of the police stations and records.</p>
                    <button onclick="window.location.href='?section=view_reports'">View Now</button>
                </div>
            </div>

         ?>

    </div>
</body>
</html>
