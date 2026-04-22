<?php
session_start();
include 'koneksi.php';

// Validasi role
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'pustakawan' && $_SESSION['role'] != 'admin')) {
    header("Location: loginadm.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_peminjaman'])) {
    $id_peminjaman = $_POST['id_peminjaman'];
    $tanggal_pinjam = $_POST['tanggal_pinjam'];
    $tanggal_kembali = $_POST['tanggal_kembali'];
    $id_buku = $_POST['id_buku'];

    $conn->begin_transaction();

    try {
        // Update peminjaman status
        $query_update = "UPDATE peminjaman SET status = 'dipinjam', tanggal_pinjam = ?, tanggal_kembali = ? WHERE id_peminjaman = ?";
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bind_param("ssi", $tanggal_pinjam, $tanggal_kembali, $id_peminjaman);
        $stmt_update->execute();

        // Mengurangi stok buku karena dipinjam
        if (!empty($id_buku)) {
            $query_buku = "UPDATE buku SET stok = stok - 1 WHERE id_buku = ? AND stok > 0";
            $stmt_buku = $conn->prepare($query_buku);
            $stmt_buku->bind_param("s", $id_buku);
            $stmt_buku->execute();
        }

        $conn->commit();
        $_SESSION['msg'] = "Request successfully approved!";
        $_SESSION['msg_type'] = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = "Failed to approve request.";
        $_SESSION['msg_type'] = "danger";
    }
}

header("Location: book_requests.php");
exit;
?>
