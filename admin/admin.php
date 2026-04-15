<?php
require "koneksi.php";

session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] != "pustakawan") {
    echo "Akses ditolak.";
    exit;
}
?>

<h2>Halaman Admin</h2>
<p>Hanya admin yang bisa melihat ini.</p>
<a href="dashboard_adm.php">Kembali</a>