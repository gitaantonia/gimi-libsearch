<?php
session_start();
//session_start();
include '../../regis/koneksi.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'pustakawan' && $_SESSION['role'] != 'admin')) {
    header("Location: ../../regis/login.php");
    exit;
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_peminjaman = $_POST['id_peminjaman'] ?? '';
    $jenis_masalah = $_POST['jenis_masalah'] ?? '';
    $hari_telat = (int)($_POST['hari_telat'] ?? 0);

    if (!empty($id_peminjaman) && in_array($jenis_masalah, ['Terlambat', 'Rusak', 'Hilang'])) {
        $jumlah_denda = 0;
        $loan_status_update = '';
        $update_return_date = true;

        if ($jenis_masalah === 'Terlambat') {
            $jumlah_denda = $hari_telat * 2000;
            $loan_status_update = 'dikembalikan'; // Returned, but late
        } elseif ($jenis_masalah === 'Rusak') {
            $jumlah_denda = 50000; // Fixed fine for damaged
            $loan_status_update = 'rusak';
        } elseif ($jenis_masalah === 'Hilang') {
            $jumlah_denda = 100000; // Fixed fine for lost
            $loan_status_update = 'hilang';
            $update_return_date = false; // Never returned
        }

        // Prevent duplicate fines for same type
        $chk = $conn->prepare("SELECT id_denda FROM denda WHERE id_peminjaman = ? AND jenis_denda = ?");
        $chk->bind_param("is", $id_peminjaman, $jenis_masalah);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = 'A fine of this type already exists for this loan.';
            $msgType = 'error';
        } else {
            // Insert new fine
            $stmt = $conn->prepare("INSERT INTO denda (id_peminjaman, jenis_denda, jumlah_denda, status) VALUES (?, ?, ?, 'Belum Dibayar')");
            $stmt->bind_param("isd", $id_peminjaman, $jenis_masalah, $jumlah_denda);
            $stmt->execute();

            // Update loan status
            if ($update_return_date) {
                // Determine if we should set tanggal_dikembalikan to today if it is null
                // We will just do it straightforwardly:
                $upd = $conn->prepare("UPDATE peminjaman SET status = ?, tanggal_dikembalikan = COALESCE(tanggal_dikembalikan, CURRENT_DATE()) WHERE id_peminjaman = ?");
                $upd->bind_param("si", $loan_status_update, $id_peminjaman);
            } else {
                $upd = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id_peminjaman = ?");
                $upd->bind_param("si", $loan_status_update, $id_peminjaman);
            }
            $upd->execute();

            // Reduce stock if lost
            if ($jenis_masalah === 'Hilang') {
                $q = $conn->prepare("SELECT id_buku FROM peminjaman WHERE id_peminjaman = ?");
                $q->bind_param("i", $id_peminjaman);
                $q->execute();
                $res = $q->get_result();
                if ($row = $res->fetch_assoc()) {
                    $id_buku = $row['id_buku'];
                    $upd_stok = $conn->prepare("UPDATE buku SET stok = stok - 1 WHERE id_buku = ? AND stok > 0");
                    $upd_stok->bind_param("s", $id_buku);
                    $upd_stok->execute();
                }
            }
            $msg = 'Problem recorded and fine generated successfully.';
            $msgType = 'success';
        }
    } else {
        $msg = 'Invalid input data.';
        $msgType = 'error';
    }
}

// Fetch active loans (dipinjam)
$search = $_GET['search'] ?? '';
$whereClause = "r.status = 'dipinjam'";
if ($search) {
    $searchEscaped = "%" . $conn->real_escape_string($search) . "%";
    $whereClause .= " AND (u.nama LIKE '$searchEscaped' OR b.judul LIKE '$searchEscaped')";
}

$query = "
SELECT 
    r.id_peminjaman,
    r.tgl_pinjam,
    r.tgl_kembali,
    r.status AS loan_status,
    DATEDIFF(CURRENT_DATE(), r.tgl_kembali) AS days_late,
    a.id_anggota,
    a.nama,
    b.id_buku,
    b.judul,
    b.stok
FROM peminjaman r
JOIN anggota a ON r.id_anggota = a.id_anggota
JOIN pengguna p ON a.id_pengguna = p.id_pengguna
LEFT JOIN buku b ON r.id_buku = b.id_buku
WHERE $whereClause
ORDER BY r.tgl_pinjam DESC
";
$result = mysqli_query($conn, $query);

// Total active loans and late loans
$totalActive = 0;
$totalLate = 0;
$qStats = mysqli_query($conn, "SELECT COUNT(*) as tot, SUM(CASE WHEN DATEDIFF(CURRENT_DATE(), tgl_kembali) > 0 THEN 1 ELSE 0 END) as late FROM peminjaman WHERE status = 'dipinjam'");
if ($qStats && $r = mysqli_fetch_assoc($qStats)) {
    $totalActive = $r['tot'] ?? 0;
    $totalLate = $r['late'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Problem Handling - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0b0d10;
            color: #e2e8f0;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #2d3139;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }

        .sidebar-item {
            color: #94a3b8;
            transition: all 0.2s;
        }

        .sidebar-item:hover {
            color: #e2e8f0;
            background-color: #1a1d24;
        }

        .sidebar-item.active {
            background-color: rgba(30, 58, 138, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .icon {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .card-bg {
            background-color: #15181e;
            border: 1px solid #2d3139;
        }
    </style>
    <script>
        function openModal(id, daysLate) {
            document.getElementById('modal_id_peminjaman').value = id;
            document.getElementById('hari_telat_container').style.display = 'none';

            let select = document.getElementById('jenis_masalah');
            select.value = '';
            document.getElementById('fine_preview').innerText = 'Rp 0';

            // If late organically, you can prefill or suggest 'Late' type
            if (daysLate > 0) {
                document.getElementById('default_hari').value = daysLate;
            } else {
                document.getElementById('default_hari').value = 0;
            }

            document.getElementById('problem_modal').classList.remove('hidden');
            document.getElementById('problem_modal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('problem_modal').classList.add('hidden');
            document.getElementById('problem_modal').classList.remove('flex');
        }

        function calculateFine() {
            let type = document.getElementById('jenis_masalah').value;
            let fine = 0;
            let c_hari = document.getElementById('hari_telat_container');

            if (type === 'Terlambat') {
                c_hari.style.display = 'block';
                let dr = parseInt(document.getElementById('hari_telat').value) || parseInt(document.getElementById('default_hari').value) || 0;
                if (document.getElementById('hari_telat').value === "") document.getElementById('hari_telat').value = dr;
                fine = dr * 2000;
            } else {
                c_hari.style.display = 'none';
                if (type === 'Rusak') {
                    fine = 50000;
                }
                if (type === 'Hilang') {
                    fine = 100000;
                }
            }
            document.getElementById('fine_preview').innerText = 'Rp ' + fine.toLocaleString('id-ID');
        }
    </script>
</head>

<body class="flex h-screen overflow-hidden text-sm">

    <!-- Sidebar -->
    <aside class="w-[260px] flex flex-col border-r border-[#2d3139] bg-[#0b0d10] shrink-0 z-20">
        <div class="px-6 py-8">
            <h1 class="text-xl font-bold tracking-wide text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20" />
                </svg>
                GiMi LibSearch
            </h1>
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider mt-2">Librarian Portal</p>
        </div>

        <nav class="flex-1 px-4 space-y-1 overflow-y-auto">
            <a href="dashboard_adm.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
                <svg class="icon" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="fasilitas.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M3 21h18"></path>
                    <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
                    <path d="M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"></path>
                </svg>
                <span class="font-medium">Facility</span>
            </a>
            <a href="book_catalogue.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>
                </svg>
                <span class="font-medium">Book Catalogue</span>
            </a>
            <a href="book_requests.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span class="font-medium">Book Requests</span>
            </a>
            <a href="fasilitas_requests.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span class="font-medium">Facility Requests</span>
            </a>

            <div class="pt-4 pb-2">
                <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fines & Reports</p>
            </div>

            <a href="problem_handling.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span class="font-medium">Problem Handling</span>
            </a>

            <a href="handling_fines.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span class="font-medium">Handling Fines</span>
            </a>
        </nav>
        <div class="p-4 border-t border-[#2d3139]">
            <div class="flex items-center gap-3 px-2 py-2">
                <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold shrink-0">
                    <?php echo strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="truncate">
                    <p class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?></p>
                    <p class="text-xs text-gray-500 truncate">Librarian</p>
                </div>
            </div>
            <a href="../../regis/logout.php" class="mt-2 flex items-center gap-3 px-3 py-2 rounded-lg text-red-400 hover:bg-[#1a1d24] transition-colors cursor-pointer">
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span class="font-medium">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main area -->
    <main class="flex-1 flex flex-col overflow-y-auto bg-[#0b0d10]">
        <!-- Header -->
        <header class="px-8 py-6 border-b border-[#2d3139] flex justify-between items-end flex-wrap gap-4">
            <div>
                <div class="text-xs text-gray-400 mb-1 font-medium tracking-wide">MANAGEMENT</div>
                <h2 class="text-2xl font-semibold text-white tracking-tight">Problem Handling</h2>
                <p class="text-sm text-gray-500 mt-1">Report late returns, damaged, or lost books.</p>
            </div>
            <div class="flex gap-4">
                <div class="flex items-center gap-4 card-bg px-5 py-3 rounded-lg border-blue-900/40">
                    <div class="bg-blue-500/10 p-2 rounded-lg text-blue-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Active Loans</p>
                        <h3 class="text-xl font-bold text-white mt-0.5"><?php echo $totalActive; ?></h3>
                    </div>
                </div>

                <div class="flex items-center gap-4 card-bg px-5 py-3 rounded-lg border-yellow-900/40">
                    <div class="bg-yellow-500/10 p-2 rounded-lg text-yellow-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Currently Late</p>
                        <h3 class="text-xl font-bold text-yellow-500 mt-0.5"><?php echo $totalLate; ?></h3>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-8 space-y-6 max-w-[1400px]">

            <?php if ($msg): ?>
                <div class="px-4 py-3 rounded-xl flex items-center gap-3 border <?php echo $msgType === 'success' ? 'bg-green-500/10 border-green-500/20 text-green-400' : 'bg-red-500/10 border-red-500/20 text-red-400'; ?>">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <?php if ($msgType === 'success'): ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        <?php else: ?>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        <?php endif; ?>
                    </svg>
                    <span><?php echo htmlspecialchars($msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Toolbar -->
            <div class="card-bg p-4 rounded-xl flex flex-col md:flex-row justify-between gap-4">
                <form method="GET" class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                    <div class="relative w-full md:w-80">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search member or book..." class="w-full bg-[#0b0d10] border border-[#2d3139] text-gray-300 rounded-lg pl-10 pr-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all placeholder-gray-600 text-sm">
                        <svg class="w-4 h-4 text-gray-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <button type="submit" class="bg-[#1a1d24] hover:bg-[#2d3139] text-gray-300 px-4 py-2.5 rounded-lg border border-[#2d3139] transition-colors text-sm font-medium">Search Active</button>
                </form>
            </div>

            <!-- Table -->
            <div class="card-bg rounded-xl overflow-hidden border border-[#2d3139] shadow-lg shadow-black/20">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr class="bg-[#1a1d24]/50 border-b border-[#2d3139]">
                                <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Loan Info</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">User</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Condition</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#2d3139]/80">
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)):
                                    $daysLate = (int)$row['days_late'];
                                    $isLate = $daysLate > 0;
                                ?>
                                    <tr class="hover:bg-[#15181e] transition-colors group">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-lg bg-[#2d3139] flex items-center justify-center shrink-0 text-gray-400 group-hover:text-blue-400 transition-colors">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-200"><?php echo htmlspecialchars($row['judul']); ?></p>
                                                    <p class="text-[10px] text-gray-600 font-mono mt-0.5">ID: #<?php echo $row['id_peminjaman']; ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-blue-900/50 flex flex-shrink-0 items-center justify-center text-[10px] font-bold text-blue-400">
                                                    <?php echo strtoupper(substr($row['nama'], 0, 1)); ?>
                                                </div>
                                                <p class="text-sm text-gray-300"><?php echo htmlspecialchars($row['nama']); ?></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1 text-xs">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-gray-500 w-10">From:</span>
                                                    <span class="text-gray-400"><?php echo date('d M Y', strtotime($row['tgl_pinjam'])); ?></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-gray-500 w-10">To:</span>
                                                    <span class="text-gray-300 font-medium"><?php echo date('d M Y', strtotime($row['tgl_kembali'])); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($isLate): ?>
                                                <span class="px-2.5 py-1 text-[11px] font-semibold rounded-full border bg-yellow-500/10 text-yellow-400 border-yellow-500/20 inline-flex items-center gap-1.5 uppercase tracking-wider">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    Late (<?php echo $daysLate; ?> Days)
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 text-[11px] font-semibold rounded-full border bg-blue-500/10 text-blue-400 border-blue-500/20 inline-block uppercase tracking-wider">
                                                    Active
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button onclick="openModal(<?php echo $row['id_peminjaman']; ?>, <?php echo $daysLate > 0 ? $daysLate : 0; ?>)" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/20 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 mx-auto">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                </svg>
                                                Log Problem
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                            </svg>
                                            <p>No active loans found matching criteria.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <div class="p-4 border-t border-[#2d3139] bg-[#1a1d24]/30 text-xs text-gray-500 flex justify-between items-center">
                        <span>Showing <?php echo mysqli_num_rows($result); ?> records</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="problem_modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-[#15181e] border border-[#2d3139] rounded-2xl w-full max-w-md shadow-2xl p-6 relative">
            <button type="button" onclick="closeModal()" class="absolute top-4 right-4 text-gray-500 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            <div class="flex items-center gap-3 mb-6">
                <div class="bg-red-500/10 p-2.5 rounded-xl text-red-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Log Problem</h3>
                    <p class="text-xs text-gray-400">Generate fine for loan return issues</p>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="id_peminjaman" id="modal_id_peminjaman" value="">
                <input type="hidden" id="default_hari" value="0">

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1">Problem Type</label>
                        <select name="jenis_masalah" id="jenis_masalah" onchange="calculateFine()" required class="w-full bg-[#0b0d10] border border-[#2d3139] text-gray-300 rounded-lg px-4 py-2.5 outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 text-sm appearance-none">
                            <option value="" disabled selected>Select problem...</option>
                            <option value="Terlambat">Late Return (Terlambat)</option>
                            <option value="Rusak">Damaged Book (Rusak)</option>
                            <option value="Hilang">Lost Book (Hilang)</option>
                        </select>
                    </div>

                    <div id="hari_telat_container" style="display:none;">
                        <label class="block text-xs font-medium text-gray-400 mb-1">Days Late (For Late Fine)</label>
                        <input type="number" name="hari_telat" id="hari_telat" min="1" onkeyup="calculateFine()" onchange="calculateFine()" class="w-full bg-[#0b0d10] border border-[#2d3139] text-gray-300 rounded-lg px-4 py-2.5 outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 text-sm">
                    </div>

                    <div class="bg-black/20 p-4 rounded-xl border border-[#2d3139] mt-6">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wider mb-1">Calculated Fine (Denda)</p>
                        <h4 id="fine_preview" class="text-2xl font-bold text-red-500 mt-1">Rp 0</h4>
                        <p class="text-[11px] text-gray-500 mt-1">* This fine will be logged as Unpaid.</p>
                    </div>
                </div>

                <div class="mt-8 flex gap-3">
                    <button type="button" onclick="closeModal()" class="flex-1 bg-[#1a1d24] hover:bg-[#2d3139] text-gray-300 py-2.5 rounded-lg border border-[#2d3139] transition-colors text-sm font-semibold">Cancel</button>
                    <button type="submit" class="flex-1 bg-red-600 hover:bg-red-500 text-white py-2.5 rounded-lg transition-colors text-sm font-semibold shadow-lg shadow-red-600/20">Submit Fine</button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>