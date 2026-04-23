<?php
$host = "sql312.infinityfree.com";
$user = "if0_41738084";
$pass = "03190504056";
$db   = "if0_41738084_gimi";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

?>