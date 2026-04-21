<?php
require "../regis/koneksi.php";

if (isset($_GET['action']) && $_GET['action'] == 'fetch') {
    $kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'all';
    $lokasi   = isset($_GET['lokasi'])   ? $_GET['lokasi']   : '';

    $conditions = [];
    if ($kategori !== 'all') {
        $cat = mysqli_real_escape_string($conn, $kategori);
        $conditions[] = "kategori = '$cat'";
    }
    if ($lokasi !== '') {
        $loc = mysqli_real_escape_string($conn, $lokasi);
        $conditions[] = "lokasi = '$loc'";
    }

    $sql = "SELECT * FROM fasilitas";
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $result = mysqli_query($conn, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/facilities.css">
    <title>Featured Facilities</title>
</head>

<body>
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
    <div class="header-section"></div>

    <div id="target-navigasi" class="floor-navigation">
        <button class="floor-btn active" data-lokasi="Lantai 1" onclick="setLokasi('Lantai 1', this)">
            <span>Lantai</span><strong>1</strong>
        </button>
        <button class="floor-btn" data-lokasi="Lantai 2" onclick="setLokasi('Lantai 2', this)">
            <span>Lantai</span><strong>2</strong>
        </button>
        <button class="floor-btn" data-lokasi="Lantai 3" onclick="setLokasi('Lantai 3', this)">
            <span>Lantai</span><strong>3</strong>
        </button>
    </div>

    <div class="booking-title">
        <h2>Booking Yours Now</h2>
        <div class="library-status">● Library Open &bull; Closes at 9:00 PM</div>
    </div>

    <div class="filter-container">
        <button class="filter-btn active" data-kategori="ruang_diskusi" onclick="setKategori('ruang_diskusi', this)">Collaborative</button>
        <button class="filter-btn" data-kategori="meja_baca" onclick="setKategori('meja_baca', this)">Quiet Zones</button>
        <button class="filter-btn" data-kategori="ruang_komputer" onclick="setKategori('ruang_komputer', this)">Tech Labs</button>
    </div>

    <div id="facilities-container" class="facilities-grid"></div>

    <script>
        let activeKategori = 'ruang_diskusi';
        let activeLokasi = 'Lantai 1';

        // Fungsi baru untuk scroll ke navigasi lantai
        function pindahKeLantai() {
            const target = document.getElementById('target-navigasi');
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        }

        function setKategori(kategori, el) {
            activeKategori = kategori;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            el.classList.add('active');
            fetchData();
        }

        function setLokasi(lokasi, el) {
            activeLokasi = lokasi;
            document.querySelectorAll('.floor-btn').forEach(b => b.classList.remove('active'));
            el.classList.add('active');
            fetchData();
        }

        async function fetchData() {
            const container = document.getElementById('facilities-container');
            container.innerHTML = '<div class="loading-state">Memuat data...</div>';

            try {
                // Pastikan file ini namanya facilities.php atau sesuaikan dengan nama file Anda
                const url = `facilities.php?action=fetch&kategori=${encodeURIComponent(activeKategori)}&lokasi=${encodeURIComponent(activeLokasi)}`;
                const response = await fetch(url);
                const data = await response.json();
                renderCards(data);
            } catch (err) {
                console.error('Error:', err);
                container.innerHTML = '<div class="loading-state">Gagal memuat data.</div>';
            }
        }

        function renderCards(data) {
            const container = document.getElementById('facilities-container');
            container.innerHTML = '';

            if (data.length === 0) {
                container.innerHTML = '<div class="empty-state">Tidak ada fasilitas ditemukan.</div>';
            }

            data.forEach(item => {
                const statusLabel = getStatusLabel(item.status);
                const lokasiLabel = getLantaiLabel(item.lokasi);
                const id = item.id; // Pastikan 'id' ini dieja sesuai kolom di database
                // Sesuaikan logika isAvailable dengan status di Gambar 1 (misal 'dikembalikan' atau 'tersedia')
                const isAvailable = (item.status === 'tersedia' || item.status === 'dikembalikan');

                const card = `
                <div class="card">
                    <div class="img-container">
                        <img src="../admin/upload/${item.gambar || 'default.jpg'}" alt="${item.nama_fasilitas}"
                             onerror="this.src='img/default.jpg'">
                        <div class="img-overlay">
                            <span class="badge-level">${lokasiLabel}</span>
                            <span class="badge-status status-${item.status}">${statusLabel}</span>
                        </div>
                        <div class="img-title">
                            <h3>${item.nama_fasilitas}</h3>
                        </div>
                    </div>
                    <div class="content">
                        <div class="info">
                            <span class="info-item">👥 Kapasitas: ${item.kapasitas} Orang</span>
                        </div>
                        <p class="desc">${item.deskripsi || ''}</p>
<div class="card-footer">
    <button 
        class="btn-book ${isAvailable ? '' : 'disabled'}" 
        ${isAvailable ? '' : 'disabled'}
        onclick="${isAvailable ? `window.location.href='bookfas.php?id=${id}'` : ''}">
        
        ${isAvailable ? 'Book Now' : 'Unavailable'}
        
    </button>
</div>
                    </div>
                </div>`;
                container.innerHTML += card;
            });

            // Tambahkan "Explore All" card dengan ONCLICK pindahKeLantai
            container.innerHTML += `
            <div class="card card-explore" onclick="pindahKeLantai()" style="cursor: pointer;">
                <div class="explore-inner">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#b0b8c9" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <h4>Explore All Facilities</h4>
                    <p>Browse by floor</p>
                </div>
            </div>`;
        }

        function getStatusLabel(status) {
            const map = {
                tersedia: '✦ Available',
                dikembalikan: '✦ Available',
                dipinjam: 'Borrowed',
                pending: 'Pending',
                terlambat: 'Overdue'
            };
            return map[status] || status;
        }

        function getLantaiLabel(lokasi) {
            return lokasi ? lokasi.toUpperCase() : '';
        }

        window.onload = () => {
            fetchData();
        };
    </script>
</body>

</html>