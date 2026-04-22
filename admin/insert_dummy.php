<?php
include 'koneksi.php';

$res = $conn->query("SELECT id_pengguna FROM pengguna WHERE role='anggota' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $id_pengguna = $row['id_pengguna'];
    $res2 = $conn->query("SELECT id_buku FROM buku LIMIT 2");
    $buku = [];
    while($row2 = $res2->fetch_assoc()) {
        $buku[] = $row2['id_buku'];
    }
    
    if (count($buku) > 0) {
        $conn->query("INSERT INTO request_peminjaman (id_pengguna, id_buku, status) VALUES ('$id_pengguna', '{$buku[0]}', 'Pending')");
        if(count($buku) > 1) {
             $conn->query("INSERT INTO request_peminjaman (id_pengguna, id_buku, status) VALUES ('$id_pengguna', NULL, 'Pending')");
        }
        echo "Dummy data inserted.\n";
    }
}
?>
