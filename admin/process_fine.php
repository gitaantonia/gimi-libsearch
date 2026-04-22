<?php
session_start();
include 'koneksi.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'pustakawan' && $_SESSION['role'] != 'admin')) {
    die("Access denied");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_peminjaman = $_POST['id_peminjaman'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!empty($id_peminjaman) && ($action === 'lost' || $action === 'damaged')) {
        $jenis_denda = ($action === 'lost') ? 'Lost' : 'Damaged';
        $amount = ($action === 'lost') ? 100000 : 50000;
        
        // Prevent duplicate fines
        $chk = $conn->prepare("SELECT id_fine FROM fines WHERE id_peminjaman = ? AND jenis_denda = ?");
        $chk->bind_param("is", $id_peminjaman, $jenis_denda);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            header("Location: handling_fines.php?msg=duplicate");
            exit;
        }

        // Insert new fine
        $stmt = $conn->prepare("INSERT INTO fines (id_peminjaman, jenis_denda, amount, status) VALUES (?, ?, ?, 'Unpaid')");
        $stmt->bind_param("isd", $id_peminjaman, $jenis_denda, $amount);
        $stmt->execute();

        // Update loan status
        $loan_status = ($action === 'lost') ? 'hilang' : 'rusak';
        $upd = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id_peminjaman = ?");
        $upd->bind_param("si", $loan_status, $id_peminjaman);
        $upd->execute();
        
        // Reduce stock if lost
        if ($action === 'lost') {
            $q = $conn->prepare("SELECT id_buku FROM peminjaman WHERE id_peminjaman = ?");
            $q->bind_param("i", $id_peminjaman);
            $q->execute();
            $res = $q->get_result();
            if ($row = $res->fetch_assoc()) {
                $id_buku = $row['id_buku'];
                $upd_stok = $conn->prepare("UPDATE buku SET stok = stok - 1 WHERE id_buku = ? AND stok > 0");
                $upd_stok->bind_param("s", $id_buku);
                $upd_stok->execute();
            }
        }
        header("Location: handling_fines.php?msg=success");
        exit;
    }
}
header("Location: handling_fines.php");
?>
