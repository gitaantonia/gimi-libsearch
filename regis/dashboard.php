<?php
require "regis/koneksi.php";
require "regis/middleware.php";
?>

<h2>Halo, <?php echo $_SESSION["nama"]; ?></h2>
<p>Role ID: <?php echo $_SESSION["role"]; ?></p>

<a href="logout.php">Logout</a>

<?php
if ($_SESSION["role"] == 1) {
    echo "<br><a href='admin/admin.php'>Masuk ke Halaman Admin</a>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>indeks</title>
</head>
<body>
    ppppppppppppppppppppp
</body>
</html>