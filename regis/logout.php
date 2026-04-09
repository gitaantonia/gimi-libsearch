<?php
require "regis/koneksi.php";

session_destroy();
header("Location: login.php");
exit;
?>