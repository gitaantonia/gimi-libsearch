<?php
session_start();
if(empty($_SESSION['username'])){
    header("location:../regis/login.php?pesan=blom_login");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>
        <?php 
        echo $_SESSION['username'];
        ?>
    </h1>
    <h2>
        <a href="logout.php">Keluar</a>
    </h2>
</body>
</html>