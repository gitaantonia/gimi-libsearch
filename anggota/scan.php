<?php
session_start();
require "../regis/koneksi.php";

// Redirect jika belum login
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: ../regis/login.php");
    exit;
}

$id_pengguna = $_SESSION["id_pengguna"];
$nama_user   = $_SESSION["nama"];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan - GiMi Library</title>
    <link rel="stylesheet" href="css/scan.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-logo"><img src="aset/img/logo.png" alt="GiMi Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="books.php">Books</a></li>
            <li><a href="facilities.php">Facilities</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="profile.php">Profile</a></li>
        </ul>
    </nav>

    <div class="container">
        <h1>Scan</h1>
        <p>Halaman scan sedang dalam pengembangan.</p>
    </div>
</body>
</html>