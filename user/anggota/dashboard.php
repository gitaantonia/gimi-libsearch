<?php
session_start();
require "../regis/koneksi.php";

// ============================================================
// GUARD — harus login dulu
// ============================================================
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: ../regis/login.php");
    exit;
}

$id_pengguna = $_SESSION["id_pengguna"];
$nama_user   = $_SESSION["nama"];

// ============================================================
// 1. RECENTLY BORROWED — 3 buku terakhir dipinjam user ini
// ============================================================
$recently_borrowed = [];
$stmt = $conn->prepare("
    SELECT b.judul, b.pengarang, b.cover_url, b.id_buku,
           p.tgl_pinjam, p.status
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    JOIN anggota a ON p.id_anggota = a.id_anggota
    WHERE a.id_pengguna = ?
    ORDER BY p.tgl_pinjam DESC
    LIMIT 3
");
$stmt->bind_param("s", $id_pengguna);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $recently_borrowed[] = $row;
}
$stmt->close();

// ============================================================
// 2. NEW & TRENDING — 5 buku terbanyak dipinjam
// ============================================================
$trending_books = [];
$res = $conn->query("
    SELECT b.id_buku, b.judul, b.pengarang, b.cover_url,
           COUNT(p.id_peminjaman) AS total_pinjam
    FROM buku b
    LEFT JOIN peminjaman p ON b.id_buku = p.id_buku
    GROUP BY b.id_buku
    ORDER BY total_pinjam DESC, b.id_buku DESC
    LIMIT 5
");
while ($row = $res->fetch_assoc()) {
    $trending_books[] = $row;
}

// ============================================================
// 3. AUTHOR OF THE WEEK — pengarang dengan buku terbanyak
// ============================================================
$author_week = null;
$res = $conn->query("
    SELECT pengarang, COUNT(*) AS total_buku, foto_pengarang
    FROM buku
    GROUP BY pengarang, foto_pengarang
    ORDER BY total_buku DESC
    LIMIT 1
");
if ($res && $res->num_rows > 0) {
    $author_week = $res->fetch_assoc();
}

// ============================================================
// 4. LAST READ — 1 buku terakhir yang dibaca user ini
// ============================================================
$last_read = null;
$stmt = $conn->prepare("
    SELECT b.judul, b.pengarang, b.cover_url
    FROM peminjaman p
    JOIN buku b ON p.id_buku = b.id_buku
    JOIN anggota a ON p.id_anggota = a.id_anggota
    WHERE a.id_pengguna = ?
      AND p.status = 'dikembalikan'
    ORDER BY p.tgl_kembali DESC
    LIMIT 1
");
$stmt->bind_param("s", $id_pengguna);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $last_read = $res->fetch_assoc();
}
$stmt->close();

// ============================================================
// 5. FACILITIES — semua fasilitas yang tersedia
// ============================================================
$facilities = [];
$res = $conn->query("
    SELECT id, nama_fasilitas, kategori, deskripsi, kapasitas, lokasi, status, gambar
    FROM fasilitas
    ORDER BY FIELD(status,'tersedia','dipesan','maintenance'), nama_fasilitas
    LIMIT 4
");
while ($row = $res->fetch_assoc()) {
    $facilities[] = $row;
}

// ============================================================
// 6. SEARCH BOOKS — jika ada query pencarian
// ============================================================
$search_results = [];
$search_query   = "";
if (isset($_GET["q"]) && trim($_GET["q"]) !== "") {
    $search_query = trim($_GET["q"]);
    $like = "%" . $search_query . "%";
    $stmt = $conn->prepare("
        SELECT id_buku, judul, pengarang, kategori, cover_url, status
        FROM buku
        WHERE judul LIKE ? OR pengarang LIKE ? OR kategori LIKE ?
        ORDER BY judul ASC
        LIMIT 12
    ");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $search_results[] = $row;
    }
    $stmt->close();
}

// ============================================================
// 7. PROSES BOOKING FASILITAS (POST)
// ============================================================
$booking_msg   = "";
$booking_error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["booking_fasilitas"])) {
    $id_fasilitas  = trim($_POST["id_fasilitas"]);
    $waktu_mulai   = trim($_POST["waktu_mulai"]);
    $waktu_selesai = trim($_POST["waktu_selesai"]);

    $stmt = $conn->prepare("SELECT id_anggota FROM anggota WHERE id_pengguna = ?");
    $stmt->bind_param("s", $id_pengguna);
    $stmt->execute();
    $res_a = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res_a) {
        $booking_error = "Data anggota tidak ditemukan.";
    } else {
        $id_anggota = $res_a["id_anggota"];

        $stmt = $conn->prepare("
            SELECT id_booking FROM booking
            WHERE id_fasilitas = ?
              AND status IN ('pending','dikonfirmasi')
              AND waktu_mulai < ? AND waktu_selesai > ?
        ");
        $stmt->bind_param("sss", $id_fasilitas, $waktu_selesai, $waktu_mulai);
        $stmt->execute();
        $bentrok = $stmt->get_result()->num_rows;
        $stmt->close();

        if ($bentrok > 0) {
            $booking_error = "Fasilitas sudah dipesan pada waktu tersebut.";
        } else {
            $kode = strtoupper(substr(md5(uniqid()), 0, 8));
            $stmt = $conn->prepare("
                INSERT INTO booking (id_anggota, id_fasilitas, waktu_mulai, waktu_selesai, kode_akses, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("sssss", $id_anggota, $id_fasilitas, $waktu_mulai, $waktu_selesai, $kode);
            if ($stmt->execute()) {
                $booking_msg = "Booking berhasil! Kode akses kamu: <strong>$kode</strong>";
            } else {
                $booking_error = "Gagal melakukan booking.";
            }
            $stmt->close();
        }
    }
}

// ============================================================
// 8. PROSES AJUKAN PEMINJAMAN (POST)
// ============================================================
$pinjam_msg   = "";
$pinjam_error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajukan_pinjam"])) {
    $id_buku = trim($_POST["id_buku"]);

    $stmt = $conn->prepare("SELECT id_anggota FROM anggota WHERE id_pengguna = ?");
    $stmt->bind_param("s", $id_pengguna);
    $stmt->execute();
    $res_a = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res_a) {
        $pinjam_error = "Data anggota tidak ditemukan.";
    } else {
        $id_anggota = $res_a["id_anggota"];

        $stmt = $conn->prepare("
            SELECT d.id_denda FROM denda d
            JOIN peminjaman p ON d.id_peminjaman = p.id_peminjaman
            WHERE p.id_anggota = ? AND d.status_bayar = 'belum_bayar'
            LIMIT 1
        ");
        $stmt->bind_param("s", $id_anggota);
        $stmt->execute();
        $ada_denda = $stmt->get_result()->num_rows;
        $stmt->close();

        if ($ada_denda > 0) {
            $pinjam_error = "Kamu masih memiliki denda yang belum dibayar.";
        } else {
            $stmt = $conn->prepare("SELECT status FROM buku WHERE id_buku = ?");
            $stmt->bind_param("s", $id_buku);
            $stmt->execute();
            $buku_row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$buku_row || $buku_row["status"] !== "tersedia") {
                $pinjam_error = "Buku tidak tersedia untuk dipinjam.";
            } else {
                $tgl_pinjam      = date("Y-m-d");
                $tgl_jatuh_tempo = date("Y-m-d", strtotime("+14 days"));

                $stmt = $conn->prepare("
                    INSERT INTO peminjaman (id_anggota, id_buku, tgl_pinjam, tgl_jatuh_tempo, status)
                    VALUES (?, ?, ?, ?, 'dipinjam')
                ");
                $stmt->bind_param("ssss", $id_anggota, $id_buku, $tgl_pinjam, $tgl_jatuh_tempo);

                if ($stmt->execute()) {
                    $upd = $conn->prepare("UPDATE buku SET status='dipinjam', tersedia=0 WHERE id_buku=?");
                    $upd->bind_param("s", $id_buku);
                    $upd->execute();
                    $upd->close();
                    $pinjam_msg = "Permintaan peminjaman berhasil! Silakan ambil buku di perpustakaan.";
                } else {
                    $pinjam_error = "Gagal mengajukan peminjaman.";
                }
                $stmt->close();
            }
        }
    }
}

// Helper: cover buku fallback
function cover($url, $judul = "")
{
    if (!empty($url)) return htmlspecialchars($url);
    return "aset/no-cover.png";
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GiMi Library — Home</title>
    <link rel="stylesheet" href="css/homepage.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
    <!-- ===================== NAVBAR ===================== -->
    <nav class="navbar">
        <div class="nav-logo"><img src="aset/img/logo.png" alt="GiMi Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active">Home</a></li>
            <li><a href="books.php">Books</a></li>
            <li><a href="facilities.php">Facilities</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li>
                <a href="../regis/logout.php" style="color:#c0392b;">Logout</a>
            </li>
        </ul>
    </nav>

    <div class="atas">
        <!-- ===================== HERO ===================== -->
        <section class="hero">
            <div class="hero-text">
                <h1>GiMi<br><span>✦ Library</span></h1>
                <p class="tagline">Opening Minds, One Book at a Time</p>
                <p class="hero-desc">
                    GiMi Library is a quiet refuge for curious minds and restless thinkers.
                    It is a place where ideas are gathered, stories are preserved, and knowledge
                    is treated as a lifelong journey rather than a final destination.
                </p>
            </div>
        </section>
    </div>

    <!-- ===================== QUICK MENU ===================== -->
    <section class="quick-menu">
        <a href="books.php" class="qm-item">
            <div class="qm-icon"><i data-lucide="book-text"></i></div>
            <span>Books</span>
        </a>
        <a href="facilities.php" class="qm-item">
            <div class="qm-icon"><i data-lucide="building-2"></i></div>
            <span>Facilities</span>
        </a>
        <a href="pinjamfas.php" class="qm-item">
            <div class="qm-icon"><i data-lucide="calendar-days"></i></div>
            <span>Booking</span>
        </a>
    </section>

    <!-- ===================== SEARCH BOOKS ===================== -->
    <section class="search-section">
        <div class="search-left">
            <h2>Turning pages, expanding <em>perspectives.</em></h2>
        </div>
        <div class="search-img">
            <img src="aset/img/search-books.png" alt="Books">
            <a href="books.php" class="floating-search-btn">Search Books</a>
        </div>
    </section>

    <!-- ===================== FACILITIES ===================== -->
    <section class="facilities-section">
        <div class="facilities-wrapper">

            <!-- LEFT CARD -->
            <div class="fac-card fac-left-text">
                <h2><strong>Spaces</strong> Designed<br><span class="h2-indent">for Thinking</span></h2>
                <div class="bawah">
                    <div class="fac-left-thumb">
                        <img src="aset/img/fas2.png" alt="">
                    </div>
                    <?php if (!empty($facilities)): ?>
                        <?php foreach ($facilities as $i => $fac): ?>
                            <?php if ($i >= 3) break; ?>
                            <p><?= htmlspecialchars($fac['nama_fasilitas']) ?></p>
                        <?php endforeach; ?>
                        <?php if (count($facilities) > 3): ?><p>dan lain-lain</p><?php endif; ?>
                    <?php else: ?>
                        <p>Belum ada fasilitas</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CENTER CARD -->
            <div class="fac-card fac-center">
                <div class="arch-frame">
                    <img src="aset/img/fas1.png" alt="Main Room">
                </div>
            </div>

            <!-- RIGHT CARD -->
            <div class="fac-card fac-right">
                <div class="small-img-wrap">
                    <img src="aset/img/fas3.png" alt="">
                    <div class="img-label">
                        <svg viewBox="0 0 70 120" width="350" height="120">
                            <defs>
                                <path id="arc-path" d="M 25,90 A 95,95 0 0,1 190,90" />
                            </defs>
                            <text font-size="15" font-weight="600" fill="#2a3a5e" font-family="sans-serif" letter-spacing="1.5">
                                <textPath href="#arc-path">Rooftop Café, Creative Corner •</textPath>
                            </text>
                        </svg>
                    </div>
                </div>
                <div class="small-img-wrap1">
                    <img src="aset/img/fas4.png" alt="">
                </div>
                <a href="facilities.php" class="btn-outline">See The Other Facilities</a>
            </div>
        </div>
    </section>

    <!-- Modal Booking -->
    <div class="modal-overlay" id="bookingModal" style="display:none">
        <div class="modal-box">
            <button class="modal-close" onclick="closeBookingModal()">✕</button>
            <h3>Booking <span id="modal-fac-name"></span></h3>
            <form method="POST">
                <input type="hidden" name="id_fasilitas" id="modal-fac-id">
                <div class="form-group">
                    <label>Waktu Mulai</label>
                    <input type="datetime-local" name="waktu_mulai" required
                        min="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="form-group">
                    <label>Waktu Selesai</label>
                    <input type="datetime-local" name="waktu_selesai" required
                        min="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <button type="submit" name="booking_fasilitas" class="btn-primary">Konfirmasi Booking</button>
            </form>
        </div>
    </div>

    <!-- ===================== NEW & TRENDING ===================== -->
    <div class="trending-recently-wrapper">
        <section class="trending-section">
            <div class="trending-left">
                <h2>New &amp;<br>Trending</h2>
                <p class="trending-sub">Explorer New World From Authors</p>

                <?php if (!empty($trending_books)): ?>
                    <div class="trending-main-book">
                        <img src="<?= cover($trending_books[0]['cover_url'], $trending_books[0]['judul']) ?>"
                            alt="<?= htmlspecialchars($trending_books[0]['judul']) ?>">
                    </div>
                <?php endif; ?>
            </div>

            <div class="trending-right">
                <!-- Author of the Week -->
                <?php if ($author_week): ?>
                    <div class="author-week-card">
                        <p class="card-label">Author of the Week</p>
                        <?php if (!empty($author_week['foto_pengarang'])): ?>
                            <img src="<?= htmlspecialchars($author_week['foto_pengarang']) ?>"
                                alt="<?= htmlspecialchars($author_week['pengarang']) ?>" class="author-photo">
                        <?php endif; ?>
                        <h4><?= htmlspecialchars($author_week['pengarang']) ?></h4>
                        <p><?= $author_week['total_buku'] ?> Books</p>
                    </div>
                <?php endif; ?>

                <!-- Last Read -->
                <?php if ($last_read): ?>
                    <div class="last-read-card">
                        <p class="card-label">Last Read</p>
                        <img src="<?= cover($last_read['cover_url'], $last_read['judul']) ?>"
                            alt="<?= htmlspecialchars($last_read['judul']) ?>">
                        <h4><?= htmlspecialchars($last_read['judul']) ?></h4>
                        <p><?= htmlspecialchars($last_read['pengarang']) ?></p>
                    </div>
                <?php else: ?>
                    <div class="last-read-card empty">
                        <p class="card-label">Last Read</p>
                        <p class="empty-state">Belum ada buku yang dibaca.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="trending-rak"></div>

        <!-- ===================== RECENTLY BORROWED ===================== -->
        <section class="recently-section">
            <h3 class="section-label-vertical">Recently Borrowed</h3>
            <div class="recently-grid">
                <?php if (empty($recently_borrowed)): ?>
                    <p class="empty-state">Belum ada riwayat peminjaman.</p>
                <?php else: ?>
                    <?php foreach ($recently_borrowed as $rb): ?>
                        <div class="recent-card">
                            <img src="<?= cover($rb['cover_url'], $rb['judul']) ?>"
                                alt="<?= htmlspecialchars($rb['judul']) ?>">
                            <div class="recent-info">
                                <h4><?= htmlspecialchars($rb['judul']) ?></h4>
                                <p><?= htmlspecialchars($rb['pengarang']) ?></p>
                                <span class="badge badge-<?= $rb['status'] === 'dipinjam' ? 'blue' : 'gray' ?>">
                                    <?= ucfirst($rb['status']) ?>
                                </span>
                                <a href="bookDetail.php?id=<?= $rb['id_buku'] ?>" class="btn-detail">Detail</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- ===================== SCRIPTS ===================== -->
    <script>
        function openBookingModal(id, nama) {
            document.getElementById('modal-fac-id').value = id;
            document.getElementById('modal-fac-name').textContent = nama;
            document.getElementById('bookingModal').style.display = 'flex';
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        document.getElementById('bookingModal').addEventListener('click', function(e) {
            if (e.target === this) closeBookingModal();
        });

        document.querySelectorAll('input[name="waktu_selesai"]').forEach(function(el) {
            el.addEventListener('change', function() {
                const mulai = document.querySelector('input[name="waktu_mulai"]').value;
                if (mulai && this.value <= mulai) {
                    alert('Waktu selesai harus lebih dari waktu mulai.');
                    this.value = '';
                }
            });
        });
    </script>
    <script>lucide.createIcons();</script>
</body>

</html>