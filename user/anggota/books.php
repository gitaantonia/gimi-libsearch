<?php
session_start();
require "../../regis/koneksi.php";

// Redirect jika belum login
if (!isset($_SESSION["id_pengguna"])) {
    header("Location: ../../regis/login.php");
    exit;
}

$id_pengguna  = $_SESSION["id_pengguna"];
$nama_pengguna = $_SESSION["nama"] ?? "Pengguna";

// Ambil id_anggota dari session
$stmt = $conn->prepare("SELECT id_anggota FROM anggota WHERE id_pengguna = ?");
$stmt->bind_param("s", $id_pengguna);
$stmt->execute();
$anggota_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$id_anggota = $anggota_row["id_anggota"] ?? null;

// Tab aktif
$tab = isset($_GET["tab"]) && $_GET["tab"] === "history" ? "history" : "catalog";

// ============================================================
// PROSES AJUKAN PEMINJAMAN
// ============================================================
$msg   = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajukan_pinjam"])) {
    $id_buku = trim($_POST["id_buku"]);

    if (!$id_anggota) {
        $error = "Data anggota tidak ditemukan.";
    } else {
        // Cek denda aktif
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
            $error = "Kamu masih memiliki denda yang belum dibayar. Selesaikan denda terlebih dahulu.";
        } else {
            // Cek batas peminjaman aktif (maks 3)
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS total FROM peminjaman
                WHERE id_anggota = ? AND status = 'dipinjam'
            ");
            $stmt->bind_param("s", $id_anggota);
            $stmt->execute();
            $jml_pinjam = $stmt->get_result()->fetch_assoc()["total"];
            $stmt->close();

            if ($jml_pinjam >= 3) {
                $error = "Batas peminjaman aktif sudah tercapai (maks 3 buku).";
            } else {
                // Cek status buku
                $stmt = $conn->prepare("SELECT status, judul FROM buku WHERE id_buku = ?");
                $stmt->bind_param("s", $id_buku);
                $stmt->execute();
                $buku_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$buku_row || $buku_row["status"] !== "tersedia") {
                    $error = "Buku tidak tersedia untuk dipinjam saat ini.";
                } else {
                    $tgl_pinjam      = date("Y-m-d");
                    $tgl_jatuh_tempo = date("Y-m-d", strtotime("+14 days"));

                    $stmt = $conn->prepare("
                        INSERT INTO peminjaman (id_anggota, id_buku, tgl_pinjam, tgl_jatuh_tempo, status)
                        VALUES (?, ?, ?, ?, 'dipinjam')
                    ");
                    $stmt->bind_param("ssss", $id_anggota, $id_buku, $tgl_pinjam, $tgl_jatuh_tempo);

                    if ($stmt->execute()) {
                        // Update status buku
                        $upd = $conn->prepare("UPDATE buku SET status = 'dipinjam' WHERE id_buku = ?");
                        $upd->bind_param("s", $id_buku);
                        $upd->execute();
                        $upd->close();

                        $msg = "Peminjaman <strong>" . htmlspecialchars($buku_row["judul"]) . "</strong> berhasil diajukan! Silakan ambil di perpustakaan.";
                        $tab = "history"; // Pindah ke tab history setelah pinjam
                    } else {
                        $error = "Gagal mengajukan peminjaman.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// ============================================================
// PROSES PENGEMBALIAN BUKU
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["kembalikan"])) {
    $id_peminjaman = trim($_POST["id_peminjaman"]);

    // Ambil data peminjaman
    $stmt = $conn->prepare("
        SELECT p.id_buku, p.tgl_jatuh_tempo, b.judul
        FROM peminjaman p
        JOIN buku b ON p.id_buku = b.id_buku
        WHERE p.id_peminjaman = ? AND p.id_anggota = ? AND p.status = 'dipinjam'
    ");
    $stmt->bind_param("ss", $id_peminjaman, $id_anggota);
    $stmt->execute();
    $pinjam_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pinjam_data) {
        $error = "Data peminjaman tidak valid.";
    } else {
        $tgl_kembali   = date("Y-m-d");
        $tgl_tempo     = $pinjam_data["tgl_jatuh_tempo"];
        $denda_amount  = 0;
        $jenis_denda   = null;

        // Hitung denda keterlambatan (Rp 1.000/hari)
        if ($tgl_kembali > $tgl_tempo) {
            $selisih      = (strtotime($tgl_kembali) - strtotime($tgl_tempo)) / 86400;
            $denda_amount = (int)$selisih * 1000;
            $jenis_denda  = "keterlambatan";
        }

        // Cek kondisi buku (dari POST)
        $kondisi = trim($_POST["kondisi"] ?? "baik");
        if (in_array($kondisi, ["rusak", "hilang"])) {
            $denda_kerusakan = $kondisi === "hilang" ? 150000 : 50000;
            $denda_amount   += $denda_kerusakan;
            $jenis_denda     = $kondisi;

            // Update status buku sesuai kondisi
            $upd = $conn->prepare("UPDATE buku SET status = ? WHERE id_buku = ?");
            $upd->bind_param("ss", $kondisi, $pinjam_data["id_buku"]);
            $upd->execute();
            $upd->close();
        } else {
            // Buku kembali normal
            $upd = $conn->prepare("UPDATE buku SET status = 'tersedia' WHERE id_buku = ?");
            $upd->bind_param("s", $pinjam_data["id_buku"]);
            $upd->execute();
            $upd->close();
        }

        // Update status peminjaman
        $stmt = $conn->prepare("
            UPDATE peminjaman SET status = 'dikembalikan', tgl_kembali = ?
            WHERE id_peminjaman = ?
        ");
        $stmt->bind_param("ss", $tgl_kembali, $id_peminjaman);
        $stmt->execute();
        $stmt->close();

        // Buat record denda jika ada
        if ($denda_amount > 0 && $jenis_denda) {
            $stmt = $conn->prepare("
                INSERT INTO denda (id_peminjaman, jumlah_denda, jenis, status_bayar)
                VALUES (?, ?, ?, 'belum_bayar')
            ");
            $stmt->bind_param("sis", $id_peminjaman, $denda_amount, $jenis_denda);
            $stmt->execute();
            $stmt->close();

            $msg = "Buku <strong>" . htmlspecialchars($pinjam_data["judul"]) . "</strong> berhasil dikembalikan. "
                . "Denda: <strong>Rp " . number_format($denda_amount, 0, ',', '.') . "</strong>";
        } else {
            $msg = "Buku <strong>" . htmlspecialchars($pinjam_data["judul"]) . "</strong> berhasil dikembalikan. Terima kasih!";
        }

        $tab = "history";
    }
}

// ============================================================
// CATALOG — semua buku + filter + search + pagination
// ============================================================
$search_judul    = trim($_GET["judul"] ?? "");
$search_penulis  = trim($_GET["penulis"] ?? "");
$kategori        = trim($_GET["kategori"] ?? "");
$page            = max(1, (int)($_GET["page"] ?? 1));
$per_page        = 8;
$offset          = ($page - 1) * $per_page;

// Daftar penulis untuk datalist autocomplete
$penulis_list = [];
$res = $conn->query("SELECT DISTINCT pengarang FROM buku WHERE pengarang IS NOT NULL ORDER BY pengarang");
while ($p = $res->fetch_assoc()) {
    $penulis_list[] = $p["pengarang"];
}

// Filter kategori untuk dropdown
$kategori_list = [];
$res = $conn->query("SELECT DISTINCT kategori FROM buku WHERE kategori IS NOT NULL ORDER BY kategori");
while ($k = $res->fetch_assoc()) {
    $kategori_list[] = $k["kategori"];
}

// Build query catalog
$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($search_judul !== "") {
    $where   .= " AND b.judul LIKE ?";
    $params[] = "%" . $search_judul . "%";
    $types   .= "s";
}

if ($search_penulis !== "") {
    $where   .= " AND b.pengarang LIKE ?";
    $params[] = "%" . $search_penulis . "%";
    $types   .= "s";
}

if ($kategori !== "") {
    $where   .= " AND b.kategori = ?";
    $params[] = $kategori;
    $types   .= "s";
}

$ada_filter = $search_judul !== "" || $search_penulis !== "" || $kategori !== "";

// Total buku untuk pagination
$count_query = "SELECT COUNT(*) AS total FROM buku b $where";
if (!empty($params)) {
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_buku = $stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();
} else {
    $total_buku = $conn->query($count_query)->fetch_assoc()["total"];
}
$total_pages = max(1, ceil($total_buku / $per_page));

// Ambil buku sesuai halaman
$catalog = [];
$catalog_query = "
    SELECT b.id_buku, b.judul, b.pengarang, b.kategori,
           b.cover_url, b.status, b.stok
    FROM buku b
    $where
    ORDER BY b.judul ASC
    LIMIT $per_page OFFSET $offset
";

if (!empty($params)) {
    $stmt = $conn->prepare($catalog_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
} else {
    $res = $conn->query($catalog_query);
}
while ($row = $res->fetch_assoc()) {
    $catalog[] = $row;
}

// ============================================================
// HISTORY — riwayat peminjaman user ini
// ============================================================
$history = [];
if ($id_anggota) {
    $stmt = $conn->prepare("
        SELECT p.id_peminjaman, p.tgl_pinjam, p.tgl_jatuh_tempo, p.tgl_kembali,
               p.status AS status_pinjam,
               b.id_buku, b.judul, b.pengarang, b.cover_url,
               b.edisi, b.isbn, b.status AS status_buku,
               d.jumlah_denda, d.jenis AS jenis_denda, d.status_bayar
        FROM peminjaman p
        JOIN buku b ON p.id_buku = b.id_buku
        LEFT JOIN denda d ON d.id_peminjaman = p.id_peminjaman
        WHERE p.id_anggota = ?
        ORDER BY p.tgl_pinjam DESC
    ");
    $stmt->bind_param("s", $id_anggota);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
}

// Helper: cover fallback
function cover($url)
{
    return !empty($url) ? htmlspecialchars($url) : "aset/no-cover.png";
}

// Helper: label badge status peminjaman
function badge_pinjam($status, $tgl_tempo)
{
    if ($status === "dipinjam" && date("Y-m-d") > $tgl_tempo) {
        return ["Terlambat", "badge-red"];
    }
    $map = [
        "dipinjam"     => ["Borrowed",  "badge-orange"],
        "dikembalikan" => ["Done",      "badge-green"],
        "terlambat"    => ["Terlambat", "badge-red"],
        "hilang"       => ["Hilang",    "badge-gray"],
    ];
    return $map[$status] ?? [$status, "badge-gray"];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GiMi Library — Books</title>
    <link rel="stylesheet" href="css/books.css">
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-logo"><img src="aset/img/logo.png" alt="GiMi Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="books.php" class="active">Books</a></li>
            <li><a href="facilities.php">Facilities</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li>
                <a href="../../regis/logout.php" style="color:#c0392b;">Logout</a>
            </li>
        </ul>
    </nav>

    <!-- HERO -->
    <section class="hero-books">
        <div class="hero-title">
            <span class="hero-bo">BO</span>
            <span class="hero-ok">OK</span>
        </div>
    </section>

    <!-- TAB TOGGLE -->
    <div class="tab-toggle">
        <a href="books.php?tab=catalog" class="tab-btn <?= $tab === 'catalog' ? 'active' : '' ?>">Catalog</a>
        <a href="books.php?tab=history" class="tab-btn <?= $tab === 'history' ? 'active' : '' ?>">History</a>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($msg): ?>
        <div class="alert alert-success"><?= $msg ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <!-- ==================== CATALOG ==================== -->
    <?php if ($tab === "catalog"): ?>
        <section class="catalog-section">

            <!-- Filter & Search -->
            <form method="GET" action="books.php" class="filter-bar">
                <input type="hidden" name="tab" value="catalog">

                <div class="filter-group">
                    <label>Judul</label>
                    <input type="text" name="judul" placeholder="Cari judul buku..."
                        value="<?= htmlspecialchars($search_judul) ?>">
                </div>

                <div class="filter-group">
                    <label>Penulis</label>
                    <input type="text" name="penulis" placeholder="Cari nama penulis..."
                        value="<?= htmlspecialchars($search_penulis) ?>"
                        list="datalist-penulis" autocomplete="off">
                    <datalist id="datalist-penulis">
                        <?php foreach ($penulis_list as $pn): ?>
                            <option value="<?= htmlspecialchars($pn) ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="filter-group">
                    <label>Kategori</label>
                    <select name="kategori">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategori_list as $kat): ?>
                            <option value="<?= htmlspecialchars($kat) ?>"
                                <?= $kategori === $kat ? "selected" : "" ?>>
                                <?= htmlspecialchars($kat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-search">Cari</button>
                    <?php if ($ada_filter): ?>
                        <a href="books.php?tab=catalog" class="btn-reset">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($ada_filter): ?>
                <p class="filter-info">
                    Menampilkan <strong><?= $total_buku ?></strong> hasil
                    <?= $search_judul ? " · judul: <em>\"" . htmlspecialchars($search_judul) . "\"</em>" : "" ?>
                    <?= $search_penulis ? " · penulis: <em>\"" . htmlspecialchars($search_penulis) . "\"</em>" : "" ?>
                    <?= $kategori ? " · kategori: <em>\"" . htmlspecialchars($kategori) . "\"</em>" : "" ?>
                </p>
            <?php endif; ?>

            <!-- Grid Buku -->
            <?php if (empty($catalog)): ?>
                <p class="empty-state">Tidak ada buku ditemukan.</p>
            <?php else: ?>
                <div class="book-grid">
                    <?php foreach ($catalog as $bk): ?>
                        <div class="book-card" onclick="openDetailModal(
            '<?= htmlspecialchars($bk['id_buku']) ?>',
            '<?= htmlspecialchars(addslashes($bk['judul'])) ?>',
            '<?= htmlspecialchars(addslashes($bk['pengarang'])) ?>',
            '<?= cover($bk['cover_url']) ?>',
            '<?= $bk['status'] ?>'
        )">
                            <div class="book-cover">
                                <img src="<?= cover($bk['cover_url']) ?>"
                                    alt="<?= htmlspecialchars($bk['judul']) ?>"
                                    onerror="this.src='aset/no-cover.png'">
                            </div>
                            <div class="book-meta">
                                <h4><?= htmlspecialchars($bk['judul']) ?></h4>
                                <p><?= htmlspecialchars($bk['pengarang']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?tab=catalog&page=<?= $page - 1 ?>&judul=<?= urlencode($search_judul) ?>&penulis=<?= urlencode($search_penulis) ?>&kategori=<?= urlencode($kategori) ?>"
                                class="page-btn">‹</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?tab=catalog&page=<?= $i ?>&judul=<?= urlencode($search_judul) ?>&penulis=<?= urlencode($search_penulis) ?>&kategori=<?= urlencode($kategori) ?>"
                                class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?tab=catalog&page=<?= $page + 1 ?>&judul=<?= urlencode($search_judul) ?>&penulis=<?= urlencode($search_penulis) ?>&kategori=<?= urlencode($kategori) ?>"
                                class="page-btn">›</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </section>

        <!-- Modal Detail Buku + Pinjam -->
        <div class="modal-overlay" id="detailModal" style="display:none">
            <div class="modal-box">
                <button class="modal-close" onclick="closeDetailModal()">✕</button>
                <div class="modal-content">
                    <img id="modal-cover" src="" alt="">
                    <div class="modal-info">
                        <h3 id="modal-judul"></h3>
                        <p id="modal-pengarang"></p>
                        <span id="modal-badge" class="badge"></span>

                        <a href="bookDetail.php" id="btn-pinjam-link" class="btn-primary-link">
                            Deskripsi Buku
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== HISTORY ==================== -->
    <?php else: ?>
        <section class="history-section">
            <?php if (empty($history)): ?>
                <p class="empty-state">Belum ada riwayat peminjaman.</p>
            <?php else: ?>
                <div class="history-grid">
                    <?php foreach ($history as $h):
                        [$label_status, $badge_class] = badge_pinjam($h["status_pinjam"], $h["tgl_jatuh_tempo"]);
                        $terlambat = $h["status_pinjam"] === "dipinjam" && date("Y-m-d") > $h["tgl_jatuh_tempo"];
                    ?>
                        <div class="history-card">
                            <div class="history-card-header">
                                <span class="item-details-label">ITEM DETAILS</span>
                                <span class="badge <?= $badge_class ?>"><?= $label_status ?></span>
                            </div>

                            <img src="<?= cover($h['cover_url']) ?>"
                                alt="<?= htmlspecialchars($h['judul']) ?>"
                                onerror="this.src='aset/no-cover.png'"
                                class="history-cover">

                            <h3 class="history-title"><?= htmlspecialchars($h['judul']) ?></h3>
                            <p class="history-author"><?= htmlspecialchars($h['pengarang']) ?></p>

                            <div class="history-meta">
                                <div class="meta-row">
                                    <span>Edition</span>
                                    <strong><?= htmlspecialchars($h['edisi'] ?? '-') ?></strong>
                                </div>
                                <div class="meta-row">
                                    <span>ISBN</span>
                                    <strong><?= htmlspecialchars($h['isbn'] ?? '-') ?></strong>
                                </div>
                                <div class="meta-row">
                                    <span>Dipinjam</span>
                                    <strong><?= date("d M Y", strtotime($h['tgl_pinjam'])) ?></strong>
                                </div>
                                <div class="meta-row">
                                    <span>Jatuh Tempo</span>
                                    <strong class="<?= $terlambat ? 'text-red' : '' ?>">
                                        <?= date("d M Y", strtotime($h['tgl_jatuh_tempo'])) ?>
                                    </strong>
                                </div>
                                <?php if ($h['tgl_kembali']): ?>
                                    <div class="meta-row">
                                        <span>Dikembalikan</span>
                                        <strong><?= date("d M Y", strtotime($h['tgl_kembali'])) ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($h['jumlah_denda'] > 0): ?>
                                    <div class="meta-row denda-row">
                                        <span>Denda</span>
                                        <strong class="text-red">
                                            Rp <?= number_format($h['jumlah_denda'], 0, ',', '.') ?>
                                            <?= $h['status_bayar'] === 'belum_bayar' ? '⚠️' : '✓' ?>
                                        </strong>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Tombol aksi -->
                            <?php if ($h["status_pinjam"] === "dipinjam"): ?>
                                <!-- Kembalikan -->
                                <button class="btn-return"
                                    onclick="openReturnModal(
                            '<?= $h['id_peminjaman'] ?>',
                            '<?= htmlspecialchars(addslashes($h['judul'])) ?>'
                        )">
                                    Return Book
                                </button>

                            <?php elseif ($h["status_pinjam"] === "dikembalikan"): ?>
                                <!-- Pinjam Lagi -->
                                <?php if ($h["status_buku"] === "tersedia"): ?>
                                    <form method="POST">
                                        <input type="hidden" name="id_buku" value="<?= $h['id_buku'] ?>">
                                        <button type="submit" name="ajukan_pinjam" class="btn-borrow-again">
                                            Borrow Again
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn-borrow-again disabled" disabled>Tidak Tersedia</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Modal Konfirmasi Pengembalian -->
        <div class="modal-overlay" id="returnModal" style="display:none">
            <div class="modal-box">
                <button class="modal-close" onclick="closeReturnModal()">✕</button>
                <h3>Kembalikan Buku</h3>
                <p id="return-judul" class="return-judul-text"></p>
                <form method="POST" id="form-return">
                    <input type="hidden" name="id_peminjaman" id="return-id-pinjam">
                    <div class="form-group">
                        <label>Kondisi Buku</label>
                        <select name="kondisi" class="select-kondisi">
                            <option value="baik">Baik</option>
                            <option value="rusak">Rusak (denda Rp 50.000)</option>
                            <option value="hilang">Hilang (denda Rp 150.000)</option>
                        </select>
                    </div>
                    <button type="submit" name="kembalikan" class="btn-primary">Konfirmasi Pengembalian</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- SCRIPTS -->
    <script>
        // Modal detail buku (catalog)function openDetailModal(id, judul, pengarang, cover, status) {
        // Set data teks dan gambar
function openDetailModal(id, judul, pengarang, cover, status) {
    // Set data teks dan gambar
    document.getElementById('modal-judul').textContent = judul;
    document.getElementById('modal-pengarang').textContent = pengarang;
    document.getElementById('modal-cover').src = cover;

    const badge = document.getElementById('modal-badge');
    const btnPinjam = document.getElementById('btn-pinjam-link');

    // Update Link Href (Ganti detail_buku.php sesuai file tujuanmu)
    btnPinjam.href = "bookDetail.php?id=" + id;

    if (status === 'tersedia') {
        badge.textContent = 'Tersedia';
        badge.className = 'badge badge-green';
        btnPinjam.style.display = 'inline-flex';
    } else {
        badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        badge.className = 'badge badge-red';
        btnPinjam.style.display = 'none';
    }

    document.getElementById('detailModal').style.display = 'flex';
}

        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        // Modal konfirmasi pengembalian
        function openReturnModal(id_pinjam, judul) {
            document.getElementById('return-id-pinjam').value = id_pinjam;
            document.getElementById('return-judul').textContent = '"' + judul + '"';
            document.getElementById('returnModal').style.display = 'flex';
        }

        function closeReturnModal() {
            document.getElementById('returnModal').style.display = 'none';
        }

        // Tutup modal klik overlay
        ['detailModal', 'returnModal'].forEach(function(id) {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) this.style.display = 'none';
            });
        });
    </script>

</body>

</html>