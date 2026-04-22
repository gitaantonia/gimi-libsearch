<?php
session_start();
include 'koneksi.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'pustakawan' && $_SESSION['role'] != 'admin')) {
    die("Access denied");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_denda = $_POST['id_denda'] ?? '';

    if (!empty($id_denda)) {
        $stmt = $conn->prepare("UPDATE denda SET status = 'Sudah Dibayar' WHERE id_denda = ?");
        $stmt->bind_param("i", $id_denda);
        $stmt->execute();
        header("Location: handling_fines.php?msg=paid");
        exit;
    }
}
header("Location: handling_fines.php");
?>
