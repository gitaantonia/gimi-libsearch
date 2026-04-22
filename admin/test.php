<?php
include 'koneksi.php';
$res = mysqli_query($conn, "DESCRIBE fasilitas");
while($row = mysqli_fetch_assoc($res)) {
  print_r($row);
}
