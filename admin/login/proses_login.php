<?php
//session_start();
include '../koneksi.php';

$email = $_POST['email'];
$password = $_POST['password'];

$query = mysqli_query($conn, "SELECT * FROM pengguna WHERE email='$email'");
$data = mysqli_fetch_assoc($query);

if ($data) {

    // kalau password BELUM hash
    if ($password == $data['password_hash']) {

        $_SESSION['nama'] = $data['nama'];
        $_SESSION['role'] = $data['role'];

        header("Location: ../dashboard/dashboard_adm.php");
        exit;

    } else {
        echo "Password salah";
    }

} else {
    echo "Email tidak ditemukan";
}