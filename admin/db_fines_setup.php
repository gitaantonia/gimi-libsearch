<?php
include 'koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS fines (
    id_fine INT AUTO_INCREMENT PRIMARY KEY,
    id_peminjaman INT NOT NULL,
    jenis_denda ENUM('Late', 'Lost', 'Damaged') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('Unpaid', 'Paid') DEFAULT 'Unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (id_peminjaman, jenis_denda)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table fines created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
