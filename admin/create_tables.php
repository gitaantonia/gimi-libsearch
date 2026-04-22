<?php
include 'koneksi.php';

$sql1 = "CREATE TABLE IF NOT EXISTS request_peminjaman (
    id_request INT AUTO_INCREMENT PRIMARY KEY,
    id_pengguna VARCHAR(36) NOT NULL,
    id_buku VARCHAR(36) NULL,
    status ENUM('Pending', 'Disetujui', 'Ditolak') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql2 = "CREATE TABLE IF NOT EXISTS peminjaman (
    id_peminjaman INT AUTO_INCREMENT PRIMARY KEY,
    id_pengguna VARCHAR(36) NOT NULL,
    id_buku VARCHAR(36) NOT NULL,
    tanggal_pinjam DATE NOT NULL,
    tanggal_kembali DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'dipinjam',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql1) === TRUE) {
    echo "Table request_peminjaman created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

if ($conn->query($sql2) === TRUE) {
    echo "Table peminjaman created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Tambah dummy data untuk tes
$dummy = "INSERT INTO request_peminjaman (id_pengguna, id_buku, status) VALUES 
('1', '1', 'Pending'),
('2', NULL, 'Pending')
ON DUPLICATE KEY UPDATE id_request=id_request";
$conn->query($dummy);

$conn->close();
?>
