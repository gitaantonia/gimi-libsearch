<?php
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: ../regis/login.php");
    exit;
}
?>