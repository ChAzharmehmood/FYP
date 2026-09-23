<?php
// Start the session
session_start();

// Destroy the session to log out the user
session_destroy();

// Redirect the user to the login page (or any other page you'd like)
header("Location: index.php");
exit();
?>
