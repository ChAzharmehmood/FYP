<?php
include('config.php');

if (isset($_GET['district'])) {
    $district = $_GET['district'];
    $query = "SELECT DISTINCT tehsil FROM reports WHERE district = '$district'";
    $result = mysqli_query($conn, $query);

    while ($row = mysqli_fetch_assoc($result)) {
        echo "<option value='" . htmlspecialchars($row['tehsil']) . "'>" . htmlspecialchars($row['tehsil']) . "</option>";
    }
}
?>
