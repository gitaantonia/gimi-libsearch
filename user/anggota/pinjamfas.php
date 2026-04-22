<?php
session_start();
include '../regis/koneksi.php';
require "helpers.php";

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../regis/login.php");
    exit;
}

$db = isset($conn) ? $conn : $koneksi;
$id_pengguna = $_SESSION['id_pengguna'];

$stmt = $db->prepare("SELECT id_anggota FROM anggota WHERE id_pengguna = ?");
$stmt->bind_param("i", $id_pengguna);
$stmt->execute();
$res_user = $stmt->get_result()->fetch_assoc();

if (!$res_user) {
    die("Data anggota tidak ditemukan.");
}
$id_anggota = $res_user['id_anggota'];

// Pastikan f.gambar ada di query untuk menampilkan foto
$query = "SELECT b.*, f.nama_fasilitas, f.lokasi, f.gambar 
          FROM bookings b 
          JOIN fasilitas f ON b.id_fasilitas = f.id
          WHERE b.id_anggota = ? 
          ORDER BY b.tanggal DESC, b.jam_mulai DESC";
$stmt_book = $db->prepare($query);
$stmt_book->bind_param("s", $id_anggota);
$stmt_book->execute();
$bookings = $stmt_book->get_result();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Booking — GiMi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/pinjamfas.css">
</head>

<body>
    <nav class="navbar">
        <div class="nav-logo"><img src="aset/img/logo.png" alt="GiMi Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="books.php">Books</a></li>
            <li><a href="facilities.php" class="active">Facilities</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li>
                <a href="../regis/logout.php" style="color:#c0392b;">Logout</a>
            </li>
        </ul>
    </nav>

    <div class="main-content">
        <h1 class="page-title">Riwayat <em>Booking</em></h1>
        <p class="page-subtitle">Daftar peminjaman fasilitas Anda.</p>

        <div class="booking-grid">
            <?php if ($bookings->num_rows > 0): ?>
                <?php while ($row = $bookings->fetch_assoc()):
                    $start_timestamp = strtotime($row['tanggal'] . ' ' . $row['jam_mulai']);
                    $end_timestamp = strtotime($row['tanggal'] . ' ' . $row['jam_selesai']);
                    // Fallback gambar jika kosong
                    $gambar_path = !empty($row['gambar']) ? "../admin/upload/" . $row['gambar'] : "../aset/img/default_fasilitas.png";
                ?>
                    <div class="booking-card">
                        <img src="<?= $gambar_path ?>" alt="Fasilitas" class="card-img-top">

                        <div class="card-body">
                            <div class="info-left">
                                <h3><?= htmlspecialchars($row['nama_fasilitas']) ?></h3>
                                <p>📍 <?= htmlspecialchars($row['lokasi']) ?></p>
                                <p>📅 <?= date('d M Y', strtotime($row['tanggal'])) ?></p>
                                <p>🕒 <?= substr($row['jam_mulai'], 0, 5) ?> - <?= substr($row['jam_selesai'], 0, 5) ?></p>
                            </div>

                            <div class="status-container">
                                <span class="status-badge <?= strtolower($row['status_booking']) ?>">
                                    <?= $row['status_booking'] ?>
                                </span>
                            </div>

                            <?php if (strtolower($row['status_booking']) === 'confirmed'): ?>
                                <div class="timer-box countdown"
                                    data-start="<?= $start_timestamp ?>"
                                    data-end="<?= $end_timestamp ?>">
                                    Menghitung...
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: black; grid-column: 1/-1; text-align: center;">Belum ada riwayat booking.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateCountdowns() {
            const now = Math.floor(Date.now() / 1000);
            document.querySelectorAll('.countdown').forEach(el => {
                const start = parseInt(el.dataset.start);
                const end = parseInt(el.dataset.end);

                if (now < start) {
                    const diff = start - now;
                    const h = Math.floor(diff / 3600);
                    const m = Math.floor((diff % 3600) / 60);
                    el.innerHTML = `Mulai dalam: ${h}j ${m}m`;
                } else if (now >= start && now <= end) {
                    const diff = end - now;
                    const m = Math.floor(diff / 60);
                    const s = diff % 60;
                    el.innerHTML = `⏳ Sisa: ${m}m ${s}s`;
                    el.style.background = "#16a34a";
                } else {
                    el.innerHTML = "Selesai";
                    el.style.background = "#6b7280";
                }
            });
        }
        setInterval(updateCountdowns, 1000);
        updateCountdowns();
    </script>

</body>

</html>