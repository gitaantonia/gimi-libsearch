<?php
include 'koneksi.php';
$output = "";
$result = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_array($result)) {
    $output .= "TABLE: " . $row[0] . "\n";
    $res2 = mysqli_query($conn, "DESCRIBE " . $row[0]);
    while ($r = mysqli_fetch_assoc($res2)) {
        $output .= print_r($r, true);
    }
}
file_put_contents('schema.txt', $output);
?>
