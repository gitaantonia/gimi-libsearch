<?php
session_start();
include '../regis/koneksi.php';

$id_fasilitas = $_GET['id'] ?? '';

if (empty($id_fasilitas)) {
    die("<div style='padding:40px; text-align:center; font-family:sans-serif;'>ID Fasilitas tidak ditemukan.</div>");
}

$db = isset($conn) ? $conn : $koneksi;

$query = mysqli_query($db, "SELECT * FROM fasilitas WHERE id = '$id_fasilitas'");
$data  = mysqli_fetch_assoc($query);

if (!$data) {
    die("<div style='padding:40px; text-align:center; font-family:sans-serif;'>Fasilitas tidak ditemukan.</div>");
}

$tanggal_hari_ini = date('Y-m-d');
$sql_booking = "SELECT b.*, u.nama 
                FROM bookings b 
                JOIN anggota u ON b.id_anggota = u.id_anggota 
                WHERE b.id_fasilitas = '$id_fasilitas' 
                AND b.tanggal = '$tanggal_hari_ini' 
                AND b.status_booking != 'cancelled'";
$res_booking = mysqli_query($db, $sql_booking);

$booked_slots = [];
while ($row = mysqli_fetch_assoc($res_booking)) {
    $key = substr($row['jam_mulai'], 0, 5);
    $booked_slots[$key] = $row['nama'];
}

// Cek apakah jam sekarang tersedia
$jam_sekarang   = date('H:00');
$is_now_booked  = isset($booked_slots[$jam_sekarang]);

// Cari slot kosong berikutnya
$next_free = null;
for ($h = (int)date('H'); $h <= 18; $h++) {
    $t = str_pad($h, 2, "0", STR_PAD_LEFT) . ":00";
    if (!isset($booked_slots[$t])) {
        $next_free = $t;
        break;
    }
}
function getCategoryIcon($kategori)
{
    if ($kategori === 'ruang_komputer') {
        // Icon Layar/Komputer
        return '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>';
    } elseif ($kategori === 'ruang_diskusi') {
        // Icon Users/Group
        return '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    } else {
        // Icon Buku (Default/Meja Baca)
        return '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';
    }
}

// Fungsi format teks (ruang_diskusi -> Ruang Diskusi)
function formatCategoryText($teks)
{
    return ucwords(str_replace('_', ' ', $teks));
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data['nama_fasilitas']) ?> — Detail</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
   
    <link rel="stylesheet" href="css/bookfas.css">
</head>

<body>
    <div class="page-wrapper">

        <!-- NAVBAR -->
        <nav class="navbar">
            <div class="nav-logo"><img src="aset/img/logo.png" alt="GiMi Logo"></div>
            <ul class="nav-links">
                <li><a href="dashboard.php">Home</a></li>
                <li><a href="books.php">Books</a></li>
                <li><a href="facilities.php" class="active">Facilities</a></li>
                <li><a href="reports.php">Reports</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </nav>

        <div class="content-grid">

            <!-- ===== LEFT COLUMN ===== -->
            <div>
                <!-- Judul & Meta -->
                <h1 class="page-title"><?= htmlspecialchars($data['nama_fasilitas']) ?></h1>
                <div class="meta-row">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg>
                    <?= htmlspecialchars($data['lokasi']) ?> &bull; Seat <?= (int)$data['kapasitas'] ?>
                </div>

                <!-- Gambar Fasilitas -->
                <div class="facility-img-wrap">
                    <img
                        src="../admin/upload/<?= htmlspecialchars($data['gambar']) ?>"
                        alt="<?= htmlspecialchars($data['nama_fasilitas']) ?>"
                        onerror="this.onerror=null; this.src='../aset/img/default.jpg';">
                    <div class="img-tags">
                        <span class="img-tag">
                            <?= getCategoryIcon($data['kategori']) ?>
                            <?= formatCategoryText($data['kategori']) ?>
                        </span>

                        <span class="img-tag" style="background: rgba(0,0,0,0.5);">
                            <i class="bi bi-info-circle" style="font-size: 10px;"></i>
                            Official Facility
                        </span>
                    </div>
                </div>

                <!-- Deskripsi dari DB -->
                <?php if (!empty($data['deskripsi'])): ?>
                    <div class="description-card">
                        <h3>About this space</h3>
                        <p><?= nl2br(htmlspecialchars($data['deskripsi'])) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Status Available Now -->
                <div class="status-banner">
                    <div class="status-icon">
                        <svg width="26" height="26" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div class="status-text">
                        <h4>Available Now</h4>
                        <?php if ($next_free): ?>
                            <p class="free-time">⏱ Free until <?= $next_free ?></p>
                        <?php endif; ?>
                        <p>The room is currently empty and ready for use.</p>
                    </div>
                    <a href="booking.php?id=<?= $id_fasilitas ?>&tgl=<?= $tanggal_hari_ini ?>" class="btn-book-instantly">
                        Book Instantly
                        <svg width="16" height="16" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
                            <line x1="5" y1="12" x2="19" y2="12" />
                            <polyline points="12 5 19 12 12 19" />
                        </svg>
                    </a>
                </div>

                <!-- Info Cards -->
                <div class="info-row">
                    <div class="info-card">
                        <div class="info-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                        </div>
                        <div class="info-card-text">
                            <span>Max Duration</span>
                            <small>Bookings are limited to 2 hours per session per user.</small>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <line x1="1" y1="1" x2="23" y2="23" />
                                <path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6" />
                                <path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23" />
                                <line x1="12" y1="19" x2="12" y2="23" />
                                <line x1="8" y1="23" x2="16" y2="23" />
                            </svg>
                        </div>
                        <div class="info-card-text">
                            <span>Silence Required</span>
                            <small>Please keep noise to a minimum. Use headphones.</small>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                                <line x1="6" y1="1" x2="6" y2="4" />
                                <line x1="10" y1="1" x2="10" y2="4" />
                                <line x1="14" y1="1" x2="14" y2="4" />
                            </svg>
                        </div>
                        <div class="info-card-text">
                            <span>No Food</span>
                            <small>Only bottled water is allowed inside the room.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== RIGHT COLUMN — Schedule ===== -->
            <div id="schedule">
                <div class="schedule-panel">
                    <div class="schedule-header">
                        <h5>Schedule</h5>
                        <div class="date-pill"><?= date('D, M d') ?></div>
                    </div>

                    <div class="schedule-scroll">
                        <?php
                        $jam_int_sekarang = (int)date('H');
                        for ($h = 9; $h <= 18; $h++):
                            $time_key  = str_pad($h, 2, "0", STR_PAD_LEFT) . ":00";
                            $time_disp = date('h:i A', strtotime($time_key));
                            $is_past   = $h < $jam_int_sekarang;
                            $is_booked = isset($booked_slots[$time_key]);
                            $is_now    = $h === $jam_int_sekarang;

                            // Tandai slot milik user yg login (jika ada session)
                            $my_id     = $_SESSION['id_anggota'] ?? null;
                        ?>
                            <div class="time-row">
                                <div class="time-label <?= $is_now ? 'current' : '' ?>"><?= $time_disp ?></div>

                                <?php if ($is_past): ?>
                                    <div class="slot slot-past">
                                        <div>
                                            <span class="slot-title text-muted">Past</span>
                                        </div>
                                    </div>

                                <?php elseif ($is_booked): ?>
                                    <div class="slot slot-occupied">
                                        <div>
                                            <span class="slot-title text-red">Occupied</span>
                                            <span class="slot-sub text-red">Booked by <?= htmlspecialchars($booked_slots[$time_key]) ?></span>
                                        </div>
                                        <svg width="15" height="15" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                        </svg>
                                    </div>

                                <?php elseif ($is_now): ?>
                                    <a href="booking.php?id=<?= $id_fasilitas ?>&time=<?= $time_key ?>&tgl=<?= $tanggal_hari_ini ?>" class="slot slot-now">
                                        <div>
                                            <span class="slot-title text-green">Available Now</span>
                                            <span class="slot-sub text-green">Tap to book this slot immediately.</span>
                                        </div>
                                        <svg width="15" height="15" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
                                            <polyline points="9 18 15 12 9 6" />
                                        </svg>
                                    </a>

                                <?php else: ?>
                                    <a href="booking.php?id=<?= $id_fasilitas ?>&time=<?= $time_key ?>&tgl=<?= $tanggal_hari_ini ?>" class="slot slot-available">
                                        <div>
                                            <span class="slot-title" style="color:#111827">Available</span>
                                            <span class="slot-sub text-muted">1h 00m duration</span>
                                        </div>
                                        <svg width="15" height="15" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="12" y1="8" x2="12" y2="16" />
                                            <line x1="8" y1="12" x2="16" y2="12" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Legend -->
                    <div class="schedule-legend">
                        <span><span class="legend-dot" style="background:var(--green)"></span>Available</span>
                        <span><span class="legend-dot" style="background:var(--red)"></span>Occupied</span>
                        <span><span class="legend-dot" style="background:var(--yours)"></span>Yours</span>
                    </div>

                    <button class="btn-select-slot">Select a slot to book</button>
                </div>
            </div>

        </div><!-- .content-grid -->
    </div><!-- .page-wrapper -->
</body>

</html>