<?php
session_start();
require "../../regis/koneksi.php";

// 1. Proteksi Halaman
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../../regis/login.php");
    exit;
}
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Jika ID kosong, langsung lempar balik ke halaman daftar buku
    header("Location: books.php");
    exit;
}

$id_user_login = $_SESSION['id_pengguna'];
$id_buku = $_GET['id'];

// 2. Ambil Data User & Buku (Lakukan di awal agar variabel tersedia untuk Logika Proses)
// Ambil data Anggota
$query_user = $conn->prepare("SELECT id_anggota, nama, nim FROM anggota WHERE id_pengguna = ?");
$query_user->bind_param("s", $id_user_login);
$query_user->execute();
$user_data = $query_user->get_result()->fetch_assoc();
$query_user->close();

if (!$user_data) {
    die("Data anggota tidak ditemukan. Pastikan profil Anda sudah lengkap.");
}

$id_anggota = $user_data['id_anggota'];

// Ambil data Buku
$query_buku = $conn->prepare("SELECT judul, pengarang, cover_url, edisi, isbn, status FROM buku WHERE id_buku = ?");
$query_buku->bind_param("s", $id_buku);
$query_buku->execute();
$buku = $query_buku->get_result()->fetch_assoc();
$query_buku->close();

if (!$buku) {
    die("Data buku tidak ditemukan.");
}

// 3. Logika Proses Simpan (Setelah data user/buku dipastikan ada)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_pinjam'])) {
    $id_buku_post = $_POST['id_buku'];
    $tgl_pinjam   = $_POST['tgl_pinjam'];
    $tgl_kembali  = $_POST['tgl_kembali'];

    // Validasi sederhana di sisi server
    if (strtotime($tgl_kembali) < strtotime($tgl_pinjam)) {
        $error_msg = "Tanggal kembali tidak boleh sebelum tanggal pinjam.";
    } else {
        // Query INSERT
        $sql_simpan = "INSERT INTO peminjaman (id_peminjaman, id_anggota, id_buku, tgl_pinjam, tgl_jatuh_tempo, status) 
                       VALUES (UUID(), ?, ?, ?, ?, 'pending')";

        $stmt_save = $conn->prepare($sql_simpan);
        $stmt_save->bind_param("ssss", $id_anggota, $id_buku_post, $tgl_pinjam, $tgl_kembali);

        if ($stmt_save->execute()) {
            echo "<script>
                    alert('Request peminjaman berhasil dikirim! Silakan tunggu konfirmasi admin.'); 
                    window.location.href = 'books.php?tab=history'; 
                  </script>";
            exit;
        } else {
            $error_msg = "Gagal menyimpan: " . $stmt_save->error;
        }
    }
}

$cover = !empty($buku['cover_url']) ? $buku['cover_url'] : "aset/no-cover.png";
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Borrow Item - <?= htmlspecialchars($buku['judul']) ?></title>
    <link rel="stylesheet" href="css/pinjam.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <li>
                <a href="../../regis/logout.php" style="color:#c0392b;">Logout</a>
            </li>
        </ul>
    </nav>
    <div class="main-content">
        <?php if (isset($error_msg)): ?>
            <div style="color: red; background: #fee; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?= $error_msg ?>
            </div>
        <?php endif; ?>

        <header class="page-header">
            <h1>Borrow Item</h1>
            <p>Complete the details below to confirm your loan.</p>
        </header>

        <div class="borrow-container">
            <div class="glass-card item-details">
                <div class="card-header">
                    <span>ITEM DETAILS</span>
                    <span class="badge-available" style="background: #2ecc71; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px;">
                        <?= strtoupper($buku['status']) ?>
                    </span>
                </div>
                <div class="book-info" style="margin-top: 20px; text-align: center;">
                    <img src="<?= $cover ?>" alt="Cover" class="cover-img" style="width: 150px; border-radius: 8px;">
                    <h2 style="margin: 15px 0 5px; font-size: 18px;"><?= htmlspecialchars($buku['judul']) ?></h2>
                    <p class="author" style="color: #666; font-size: 14px;"><?= htmlspecialchars($buku['pengarang']) ?></p>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; margin-top: 20px; font-size: 13px;">
                    <span>Edition</span>
                    <strong><?= htmlspecialchars($buku['edisi']) ?></strong>
                </div>
                <div class="detail-row" style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 13px;">
                    <span>ISBN</span>
                    <strong><?= htmlspecialchars($buku['isbn']) ?></strong>
                </div>
            </div>

            <div class="white-card borrow-form">
                <form action="" method="POST">
                    <input type="hidden" name="id_buku" value="<?= $id_buku ?>">
                    <input type="hidden" name="proses_pinjam" value="1">

                    <h3><i class="fa fa-user-circle"></i> Borrower Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>NAME</label>
                            <div class="input-wrapper">
                                <input type="text" value="<?= htmlspecialchars($user_data['nama']) ?>" readonly>
                                <i class="fa fa-lock"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>NIM</label>
                            <div class="input-wrapper">
                                <input type="text" value="<?= htmlspecialchars($user_data['nim']) ?>" readonly>
                                <i class="fa fa-lock"></i>
                            </div>
                        </div>
                    </div>

                    <h3><i class="fa fa-calendar-alt"></i> Duration</h3>
                    <div class="duration-box">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" name="tgl_pinjam" id="start_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="arrow">→</div>
                            <div class="form-group">
                                <label>Return Date</label>
                                <input type="date" name="tgl_kembali" id="return_date" required>
                            </div>
                        </div>
                        <div class="duration-badge" id="duration-text">Duration: 0 Days</div>
                    </div>

                    <div class="terms" style="margin-top: 20px; display: flex; gap: 10px;">
                        <input type="checkbox" id="agree" required>
                        <label for="agree" style="font-size: 12px;">
                            <strong>I agree to the borrowing terms</strong><br>
                            <span style="color: #888;">I promise to return this item in the same condition.</span>
                        </label>
                    </div>

                    <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px;">
                        <a href="books.php" class="btn-cancel" style="text-decoration: none; color: #666; padding-top: 8px;">Cancel</a>
                        <button type="submit" class="btn-confirm" style="background: #1a73e8; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">
                            Confirm Borrow <i class="fa fa-check"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const startInput = document.getElementById('start_date');
        const endInput = document.getElementById('return_date');
        const textDisplay = document.getElementById('duration-text');

        function updateDiff() {
            if (startInput.value && endInput.value) {
                const s = new Date(startInput.value);
                const e = new Date(endInput.value);
                const diff = Math.ceil((e - s) / (1000 * 60 * 60 * 24));

                if (diff > 0 && diff <= 21) {
                    textDisplay.innerText = `Duration: ${diff} Days`;
                    textDisplay.style.color = "#1a73e8";
                } else if (diff > 21) {
                    alert("Maksimal peminjaman 21 hari!");
                    endInput.value = "";
                    textDisplay.innerText = `Duration: 0 Days`;
                } else {
                    textDisplay.innerText = `Duration: 0 Days`;
                }
            }
        }
        startInput.onchange = updateDiff;
        endInput.onchange = updateDiff;
    </script>
</body>

</html>