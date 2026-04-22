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

    $query = "UPDATE peminjaman SET status = 'ditolak' WHERE id_peminjaman = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_peminjaman);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Request has been rejected.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "Failed to reject request.";
        $_SESSION['msg_type'] = "danger";
    }
}

header("Location: book_requests.php");
exit;
?>
