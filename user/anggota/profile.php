<?php
session_start();
require "../../regis/koneksi.php";

// Proteksi halaman
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../../regis/.php");
    exit;
}

$id_pengguna   = $_SESSION['id_pengguna'];
$nama_pengguna = $_SESSION['nama'] ?? "Pengguna";

$msg   = "";
$error = "";

// ============================================================
// Ambil data pengguna
// ============================================================
$stmt = $conn->prepare("SELECT id_pengguna, nama, email, role, aktif FROM pengguna WHERE id_pengguna = ?");
$stmt->bind_param("s", $id_pengguna);
$stmt->execute();
$pengguna = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ============================================================
// Ambil data anggota berdasarkan id_pengguna (FK di tabel anggota)
// ============================================================
$stmt = $conn->prepare("SELECT * FROM anggota WHERE id_pengguna = ?");
$stmt->bind_param("s", $id_pengguna);
$stmt->execute();
$anggota = $stmt->get_result()->fetch_assoc();
$stmt->close();

$sudah_anggota = !empty($anggota);

// ============================================================
// PROSES DAFTAR ANGGOTA (jika belum punya data anggota)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daftar_anggota'])) {
    $nim       = trim($_POST['nim']);
    $no_telp   = trim($_POST['no_telp']);
    $alamat    = trim($_POST['alamat']);
    $tgl_lahir = trim($_POST['tgl_lahir']);
    $jurusan   = trim($_POST['jurusan']);

    if (empty($nim) || empty($no_telp) || empty($alamat)) {
        $error = "NIM, No. Telepon, dan Alamat wajib diisi.";
    } else {
        // Cek NIM sudah dipakai
        $cek = $conn->prepare("SELECT id_anggota FROM anggota WHERE nim = ?");
        $cek->bind_param("s", $nim);
        $cek->execute();
        $nim_dipakai = $cek->get_result()->num_rows > 0;
        $cek->close();

        if ($nim_dipakai) {
            $error = "NIM sudah terdaftar oleh anggota lain.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO anggota (id_pengguna, nama, nim, no_telepon, alamat, tgl_lahir, jurusan, tanggal_daftar)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
            ");
            $stmt->bind_param("sssssss", $id_pengguna, $nama_pengguna, $nim, $no_telp, $alamat, $tgl_lahir, $jurusan);

            if ($stmt->execute()) {
                $msg = "Berhasil terdaftar sebagai anggota perpustakaan!";
                // Refresh data anggota
                $stmt2 = $conn->prepare("SELECT * FROM anggota WHERE id_pengguna = ?");
                $stmt2->bind_param("s", $id_pengguna);
                $stmt2->execute();
                $anggota = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
                $sudah_anggota = true;
            } else {
                $error = "Gagal mendaftar: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// ============================================================
// PROSES UPDATE DATA ANGGOTA
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_anggota']) && $sudah_anggota) {
    $no_telp = trim($_POST['no_telp']);
    $alamat  = trim($_POST['alamat']);
    $jurusan = trim($_POST['jurusan']);

    $stmt = $conn->prepare("
        UPDATE anggota SET no_telp = ?, alamat = ?, jurusan = ?
        WHERE id_pengguna = ?
    ");
    $stmt->bind_param("ssss", $no_telp, $alamat, $jurusan, $id_pengguna);

    if ($stmt->execute()) {
        $msg = "Data anggota berhasil diperbarui.";
        // Refresh
        $stmt2 = $conn->prepare("SELECT * FROM anggota WHERE id_pengguna = ?");
        $stmt2->bind_param("s", $id_pengguna);
        $stmt2->execute();
        $anggota = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
    } else {
        $error = "Gagal memperbarui data.";
    }
    $stmt->close();
}

// ============================================================
// Statistik peminjaman (jika sudah anggota)
// ============================================================
$stat_aktif     = 0;
$stat_selesai   = 0;
$stat_denda     = 0;

if ($sudah_anggota) {
    $id_anggota = $anggota['id_anggota'];

    $q = $conn->prepare("SELECT COUNT(*) AS total FROM peminjaman WHERE id_anggota = ? AND status = 'dipinjam'");
    $q->bind_param("s", $id_anggota);
    $q->execute();
    $stat_aktif = $q->get_result()->fetch_assoc()['total'];
    $q->close();

    $q = $conn->prepare("SELECT COUNT(*) AS total FROM peminjaman WHERE id_anggota = ? AND status = 'dikembalikan'");
    $q->bind_param("s", $id_anggota);
    $q->execute();
    $stat_selesai = $q->get_result()->fetch_assoc()['total'];
    $q->close();

    $q = $conn->prepare("
        SELECT COALESCE(SUM(d.jumlah_denda), 0) AS total
        FROM denda d
        JOIN peminjaman p ON d.id_peminjaman = p.id_peminjaman
        WHERE p.id_anggota = ? AND d.status_bayar = 'belum_bayar'
    ");
    $q->bind_param("s", $id_anggota);
    $q->execute();
    $stat_denda = $q->get_result()->fetch_assoc()['total'];
    $q->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil — GiMi Library</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>

<!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-logo"><img src="aset/img/logo.png" alt="GiMi Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="books.php">Books</a></li>
            <li><a href="facilities.php">Facilities</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="profile.php" class="active">Profile</a></li>
            <li>
                <a href="../../regis/logout.php" style="color:#c0392b;">Logout</a>
            </li>
        </ul>
    </nav>

<!-- HERO -->
<div class="hero">
    <div class="hero-inner">
        <div class="avatar"><?= mb_strtoupper(mb_substr($nama_pengguna, 0, 1)) ?></div>
        <div class="hero-text">
            <h1><?= htmlspecialchars($nama_pengguna) ?></h1>
            <p><?= htmlspecialchars($pengguna['email'] ?? '') ?></p>
            <?php if ($sudah_anggota): ?>
                <span class="hero-badge badge-member">✦ Anggota Perpustakaan</span>
            <?php else: ?>
                <span class="hero-badge badge-visitor">⌛ Belum Terdaftar sebagai Anggota</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- STAT BAR (hanya jika anggota) -->
<?php if ($sudah_anggota): ?>
<div class="stat-bar">
    <div class="stat-card">
        <div class="num"><?= $stat_aktif ?></div>
        <div class="lbl">Dipinjam</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= $stat_selesai ?></div>
        <div class="lbl">Selesai</div>
    </div>
    <div class="stat-card">
        <div class="num <?= $stat_denda > 0 ? 'red' : '' ?>">
            <?= $stat_denda > 0 ? 'Rp ' . number_format($stat_denda,0,',','.') : '—' ?>
        </div>
        <div class="lbl">Denda Aktif</div>
    </div>
</div>
<?php endif; ?>

<!-- PAGE CONTENT -->
<div class="page-content" style="margin-top: <?= $sudah_anggota ? '48px' : '32px' ?>">

    <!-- ALERT -->
    <?php if ($msg): ?>
        <div class="alert alert-success">✓ <?= htmlspecialchars($msg) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-error">✕ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ============ JIKA BELUM ANGGOTA: BANNER DAFTAR ============ -->
    <?php if (!$sudah_anggota): ?>
    <div class="register-banner">
        <div>
            <h3>Daftarkan Diri sebagai Anggota</h3>
            <p>Lengkapi data keanggotaan untuk mulai meminjam buku dari perpustakaan.</p>
        </div>
        <button class="btn btn-gold" onclick="document.getElementById('modalDaftar').style.display='flex'">
            Daftar Sekarang →
        </button>
    </div>
    <?php endif; ?>

    <!-- ============ INFO AKUN ============ -->
    <div class="card">
        <div class="card-head">
            <h2>Akun</h2>
            <div class="icon">👤</div>
        </div>
        <div class="card-body">
            <div class="info-row">
                <span class="lbl">Nama</span>
                <span class="val"><?= htmlspecialchars($pengguna['nama']) ?></span>
            </div>
            <div class="info-row">
                <span class="lbl">Email</span>
                <span class="val"><?= htmlspecialchars($pengguna['email']) ?></span>
            </div>
            <div class="info-row">
                <span class="lbl">ID Pengguna</span>
                <span class="val id-badge"><?= htmlspecialchars($id_pengguna) ?></span>
            </div>
            <div class="info-row">
                <span class="lbl">Status</span>
                <span class="val" style="color: var(--green)">● <?= htmlspecialchars($pengguna['aktif'] ?? 'Aktif') ?></span>
            </div>
        </div>
    </div>

    <!-- ============ INFO ANGGOTA / KARTU ============ -->
    <?php if ($sudah_anggota): ?>
    <div class="card">
        <div class="card-head">
            <h2>Kartu Anggota</h2>
            <div class="icon">🪪</div>
        </div>
        <div class="card-body">
            <div class="member-card-visual">
                <div class="mcv-lib">GiMi Library · Member Card</div>
                <div class="mcv-name"><?= htmlspecialchars($anggota['nama']) ?></div>
                <div class="mcv-nim"><?= htmlspecialchars($anggota['nim']) ?></div>
                <div class="mcv-row">
                    <div class="mcv-col">
                        <span>Jurusan</span>
                        <strong><?= htmlspecialchars($anggota['jurusan'] ?? '—') ?></strong>
                    </div>
                    <div class="mcv-col">
                        <span>Tgl Daftar</span>
                        <strong><?= date('d M Y', strtotime($anggota['tanggal_daftar'] ?? 'now')) ?></strong>
                    </div>
                </div>
                <div class="mcv-id">ID: <?= htmlspecialchars($anggota['id_anggota']) ?></div>
            </div>
        </div>
    </div>

    <!-- ============ EDIT DATA ANGGOTA ============ -->
    <div class="card full">
        <div class="card-head">
            <h2>Edit Data Anggota</h2>
            <div class="icon">✏️</div>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nama (tidak bisa diubah)</label>
                        <input type="text" value="<?= htmlspecialchars($anggota['nama']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>NIM (tidak bisa diubah)</label>
                        <input type="text" value="<?= htmlspecialchars($anggota['nim']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="text" name="no_telp" value="<?= htmlspecialchars($anggota['no_telepon'] ?? '') ?>" placeholder="08xx-xxxx-xxxx" required>
                    </div>
                    <div class="form-group">
                        <label>Jurusan</label>
                        <input type="text" name="jurusan" value="<?= htmlspecialchars($anggota['jurusan'] ?? '') ?>" placeholder="Teknik Informatika">
                    </div>
                    <div class="form-group full">
                        <label>Alamat</label>
                        <textarea name="alamat" rows="3" placeholder="Jl. ..." required><?= htmlspecialchars($anggota['alamat'] ?? '') ?></textarea>
                    </div>
                    <div class="full" style="display:flex; justify-content:flex-end;">
                        <button type="submit" name="update_anggota" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php else: ?>

    <!-- ============ PLACEHOLDER ANGGOTA (belum daftar) ============ -->
    <div class="card">
        <div class="card-head">
            <h2>Data Anggota</h2>
            <div class="icon">🪪</div>
        </div>
        <div class="card-body" style="text-align:center; padding: 40px 24px; color: var(--muted);">
            <div style="font-size: 48px; margin-bottom: 12px;">📋</div>
            <p style="font-size: 15px;">Belum terdaftar sebagai anggota.</p>
            <p style="font-size: 13px; margin-top: 4px;">Daftar untuk mendapatkan akses peminjaman buku.</p>
            <button class="btn btn-gold btn-sm" style="margin-top: 20px;"
                onclick="document.getElementById('modalDaftar').style.display='flex'">
                Daftar Sekarang
            </button>
        </div>
    </div>

    <?php endif; ?>

</div><!-- /page-content -->


<!-- ========================================================
     MODAL DAFTAR ANGGOTA
======================================================== -->
<?php if (!$sudah_anggota): ?>
<div class="modal-overlay" id="modalDaftar" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('modalDaftar').style.display='none'">✕</button>
        <h3>Daftar Anggota</h3>
        <p>Lengkapi data berikut untuk mendaftarkan diri sebagai anggota perpustakaan GiMi.</p>

        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nama (dari akun)</label>
                    <input type="text" value="<?= htmlspecialchars($nama_pengguna) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>NIM *</label>
                    <input type="text" name="nim" placeholder="2xxxxxxxxxxxxx" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="tgl_lahir">
                </div>
                <div class="form-group">
                    <label>No. Telepon *</label>
                    <input type="text" name="no_telp" placeholder="08xx-xxxx-xxxx" required>
                </div>
                <div class="form-group">
                    <label>Jurusan</label>
                    <input type="text" name="jurusan" placeholder="Teknik Informatika">
                </div>
                <div class="form-group full">
                    <label>Alamat *</label>
                    <textarea name="alamat" rows="3" placeholder="Jl. ..." required></textarea>
                </div>
                <div class="full" style="display:flex; justify-content:flex-end; margin-top:4px;">
                    <button type="submit" name="daftar_anggota" class="btn btn-primary">Daftar Sekarang →</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Buka modal otomatis jika ada error registrasi -->
<?php if ($error): ?>
<script>document.getElementById('modalDaftar').style.display='flex';</script>
<?php endif; ?>
<?php endif; ?>

</body>
</html>
