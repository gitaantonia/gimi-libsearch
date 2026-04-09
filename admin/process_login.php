<?php
session_start();

// contoh login sederhana (hardcode)
$email = $_POST['email'];
$password = $_POST['password'];

if($email == "admin@gmail.com" && $password == "12345"){
    $_SESSION['admin'] = $email;
    header("Location: dashboard.php");
} else {
    echo "<script>alert('Login gagal!'); window.location='login.php';</script>";
}
?>