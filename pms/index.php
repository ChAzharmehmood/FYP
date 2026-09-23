<?php
session_start();
include('config.php'); // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT * FROM staff WHERE name = ? AND role = 'staff'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && $password == $user['password']) { // Directly compare plain text password
        $_SESSION['staff_id'] = $user['id'];
        $_SESSION['username'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['police_station_name'] = $user['police_station_name'];
        header('Location: staff_dashboard.php'); // Redirect to Staff Dashboard
        exit();
    } else {
        $error_message = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
       body {
    font-family: 'Roboto', sans-serif;
    margin: 0;
    padding: 0;
    background-color:  rgb(219, 218, 218); 
    color: black;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column; 
    height: 100vh;
}

.container {
    max-width: 350px; /* Reduced width for a more professional look */
    padding: 25px;
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
}

h1.text-center {
    font-size: 26px;
    font-weight: bold;
    margin-bottom: 20px;
    color: rgb(33, 7, 146);
    text-align: center;
}

h3.text-center-hed{
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 20px;
    color: rgb(33, 7, 146);
    text-align: center;
}
.form-label {
    font-weight: 600; 
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s ease;
   
}

.form-control:focus {
    border-color: rgb(33, 7, 146);
    outline: none;
}

.btn-primary {
    width: 80%;
    padding: 10px;
    font-size: 14px;
    color: white;
    background-color: rgb(33, 7, 146);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s;
}

.btn-primary:hover {
    background-color: rgb(40, 185, 21);
    transform: scale(1.02);
}

.error-message {
    color: red;
    font-size: 14px;
    margin-top: 10px;
    text-align: center;
}


.role-links {
            margin-right: 20px;
            text-align: left;
        }

        .role-links ul {
            list-style-type: disc;
            padding-left: 20px;
        }

        .role-links ul li {
            margin-bottom: 8px;
        }

        .role-links ul li a {
            font-size: 14px;
            color: rgb(11, 89, 235);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .role-links ul li a:hover {
            color: rgb(9, 238, 238);
        }


.separator {
    border-top: 1px solid #ccc;
    margin: 20px 0;
}

.logo-container {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.logo {
    max-width: 80px;
    height: auto;
    margin-right: 10px;
}

.logo-text {
    font-size: 22px;
    font-weight: bold;
    color: rgb(6, 136, 104);
    text-transform: uppercase;
}

.d-flex {
    display: flex;
    justify-content: center;
}

.kahuta {
    margin-left: 40px;
}

.text-center {
    color: blue;
    text-align: center;
}
.mb-3{
    width:80%;
}
.mb-3.form-check {
    margin-top: 10px; /* Space from the top */
    margin-bottom: 10px; /* Space from the bottom */
    display: flex;
    align-items: center; /* Align checkbox and label properly */
    gap: 5px; /* Add small spacing between checkbox and label */
}  
</style>
</head>
<body>
<?php if (isset($error_message)) { ?>
    <script>
        alert("<?= htmlspecialchars($error_message); ?>");
    </script>
<?php } ?>

<div class="container">
    <!-- Logo and Text in parallel -->
    <div class="logo-container">
    <img src="img/logo.jpg" alt="Logo" class="logo">
        
    </div>

    
    <h1 class="text-center">Police Management System</h1>
    <h3 class="text-center-hed"> Login For Staff</h3>
    <form method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" name="username" class="form-control" id="username" value="<?php echo isset($_COOKIE['username']) ? $_COOKIE['username'] : ''; ?>" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" class="form-control" id="password" value="<?php echo isset($_COOKIE['password']) ? $_COOKIE['password'] : ''; ?>" required>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" name="remember" id="remember" <?php echo isset($_COOKIE['password']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="remember">Remember me</label>
        </div>
        
        <div class="d-flex">
            <button type="submit" class="btn btn-primary">Login</button>
        </div>

        <?php if (isset($error)) { echo "<div class='error-message'>$error</div>"; } ?>
    </form>
    <!-- Separator Line -->
    <div class="separator"></div>

    <!-- Role Links -->
    <div class="role-links">
        <ul>
            <li><a href="login_station_admin.php">Login For PS-Admin</a></li>
            <li><a href="admin_login.php">Login For Admin</a></li>
            <li><a href="index.php">Login For Staff</a></li>
        </ul>
    </div>
</body>
</html>
