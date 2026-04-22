<?php
include 'koneksi.php';

echo "=== TABLES ===\n";
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    $t = $row[0];
    echo "- $t\n";
}
?>
