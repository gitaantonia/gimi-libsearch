<?php
session_start();
include '../../regis/koneksi.php';
require "helpers.php";

// Redirect kalau belum login
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../../regis/login.php");
    exit;
}

$db = isset($conn) ? $conn : $koneksi;

// ============================================================
// AMBIL PARAMETER
// ============================================================
$id_fasilitas = $_GET['id']   ?? '';
$time_key     = $_GET['time'] ?? '';
$tanggal      = $_GET['tgl']  ?? date('Y-m-d');
$id_pengguna  = $_SESSION['id_pengguna'];

// Ambil data anggota berdasarkan id_pengguna
$stmt = $db->prepare("SELECT * FROM anggota WHERE id_pengguna = ?");
$stmt->bind_param("i", $id_pengguna);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("<div style='padding:40px;text-align:center;font-family:sans-serif;'>Data anggota tidak ditemukan.</div>");
}

$id_anggota = $user['id_anggota'];

if (empty($id_fasilitas) || empty($time_key)) {
    die("<div style='padding:40px;text-align:center;font-family:sans-serif;'>Parameter tidak lengkap.</div>");
}

// ============================================================
// AMBIL DATA FASILITAS
// ============================================================
$fasilitas = getFacilityData($db, $id_fasilitas);
if (!$fasilitas) {
    die("<div style='padding:40px;text-align:center;font-family:sans-serif;'>Fasilitas tidak ditemukan.</div>");
}

// ============================================================
// PROSES BOOKING (POST)
// ============================================================
$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jam_mulai   = $_POST['jam_mulai']  ?? $time_key;
    $durasi      = (int)($_POST['durasi'] ?? 1);           // jam
    $catatan     = mysqli_real_escape_string($db, $_POST['catatan'] ?? '');
    $tgl_booking = $_POST['tanggal']    ?? $tanggal;

    // Hitung jam selesai
    $jam_selesai = date('H:i', strtotime($jam_mulai) + ($durasi * 3600));

    // Cek slot masih kosong (race condition guard)
    $cek = mysqli_query(
        $db,
        "SELECT id_booking FROM bookings
         WHERE id_fasilitas = '$id_fasilitas'
         AND tanggal = '$tgl_booking'
         AND status_booking != 'cancelled'
         AND jam_mulai = '$jam_mulai:00'"
    );

    if (mysqli_num_rows($cek) > 0) {
        $error = "Slot ini sudah dibooking orang lain. Silakan pilih waktu lain.";
    } else {
        // Insert booking
        $insert = mysqli_query(
            $db,
            "INSERT INTO bookings (id_anggota, id_fasilitas, tanggal, jam_mulai, jam_selesai, catatan, status_booking, created_at)
             VALUES ('$id_anggota', '$id_fasilitas', '$tgl_booking', '$jam_mulai:00', '$jam_selesai:00', '$catatan', 'pending', NOW())"
        );

        if ($insert) {
            $success = true;
            $new_id  = mysqli_insert_id($db);
        } else {
            $error = "Gagal menyimpan booking: " . mysqli_error($db);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking — <?= htmlspecialchars($fasilitas['nama_fasilitas']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/booking.css">
</head>

<body>
    <div class="page-wrapper">

        <?php if ($success): ?>
            <!-- ============================================================
         SUCCESS PAGE
    ============================================================ -->
            <div class="success-wrapper">
                <div class="success-icon">
                    <svg width="38" height="38" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                </div>
                <h2>Booking Confirmed!</h2>
                <p>Your booking has been submitted and is pending approval.<br>You'll be notified once it's confirmed.</p>

                <div class="success-detail">
                    <div class="success-row">
                        <span class="label">Facility</span>
                        <span class="value"><?= htmlspecialchars($fasilitas['nama_fasilitas']) ?></span>
                    </div>
                    <div class="success-row">
                        <span class="label">Date</span>
                        <span class="value"><?= date('D, d M Y', strtotime($tanggal)) ?></span>
                    </div>
                    <div class="success-row">
                        <span class="label">Time</span>
                        <span class="value"><?= date('h:i A', strtotime($time_key)) ?></span>
                    </div>
                    <div class="success-row">
                        <span class="label">Location</span>
                        <span class="value"><?= htmlspecialchars($fasilitas['lokasi']) ?></span>
                    </div>
                    <div class="success-row">
                        <span class="label">Booking ID</span>
                        <span class="value" style="color:var(--blue)">#<?= str_pad($new_id, 5, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="success-row">
                        <span class="label">Status</span>
                        <span class="value" style="color:var(--green)">Pending Approval</span>
                    </div>
                </div>

                <a href="facilities.php" class="btn-back">
                    <svg width="16" height="16" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
                        <line x1="19" y1="12" x2="5" y2="12" />
                        <polyline points="12 19 5 12 12 5" />
                    </svg>
                    Back to Facilities
                </a>
            </div>

        <?php else: ?>
            <!-- ============================================================
         BOOKING FORM
    ============================================================ -->

            <!-- Breadcrumb -->
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

            <div class="page-heading">Confirm Your Booking</div>
            <p class="page-sub">Review the details below before confirming your reservation.</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="booking-grid">

                <!-- ===== LEFT — Form ===== -->
                <div>
                    <!-- Info user yang login -->
                    <div class="glass-card">
                        <h2>Booked By</h2>
                        <div class="user-info-row">
                            <div class="user-avatar"><?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?></div>
                            <div>
                                <div class="user-name"><?= htmlspecialchars($user['nama'] ?? '-') ?></div>
                                <div class="user-email"><?= htmlspecialchars($user['email'] ?? ($user['no_anggota'] ?? '-')) ?></div>
                            </div>
                        </div>

                        <form method="POST" id="booking-form">
                            <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
                            <input type="hidden" name="jam_mulai" value="<?= htmlspecialchars($time_key) ?>">

                            <!-- Tanggal (read only) -->
                            <div class="form-group">
                                <label>📅 Tanggal Booking</label>
                                <input type="text" class="form-control"
                                    value="<?= date('l, d F Y', strtotime($tanggal)) ?>" disabled>
                            </div>

                            <!-- Jam mulai (read only) -->
                            <div class="form-group">
                                <label>🕐 Jam Mulai</label>
                                <input type="text" class="form-control"
                                    value="<?= date('h:i A', strtotime($time_key)) ?>" disabled>
                            </div>

                            <!-- Durasi -->
                            <div class="form-group">
                                <label>⏱ Durasi Booking <span>*</span></label>
                                <div class="duration-group">
                                    <div class="duration-pill">
                                        <input type="radio" name="durasi" id="dur1" value="1" checked>
                                        <label for="dur1">1 Hour</label>
                                    </div>
                                </div>
                                <small style="color:var(--muted);font-size:.76rem;margin-top:6px;display:block;">
                                    Maksimal 1 jam per sesi.
                                </small>
                            </div>

                            <!-- Catatan -->
                            <div class="form-group">
                                <label>📝 Catatan <small style="font-weight:400;color:var(--muted)">(opsional)</small></label>
                                <textarea name="catatan" class="form-control"
                                    placeholder="Contoh: untuk rapat kelompok, butuh proyektor..."></textarea>
                            </div>

                            <!-- Terms -->
                            <div class="terms-check">
                                <input type="checkbox" id="terms" required>
                                <label for="terms">
                                    Saya menyetujui <a href="#">syarat & ketentuan</a> penggunaan fasilitas perpustakaan dan akan menjaga kebersihan serta ketertiban ruangan.
                                </label>
                            </div>

                            <button type="submit" class="btn-submit" id="submit-btn">
                                <svg width="18" height="18" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                    <polyline points="22 4 12 14.01 9 11.01" />
                                </svg>
                                Konfirmasi Booking
                            </button>
                            <a href="desc_fasilitas.php?id=<?= $id_fasilitas ?>" class="btn-cancel">← Batalkan</a>
                        </form>
                    </div>
                </div>

                <!-- ===== RIGHT — Preview Fasilitas ===== -->
                <div>
                    <div class="glass-card">
                        <h2>Facility</h2>
                        <img
                            src="../../admin/dashboard/upload/<?= htmlspecialchars($fasilitas['gambar']) ?>"
                            alt="<?= htmlspecialchars($fasilitas['nama_fasilitas']) ?>"
                            class="facility-thumb"
                            onerror="this.onerror=null; this.src='../../admin/dashboard/upload/default.jpg';">

                        <div class="facility-name"><?= htmlspecialchars($fasilitas['nama_fasilitas']) ?></div>

                        <div class="facility-meta">
                            <span>
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                <?= htmlspecialchars($fasilitas['lokasi']) ?>
                            </span>
                            <span>
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                </svg>
                                Kapasitas <?= (int)$fasilitas['kapasitas'] ?> orang
                            </span>
                        </div>

                        <div class="cat-badge">
                            <?= getCategoryIcon($fasilitas['kategori']) ?>
                            <?= formatCategoryText($fasilitas['kategori']) ?>
                        </div>

                        <!-- Summary -->
                        <div class="summary-box">
                            <div class="summary-row">
                                <span class="label">Tanggal</span>
                                <span class="value"><?= date('d M Y', strtotime($tanggal)) ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="label">Jam Mulai</span>
                                <span class="value"><?= date('h:i A', strtotime($time_key)) ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="label">Jam Selesai</span>
                                <span class="value" id="preview-selesai">
                                    <?= date('h:i A', strtotime($time_key) + 3600) ?>
                                </span>
                            </div>
                            <div class="summary-row">
                                <span class="label">Biaya</span>
                                <span class="value green">Gratis</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div><!-- .page-wrapper -->

    <script>
        // Update preview jam selesai saat durasi berubah
        const jamMulai = '<?= $time_key ?>';
        const radios = document.querySelectorAll('input[name="durasi"]');
        const preview = document.getElementById('preview-selesai');

        function updateSelesai() {
            const durasi = parseInt(document.querySelector('input[name="durasi"]:checked').value);
            const [h, m] = jamMulai.split(':').map(Number);
            const total = h * 60 + m + durasi * 60;
            const hh = Math.floor(total / 60) % 24;
            const mm = total % 60;
            const ampm = hh >= 12 ? 'PM' : 'AM';
            const hDisp = String(hh > 12 ? hh - 12 : (hh === 0 ? 12 : hh)).padStart(2, '0');
            const mDisp = String(mm).padStart(2, '0');
            if (preview) preview.textContent = `${hDisp}:${mDisp} ${ampm}`;
        }

        radios.forEach(r => r.addEventListener('change', updateSelesai));

        // Prevent double submit
        const form = document.getElementById('booking-form');
        const btn = document.getElementById('submit-btn');
        if (form) {
            form.addEventListener('submit', () => {
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Memproses...';
                }
            });
        }
    </script>
</body>

</html>