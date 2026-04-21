<?php
session_start();
require "../regis/koneksi.php";

// Redirect jika belum login
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: ../regis/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    echo "Buku tidak ditemukan.";
    exit;
}

$id_buku = $_GET['id'];

// Ambil data buku
$stmt = $conn->prepare("
    SELECT id_buku, judul, pengarang, cover_url, edisi, isbn, tahun_terbit, deskripsi, stok, kategori, status
    FROM buku
    WHERE id_buku = ?
");
$stmt->bind_param("s", $id_buku);
$stmt->execute();
$buku = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$buku) {
    echo "Buku tidak ditemukan.";
    exit;
}
// fallback cover
$cover = !empty($buku['cover_url']) ? $buku['cover_url'] : "aset/no-cover.png";

// 3. BARU BUAT LINK (Setelah $buku dipastikan ada isinya)
$linkPinjam = "pinjam.php?id=" . urlencode($buku['id_buku']);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($buku['judul']) ?></title>

    <link rel="stylesheet" href="css/detail.css">
</head>

<body>
    <nav class="navbar">
        <div class="nav-logo"><img src="aset/img/logo.png" alt="GiMi Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="books.php" class="active">Books</a></li>
            <li><a href="facilities.php">Facilities</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="profile.php">Profile</a></li>
        </ul>
    </nav>
    <div class="container">
        <img src="aset/img/gambar-buku.png" class="floating-book" alt="Open Book">
        <!-- LEFT -->
        <div class="left">
            <div class="title">Keep The Story Going</div>
            <div class="book-display-container">
                <img src="<?= $cover ?>" class="book-cover-img" alt="Cover Buku">
                <div class="book-details-card">
                    <div class="book-title"><?= htmlspecialchars($buku['judul']) ?></div>
                    <div class="book-author"><?= htmlspecialchars($buku['pengarang']) ?></div>
                    <div class="info-box">
                        <span>Edition</span>
                        <strong><?= htmlspecialchars($buku['edisi'] ?? '-') ?></strong>
                    </div>
                    <div class="info-box">
                        <span>ISBN</span>
                        <strong><?= htmlspecialchars($buku['isbn'] ?? '-') ?></strong>
                    </div>
                    <?php if ($buku['stok'] > 0): ?>
                        <a href="<?= $linkPinjam ?>" style="text-decoration: none;">
                            <button type="button" class="btn-borrow">Borrow Now</button>
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn-borrow" style="opacity: 0.5; cursor: not-allowed;" disabled>
                            Out of Stock
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="right">
            <div class="badge-container">
                <span class="badge badge-category"><?php echo $buku['kategori']; ?></span>
<span class="badge" style="background: <?php echo ($buku['status'] == 'tersedia') ? '#2ecc71' : '#e74c3c'; ?>">
    <?php echo $buku['status']; ?>
</span>
            </div>
            <div class="top-info">
                <div class="box">
                    <span>TAHUN TERBIT</span>

                    <strong><?php echo $buku['tahun_terbit']; ?></strong>

                </div>

                <div class="box">

                    <span>STOK</span>

                    <strong><?php echo $buku['stok']; ?> Eks</strong>

                </div>

            </div>



            <div class="sinopsis-card">

                <h3>Sinopsis</h3>

                <p class="sinopsis">

                    <?php echo nl2br($buku['deskripsi']); ?>

                </p>

            </div>

        </div>
    </div>
    </div>

</body>

</html>