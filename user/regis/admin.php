<?php
require "koneksi.php";
require "middleware.php";

if ($_SESSION["role"] != 1) {
    echo "Akses ditolak.";
    exit;
}
?>

<h2>Halaman Admin</h2>
<p>Hanya admin yang bisa melihat ini.</p>
<a href="dashboard.php">Kembali</a>