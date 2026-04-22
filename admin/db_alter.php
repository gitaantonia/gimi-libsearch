<?php
include 'koneksi.php';

$sql = "ALTER TABLE peminjaman ADD COLUMN tanggal_dikembalikan DATE NULL";
if ($conn->query($sql) === TRUE) {
    echo "Column tanggal_dikembalikan added successfully.\n";
} else {
    echo "Error altering table: " . $conn->error . "\n";
}

$conn->close();
?>
