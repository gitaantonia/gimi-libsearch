<?php
session_start();
include '../../regis/koneksi.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'pustakawan' && $_SESSION['role'] != 'admin')) {
    header("Location: ../../regis/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_denda']) && isset($_POST['status_bayar'])) {
    $id_denda = $_POST['id_denda'];
    $status_bayar = $_POST['status_bayar'];
    if (!empty($id_denda)) {
        $stmt = $conn->prepare("UPDATE denda SET status_bayar = ? WHERE id_denda = ?");
        $stmt->bind_param("ss", $status_bayar, $id_denda);
        $stmt->execute();
        header("Location: handling_fines.php?msg=success");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_fine'])) {
    $id_denda = $_POST['id_denda'];
    $jumlah_denda = (int)$_POST['jumlah_denda'];
    if (!empty($id_denda)) {
        $stmt = $conn->prepare("UPDATE denda SET jumlah_denda = ?, status_bayar = 'belum_bayar' WHERE id_denda = ?");
        $stmt->bind_param("is", $jumlah_denda, $id_denda);
        $stmt->execute();
        header("Location: handling_fines.php?msg=success");
        exit;
    }
}

// Auto-generate late fines on page load with 0 amount (pending)
$qLate = "SELECT p.id_peminjaman, DATEDIFF(p.tgl_kembali, p.tgl_jatuh_tempo) as days_late 
          FROM peminjaman p 
          LEFT JOIN denda d ON p.id_peminjaman = d.id_peminjaman AND d.jenis = 'keterlambatan'
          WHERE p.tgl_jatuh_tempo IS NOT NULL 
          AND p.tgl_kembali > p.tgl_jatuh_tempo 
          AND d.id_denda IS NULL";
$resLate = mysqli_query($conn, $qLate);
if ($resLate) {
    while ($row = mysqli_fetch_assoc($resLate)) {
        $id_p = $row['id_peminjaman'];
        $stmt = $conn->prepare("INSERT IGNORE INTO denda (id_peminjaman, jenis, jumlah_denda, status_bayar) VALUES (?, 'keterlambatan', 0, 'pending')");
        $stmt->bind_param("s", $id_p);
        $stmt->execute();
    }
}

// Search and Filter handling
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'All';

if ($search) {
    $searchEscaped = "%".$conn->real_escape_string($search)."%";
    $whereClause = " AND (a.nama LIKE '$searchEscaped' OR a2.nama LIKE '$searchEscaped' OR b.judul LIKE '$searchEscaped')";
} else {
    $whereClause = "";
}

$filterCond = "";
if ($filter === 'Unpaid') {
    $filterCond = " AND d.status_bayar = 'belum_bayar'";
} elseif ($filter === 'Pending') {
    $filterCond = " AND d.status_bayar = 'pending'";
} elseif ($filter === 'Paid') {
    $filterCond = " AND d.status_bayar = 'lunas'";
} elseif ($filter === 'Lainnya') {
    $filterCond = " AND d.status_bayar = 'lainnya'";
} elseif ($filter === 'With Fines') {
    $filterCond = " AND d.id_denda IS NOT NULL";
}

$query = "
    SELECT 
        d.id_denda, 
        d.jenis, 
        d.jumlah_denda, 
        d.status_bayar AS status_denda,
        p.id_peminjaman, 
        COALESCE(b.judul, l.terkait_item, '-') AS judul,
        p.tgl_jatuh_tempo AS return_deadline, 
        p.tgl_kembali AS return_date, 
        DATEDIFF(p.tgl_kembali, p.tgl_jatuh_tempo) AS days_late,
        p.status AS loan_status,
        l.id_laporan,
        l.tipe_laporan,
        l.terkait_item,
        l.tgl_kejadian,
        COALESCE(a.nama, a2.nama, '-') AS nama
    FROM denda d
    LEFT JOIN peminjaman p ON d.id_peminjaman = p.id_peminjaman
    LEFT JOIN anggota a ON p.id_anggota = a.id_anggota
    LEFT JOIN buku b ON p.id_buku = b.id_buku
    LEFT JOIN laporan l ON d.id_laporan = l.id_laporan
    LEFT JOIN anggota a2 ON l.id_anggota = a2.id_anggota
    WHERE 1=1 $whereClause $filterCond
    ORDER BY CASE WHEN d.status_bayar = 'pending' THEN 0 ELSE 1 END, d.id_denda DESC
";
$result = mysqli_query($conn, $query);

// Total outstanding fines
$totalFines = 0;
$qTotal = mysqli_query($conn, "SELECT SUM(jumlah_denda) as total_denda FROM denda WHERE status_bayar IN ('belum_bayar', 'pending')");
if ($qTotal && $rt = mysqli_fetch_assoc($qTotal)) {
    $totalFines = $rt['total_denda'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Handling Fines - Admin Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #0b0d10;
        color: #e2e8f0;
    }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #2d3139; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #4b5563; }
    .sidebar-item { color: #94a3b8; transition: all 0.2s; }
    .sidebar-item:hover { color: #e2e8f0; background-color: #1a1d24; }
    .sidebar-item.active { background-color: rgba(30, 58, 138, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
    .icon { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
    .card-bg { background-color: #15181e; border: 1px solid #2d3139; }
</style>
</head>
<body class="flex h-screen overflow-hidden text-sm">

<!-- Sidebar -->
<aside class="w-[260px] flex flex-col border-r border-[#2d3139] bg-[#0b0d10] shrink-0 z-20">
    <div class="px-6 py-8">
        <h1 class="text-xl font-bold tracking-wide text-white flex items-center gap-2">
            <svg class="w-6 h-6 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
            GiMi LibSearch
        </h1>
        <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider mt-2">Librarian Portal</p>
    </div>

    <nav class="flex-1 px-4 space-y-1 overflow-y-auto">
        <a href="dashboard_adm.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            <span class="font-medium">Dashboard</span>
        </a>
        <a href="fasilitas.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M3 21h18"></path><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path><path d="M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"></path></svg>
            <span class="font-medium">Facility</span>
        </a>
        <a href="book_catalogue.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>
            <span class="font-medium">Book Catalogue</span>
        </a>
        <a href="book_requests.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <span class="font-medium">Book Requests</span>
        </a>
        <a href="fasilitas_requests.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span class="font-medium">Facility Requests</span>
        </a>

        <div class="pt-4 pb-2">
            <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fines & Reports</p>
        </div>

        <a href="problem_handling.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <span class="font-medium">Problem Handling</span>
        </a>

        <a href="handling_fines.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
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
            <svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
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
            <h2 class="text-2xl font-semibold text-white tracking-tight">Handling Fines</h2>
            <p class="text-sm text-gray-500 mt-1">Manage and track outstanding fines payments.</p>
        </div>
    </header>

    <!-- Content -->
    <div class="p-8 space-y-6 max-w-[1400px]">

        <?php if(isset($_GET['msg'])): ?>
            <?php if($_GET['msg'] == 'success' || $_GET['msg'] == 'paid'): ?>
                <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Action completed successfully.</span>
                </div>
            <?php elseif($_GET['msg'] == 'duplicate'): ?>
                <div class="bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 px-4 py-3 rounded-xl flex items-center gap-3">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>Fine already exists for this loan.</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="card-bg p-4 rounded-xl flex flex-col md:flex-row justify-between gap-4">
            <form method="GET" class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                <div class="relative w-full md:w-72">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search member or book..." class="w-full bg-[#0b0d10] border border-[#2d3139] text-gray-300 rounded-lg pl-10 pr-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all placeholder-gray-600 text-sm">
                    <svg class="w-4 h-4 text-gray-500 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <div class="flex gap-2">
                    <select name="filter" class="bg-[#0b0d10] border border-[#2d3139] text-gray-300 text-sm rounded-lg pl-3 pr-8 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none appearance-none cursor-pointer">
                        <option value="All" <?php if($filter=='All') echo 'selected'; ?>>All Loans & Fines</option>
                        <option value="With Fines" <?php if($filter=='With Fines') echo 'selected'; ?>>With Fines Only</option>
                        <option value="Unpaid" <?php if($filter=='Unpaid') echo 'selected'; ?>>Unpaid</option>
                        <option value="Pending" <?php if($filter=='Pending') echo 'selected'; ?>>Pending</option>
                        <option value="Paid" <?php if($filter=='Paid') echo 'selected'; ?>>Paid</option>
                        <option value="Lainnya" <?php if($filter=='Lainnya') echo 'selected'; ?>>Others</option>
                    </select>
                    <button type="submit" class="bg-[#1a1d24] hover:bg-[#2d3139] text-gray-300 px-4 py-2.5 rounded-lg border border-[#2d3139] transition-colors text-sm font-medium">Search</button>
                    <?php if($search || $filter !== 'All'): ?>
                        <a href="handling_fines.php" class="bg-red-500/10 text-red-400 hover:bg-red-500/20 px-4 py-2.5 rounded-lg border border-red-500/20 transition-colors text-sm font-medium text-center">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="card-bg rounded-xl overflow-hidden border border-[#2d3139] shadow-lg shadow-black/20">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#1a1d24]/50 border-b border-[#2d3139]">
                            <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Requests Info</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Dates</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Fine Details</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#2d3139]/80">
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)):
                                $isLate = ($row['return_date'] && $row['return_date'] > $row['return_deadline']) || (!$row['return_date'] && date('Y-m-d') > $row['return_deadline']);
                                $ls = strtolower($row['loan_status'] ?? '');

                                // Loan status badge
                                $badgeClass = "bg-gray-500/10 text-gray-400 border-gray-500/20";
                                $badgeLabel = ucfirst($ls);
                                if ($ls == 'hilang') { $badgeClass = "bg-red-500/10 text-red-400 border-red-500/20"; $badgeLabel = "Lost"; }
                                elseif ($ls == 'rusak') { $badgeClass = "bg-orange-500/10 text-orange-400 border-orange-500/20"; $badgeLabel = "Damaged"; }
                                elseif ($ls == 'dikembalikan' || $ls == 'kembali') { $badgeClass = "bg-green-500/10 text-green-400 border-green-500/20"; $badgeLabel = "Returned"; }
                                elseif ($ls == 'dipinjam' && $isLate) { $badgeClass = "bg-yellow-500/10 text-yellow-400 border-yellow-500/20"; $badgeLabel = "Overdue"; }
                                elseif ($ls == 'dipinjam') { $badgeClass = "bg-blue-500/10 text-blue-400 border-blue-500/20"; $badgeLabel = "Borrowed"; }

                                // Fine status badge
                                $sd = strtolower($row['status_denda'] ?? '');
                                $fineBadgeClass = "bg-gray-500/10 text-gray-400 border-gray-500/20";
                                $fineBadgeLabel = ucfirst($sd);
                                if ($sd == 'belum_bayar') { $fineBadgeClass = "bg-red-500/10 text-red-400 border-red-500/20"; $fineBadgeLabel = "Unpaid"; }
                                elseif ($sd == 'lunas') { $fineBadgeClass = "bg-green-500/10 text-green-400 border-green-500/20"; $fineBadgeLabel = "Paid"; }
                                elseif ($sd == 'pending') { $fineBadgeClass = "bg-yellow-500/10 text-yellow-400 border-yellow-500/20"; $fineBadgeLabel = "Pending"; }
                                elseif ($sd == 'lainnya') { $fineBadgeClass = "bg-purple-500/10 text-purple-400 border-purple-500/20"; $fineBadgeLabel = "Others"; }

                                $daysLate = (int)($row['days_late'] ?? 0);
                                $jumlahDenda = (int)($row['jumlah_denda'] ?? 0);
                            ?>
                            <tr class="hover:bg-[#1a1d24]/60 transition-colors group">

                                <!-- Requests Info -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-[#2d3139] flex items-center justify-center shrink-0 text-gray-400 group-hover:text-blue-400 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-200"><?php echo htmlspecialchars($row['judul'] ?? '-'); ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5">User: <span class="text-gray-400"><?php echo htmlspecialchars($row['nama'] ?? '-'); ?></span></p>
                                            <p class="text-[10px] text-gray-600 font-mono mt-0.5">Loan ID: #<?php echo htmlspecialchars($row['id_peminjaman'] ?? '-'); ?></p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Dates -->
                                <td class="px-6 py-4">
    <div class="flex flex-col gap-1 text-xs">
        <div class="flex items-center gap-2">
            <span class="text-gray-500 w-14">Deadline:</span>
            <span class="text-gray-300 font-medium">
                <?php echo $row['return_deadline'] ? date('d M Y', strtotime($row['return_deadline'])) : ($row['tgl_kejadian'] ? date('d M Y', strtotime($row['tgl_kejadian'])) : '-'); ?>
            </span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-gray-500 w-14">Returned:</span>
            <?php if($row['return_date']): ?>
                <span class="<?php echo $row['return_date'] > $row['return_deadline'] ? 'text-yellow-400 font-medium' : 'text-green-400 font-medium'; ?>">
                    <?php echo date('d M Y', strtotime($row['return_date'])); ?>
                </span>
            <?php else: ?>
                <span class="text-gray-600 italic">Not returned yet</span>
            <?php endif; ?>
        </div>
    </div>
</td>

                                <!-- Actions -->
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-2 items-center">
                                        <?php if($sd == 'pending' || $sd == 'belum_bayar'): ?>
                                            <!-- Calculate Fine Button -->
                                            <button type="button"
                                                onclick="openCalculateModal('<?php echo $row['id_denda']; ?>', '<?php echo addslashes($row['jenis']); ?>', <?php echo $daysLate; ?>)"
                                                class="w-full flex items-center justify-center gap-1.5 bg-blue-600/10 hover:bg-blue-600/20 text-blue-400 border border-blue-500/20 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                Calculate
                                            </button>
                                        <?php endif; ?>

                                        <?php if($sd == 'belum_bayar'): ?>
                                            <!-- Mark as Paid -->
                                            <form method="POST" onsubmit="return confirm('Mark this fine as paid?')">
                                                <input type="hidden" name="id_denda" value="<?php echo $row['id_denda']; ?>">
                                                <input type="hidden" name="status_bayar" value="lunas">
                                                <button type="submit" class="w-full flex items-center justify-center gap-1.5 bg-green-600/10 hover:bg-green-600/20 text-green-400 border border-green-500/20 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    Mark Paid
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if($sd == 'lunas'): ?>
                                            <span class="inline-flex items-center gap-1.5 text-green-400 text-xs font-medium">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Settled
                                            </span>
                                        <?php endif; ?>

                                        <?php if($sd != 'lunas'): ?>
                                            <!-- Mark as Others -->
                                            <form method="POST">
                                                <input type="hidden" name="id_denda" value="<?php echo $row['id_denda']; ?>">
                                                <input type="hidden" name="status_bayar" value="lainnya">
                                                <button type="submit" class="w-full flex items-center justify-center gap-1.5 bg-[#1a1d24] hover:bg-[#2d3139] text-gray-400 border border-[#2d3139] px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                    Others
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <svg class="w-12 h-12 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        <p class="text-gray-500 text-sm">No fines records found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($result && mysqli_num_rows($result) > 0): ?>
            <div class="p-4 border-t border-[#2d3139] bg-[#1a1d24]/30 text-xs text-gray-500 flex justify-between items-center">
                <span>Showing <?php echo mysqli_num_rows($result); ?> records</span>
                <span>Total outstanding: <strong class="text-white">Rp <?php echo number_format($totalFines, 0, ',', '.'); ?></strong></span>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- Calculate Fine Modal -->
<div id="fine_modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[#15181e] border border-[#2d3139] rounded-2xl w-full max-w-md shadow-2xl p-6 relative">
        <button type="button" onclick="closeModal()" class="absolute top-4 right-4 text-gray-500 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="flex items-center gap-3 mb-6">
            <div class="bg-blue-500/10 p-2.5 rounded-xl text-blue-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <div>
                <h3 class="text-xl font-bold text-white">Calculate Fine</h3>
                <p class="text-xs text-gray-400">Input fine amount for this issue</p>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="id_denda" id="modal_id_denda" value="">

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1">Issue Type</label>
                    <input type="text" id="modal_jenis_display" readonly class="w-full bg-[#0b0d10] border border-[#2d3139] text-gray-400 rounded-lg px-4 py-2.5 outline-none text-sm cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1">Fine Amount (Rp)</label>
                    <input type="number" name="jumlah_denda" id="modal_jumlah_denda" min="0" onkeyup="updatePreview()" onchange="updatePreview()" required class="w-full bg-[#0b0d10] border border-[#2d3139] text-gray-300 rounded-lg px-4 py-2.5 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm">
                </div>

                <div class="bg-black/20 p-4 rounded-xl border border-[#2d3139] mt-2">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wider mb-1">Total Fine</p>
                    <h4 id="fine_preview" class="text-2xl font-bold text-blue-500 mt-1">Rp 0</h4>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <button type="button" onclick="closeModal()" class="flex-1 bg-[#1a1d24] hover:bg-[#2d3139] text-gray-300 py-2.5 rounded-lg border border-[#2d3139] transition-colors text-sm font-semibold">Cancel</button>
                <button type="submit" name="calculate_fine" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white py-2.5 rounded-lg transition-colors text-sm font-semibold shadow-lg shadow-blue-600/20">Submit Fine</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCalculateModal(id_denda, jenis, daysLate) {
    document.getElementById('modal_id_denda').value = id_denda;
    document.getElementById('modal_jenis_display').value = jenis.toUpperCase() + (daysLate > 0 ? ` (${daysLate} days late)` : '');

    let fine = 0;
    if (jenis === 'keterlambatan') fine = daysLate * 2000;
    else if (jenis === 'kerusakan') fine = 50000;
    else if (jenis === 'kehilangan') fine = 100000;
    else fine = 25000;

    document.getElementById('modal_jumlah_denda').value = fine;
    updatePreview();

    document.getElementById('fine_modal').classList.remove('hidden');
    document.getElementById('fine_modal').classList.add('flex');
}

function closeModal() {
    document.getElementById('fine_modal').classList.add('hidden');
    document.getElementById('fine_modal').classList.remove('flex');
}

function updatePreview() {
    let fine = parseInt(document.getElementById('modal_jumlah_denda').value) || 0;
    document.getElementById('fine_preview').innerText = 'Rp ' + fine.toLocaleString('id-ID');
}

// Close modal when clicking outside
document.getElementById('fine_modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>