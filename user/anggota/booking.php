<?php
session_start();
include '../regis/koneksi.php';
require "helpers.php";

// Redirect kalau belum login
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../regis/login.php");
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
    $cek = mysqli_query($db,
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
        $insert = mysqli_query($db,
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

// ============================================================
// HELPER
// ============================================================
?>
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking — <?= htmlspecialchars($fasilitas['nama_fasilitas']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           VARIABLES & RESET
        ============================================================ */
        :root {
            --blue:        #0061ff;
            --blue-dark:   #0047c0;
            --blue-light:  #eff6ff;
            --green:       #16a34a;
            --green-light: #dcfce7;
            --red:         #dc2626;
            --red-light:   #fee2e2;
            --text:        #111827;
            --muted:       #6b7280;
            --border:      #e5e7eb;
            --card:        rgba(255,255,255,0.78);
            --radius:      20px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text);
            min-height: 100vh;
            background-color: #dde8f2;
            background-image:
                radial-gradient(ellipse at 15% 35%, rgba(173,208,235,.55) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 65%, rgba(190,215,240,.45) 0%, transparent 50%),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='240'%3E%3Ccircle cx='50' cy='50' r='22' fill='none' stroke='%23a8c8e8' stroke-width='1.5' opacity='0.45'/%3E%3Ccircle cx='50' cy='50' r='12' fill='%23c5ddf2' opacity='0.28'/%3E%3Ccircle cx='180' cy='90' r='26' fill='none' stroke='%23a8c8e8' stroke-width='1.5' opacity='0.38'/%3E%3Ccircle cx='180' cy='90' r='14' fill='%23c5ddf2' opacity='0.22'/%3E%3Ccircle cx='100' cy='180' r='18' fill='none' stroke='%23b5cfe8' stroke-width='1.5' opacity='0.42'/%3E%3Ccircle cx='100' cy='180' r='9' fill='%23c5ddf2' opacity='0.26'/%3E%3C/svg%3E");
            background-attachment: fixed;
        }

        /* ============================================================
           LAYOUT
        ============================================================ */
        .page-wrapper {
            max-width: 960px;
            margin: 0 auto;
            padding: 36px 24px 80px;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            color: var(--muted);
            margin-bottom: 28px;
            list-style: none;
        }
        .breadcrumb a { color: var(--muted); text-decoration: none; }
        .breadcrumb a:hover { color: var(--text); }
        .breadcrumb li::after { content: '/'; margin-left: 6px; color: #d1d5db; }
        .breadcrumb li:last-child::after { display: none; }
        .breadcrumb li:last-child { color: var(--text); font-weight: 600; }

        /* Page title */
        .page-heading {
            font-size: 1.7rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .page-sub {
            font-size: 0.88rem;
            color: var(--muted);
            margin-bottom: 32px;
        }

        /* Two-col grid */
        .booking-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: start;
        }

        /* ============================================================
           GLASS CARD
        ============================================================ */
        .glass-card {
            background: var(--card);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.72);
            border-radius: var(--radius);
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
            padding: 28px;
            margin-bottom: 20px;
        }
        .glass-card h2 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 20px;
        }

        /* ============================================================
           FACILITY PREVIEW (kanan atas)
        ============================================================ */
        .facility-thumb {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 14px;
            display: block;
            background: #c9d8e8;
            margin-bottom: 16px;
        }
        .facility-name {
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .facility-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.82rem;
            color: var(--muted);
        }
        .facility-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cat-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--blue-light);
            color: var(--blue);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            margin-top: 10px;
        }

        /* Booking summary box */
        .summary-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            margin-top: 16px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row .label { color: var(--muted); }
        .summary-row .value { font-weight: 700; }
        .summary-row .value.green { color: var(--green); }

        /* ============================================================
           FORM
        ============================================================ */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 0.83rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
        }
        .form-group label span { color: var(--red); margin-left: 2px; }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            color: var(--text);
            background: white;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-control:focus { border-color: var(--blue); }
        .form-control:disabled {
            background: #f9fafb;
            color: var(--muted);
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 90px;
        }

        /* Durasi radio pills */
        .duration-group {
            display: flex;
            gap: 10px;
        }
        .duration-pill input { display: none; }
        .duration-pill label {
            display: block;
            padding: 10px 22px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s;
            margin: 0;
        }
        .duration-pill input:checked + label {
            border-color: var(--blue);
            background: var(--blue-light);
            color: var(--blue);
        }

        /* User info display */
        .user-info-row {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }
        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--blue-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 800;
            color: var(--blue);
            flex-shrink: 0;
        }
        .user-name { font-size: 0.9rem; font-weight: 700; }
        .user-email { font-size: 0.78rem; color: var(--muted); }

        /* Alert */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error { background: var(--red-light); color: var(--red); border: 1px solid #fca5a5; }
        .alert-success { background: var(--green-light); color: var(--green); border: 1px solid #86efac; }

        /* Terms */
        .terms-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.83rem;
            color: var(--muted);
            margin-bottom: 24px;
        }
        .terms-check input { margin-top: 2px; accent-color: var(--blue); flex-shrink: 0; }
        .terms-check a { color: var(--blue); text-decoration: none; font-weight: 600; }

        /* Submit */
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover { background: var(--blue-dark); transform: scale(1.01); }
        .btn-submit:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }

        /* Cancel link */
        .btn-cancel {
            display: block;
            text-align: center;
            margin-top: 12px;
            font-size: 0.85rem;
            color: var(--muted);
            text-decoration: none;
            font-weight: 600;
        }
        .btn-cancel:hover { color: var(--text); }

        /* ============================================================
           SUCCESS STATE
        ============================================================ */
        .success-wrapper {
            max-width: 520px;
            margin: 80px auto;
            text-align: center;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--green-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .success-wrapper h2 { font-size: 1.6rem; font-weight: 800; margin-bottom: 8px; }
        .success-wrapper p  { color: var(--muted); font-size: 0.92rem; margin-bottom: 32px; line-height: 1.6; }

        .success-detail {
            background: var(--card);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,.7);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 28px;
            text-align: left;
        }
        .success-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .success-row:last-child { border-bottom: none; }
        .success-row .label { color: var(--muted); }
        .success-row .value { font-weight: 700; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 30px;
            background: var(--blue);
            color: white;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-back:hover { background: var(--blue-dark); }

        /* Responsive */
        @media (max-width: 760px) {
            .booking-grid { grid-template-columns: 1fr; }
            .duration-group { flex-wrap: wrap; }
        }
    </style>
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
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <h2>Booking Confirmed! 🎉</h2>
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
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Back to Facilities
        </a>
    </div>

    <?php else: ?>
    <!-- ============================================================
         BOOKING FORM
    ============================================================ -->

    <!-- Breadcrumb -->
    <ul class="breadcrumb">
        <li><a href="dashboard.php">🏠 Home</a></li>
        <li><a href="facilities.php">Facilities</a></li>
        <li><a href="desc_fasilitas.php?id=<?= $id_fasilitas ?>"><?= htmlspecialchars($fasilitas['nama_fasilitas']) ?></a></li>
        <li>Booking</li>
    </ul>

    <div class="page-heading">Confirm Your Booking</div>
    <p class="page-sub">Review the details below before confirming your reservation.</p>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
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
                    <input type="hidden" name="tanggal"   value="<?= htmlspecialchars($tanggal) ?>">
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
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
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
                    src="../admin/upload/<?= htmlspecialchars($fasilitas['gambar']) ?>"
                    alt="<?= htmlspecialchars($fasilitas['nama_fasilitas']) ?>"
                    class="facility-thumb"
                    onerror="this.onerror=null; this.src='../aset/img/default.jpg';">

                <div class="facility-name"><?= htmlspecialchars($fasilitas['nama_fasilitas']) ?></div>

                <div class="facility-meta">
                    <span>
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        <?= htmlspecialchars($fasilitas['lokasi']) ?>
                    </span>
                    <span>
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
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
    const radios   = document.querySelectorAll('input[name="durasi"]');
    const preview  = document.getElementById('preview-selesai');

    function updateSelesai() {
        const durasi = parseInt(document.querySelector('input[name="durasi"]:checked').value);
        const [h, m] = jamMulai.split(':').map(Number);
        const total  = h * 60 + m + durasi * 60;
        const hh     = Math.floor(total / 60) % 24;
        const mm     = total % 60;
        const ampm   = hh >= 12 ? 'PM' : 'AM';
        const hDisp  = String(hh > 12 ? hh - 12 : (hh === 0 ? 12 : hh)).padStart(2, '0');
        const mDisp  = String(mm).padStart(2, '0');
        if (preview) preview.textContent = `${hDisp}:${mDisp} ${ampm}`;
    }

    radios.forEach(r => r.addEventListener('change', updateSelesai));

    // Prevent double submit
    const form = document.getElementById('booking-form');
    const btn  = document.getElementById('submit-btn');
    if (form) {
        form.addEventListener('submit', () => {
            if (btn) { btn.disabled = true; btn.textContent = 'Memproses...'; }
        });
    }
</script>
</body>
</html>