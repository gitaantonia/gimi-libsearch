<?php
//session_start();
include '../koneksi.php';

// Security check
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'pustakawan' && $_SESSION['role'] != 'admin')) {
    header("Location: ../login/loginadm.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_denda'])) {
    $id_denda = $_POST['id_denda'];
    if (!empty($id_denda)) {
        $stmt = $conn->prepare("UPDATE denda SET status = 'Sudah Dibayar' WHERE id_denda = ?");
        $stmt->bind_param("i", $id_denda);
        $stmt->execute();
        header("Location: handling_fines.php?msg=paid");
        exit;
    }
}

// 1. Auto-generate late fines on page load
$qLate = "SELECT p.id_peminjaman, DATEDIFF(p.tanggal_dikembalikan, p.tanggal_kembali) as days_late 
          FROM peminjaman p 
          LEFT JOIN denda d ON p.id_peminjaman = d.id_peminjaman AND d.jenis_denda = 'Terlambat'
          WHERE p.tanggal_dikembalikan IS NOT NULL 
          AND p.tanggal_dikembalikan > p.tanggal_kembali 
          AND d.id_denda IS NULL";
$resLate = mysqli_query($conn, $qLate);
if ($resLate) {
    while ($row = mysqli_fetch_assoc($resLate)) {
        $amount = (int)$row['days_late'] * 2000;
        $id_p = $row['id_peminjaman'];
        $stmt = $conn->prepare("INSERT IGNORE INTO denda (id_peminjaman, jenis_denda, jumlah_denda, status) VALUES (?, 'Terlambat', ?, 'Belum Dibayar')");
        $stmt->bind_param("id", $id_p, $amount);
        $stmt->execute();
    }
}

// Search and Filter handling
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'All';

$whereClause = "1=1";
if ($search) {
    $searchEscaped = "%".$conn->real_escape_string($search)."%";
    $whereClause .= " AND (u.nama LIKE '$searchEscaped' OR b.judul LIKE '$searchEscaped')";
}

if ($filter === 'Unpaid') {
    $whereClause .= " AND d.status = 'Belum Dibayar'";
} elseif ($filter === 'Paid') {
    $whereClause .= " AND d.status = 'Sudah Dibayar'";
} else if ($filter === 'With Fines') {
    $whereClause .= " AND d.id_denda IS NOT NULL";
}

$query = "
    SELECT p.id_peminjaman, u.nama, b.judul, p.tanggal_kembali as return_deadline, p.tanggal_dikembalikan as return_date, p.status as loan_status,
           d.id_denda, d.jenis_denda, d.jumlah_denda, d.status as status_denda
    FROM peminjaman p
    JOIN pengguna u ON p.id_pengguna = u.id_pengguna
    JOIN buku b ON p.id_buku = b.id_buku
    LEFT JOIN denda d ON p.id_peminjaman = d.id_peminjaman
    WHERE $whereClause
    ORDER BY p.id_peminjaman DESC, d.id_denda DESC
";
$result = mysqli_query($conn, $query);

// Total outstanding fines
$totalFines = 0;
$qTotal = mysqli_query($conn, "SELECT SUM(jumlah_denda) as total_denda FROM denda WHERE status = 'Belum Dibayar'");
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
<script>
    function confirmAction(message) {
        return confirm(message);
    }
</script>
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
                <a href="fasilitas_requests.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
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
        <a href="../login/logoutadm.php" class="mt-2 flex items-center gap-3 px-3 py-2 rounded-lg text-red-400 hover:bg-[#1a1d24] transition-colors cursor-pointer">
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
        <div class="flex items-center gap-4 card-bg px-5 py-3 rounded-lg border-red-900/50">
            <div class="bg-red-500/10 p-2 rounded-lg">
                <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Total Unpaid Fines</p>
                <h3 class="text-xl font-bold text-red-500 mt-0.5">Rp <?php echo number_format($totalFines, 0, ',', '.'); ?></h3>
            </div>
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
                        <option value="Unpaid" <?php if($filter=='Unpaid') echo 'selected'; ?>>Unpaid Fines</option>
                        <option value="Paid" <?php if($filter=='Paid') echo 'selected'; ?>>Paid Fines</option>
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
                            <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Fine details</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-400 uppercase tracking-wider text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#2d3139]/80">
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): 
                                $isLate = ($row['return_date'] && $row['return_date'] > $row['return_deadline']) || (!$row['return_date'] && date('Y-m-d') > $row['return_deadline']);
                                $lateClass = $isLate ? "text-yellow-500 bg-yellow-500/10" : "text-gray-300";
                            ?>
                            <tr class="hover:bg-[#15181e] transition-colors group">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-[#2d3139] flex items-center justify-center shrink-0 text-gray-400 group-hover:text-blue-400 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-200"><?php echo htmlspecialchars($row['judul']); ?></p>
                                            <p class="text-xs text-gray-500 mt-0.5">User: <span class="text-gray-400"><?php echo htmlspecialchars($row['nama']); ?></span></p>
                                            <p class="text-[10px] text-gray-600 font-mono mt-0.5">ID: #<?php echo $row['id_peminjaman']; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1 text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-500 w-12">Deadline:</span>
                                            <span class="text-gray-300 font-medium"><?php echo date('d M Y', strtotime($row['return_deadline'])); ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-500 w-12">Returned:</span>
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
                                <td class="px-6 py-4">
                                    <?php 
                                        $ls = strtolower($row['loan_status']);
                                        $badgeClass = "bg-gray-500/10 text-gray-400 border-gray-500/20";
                                        if($ls == 'hilang') $badgeClass = "bg-red-500/10 text-red-400 border-red-500/20";
                                        elseif($ls == 'rusak') $badgeClass = "bg-orange-500/10 text-orange-400 border-orange-500/20";
                                        elseif($ls == 'dikembalikan' || $ls == 'kembali') $badgeClass = "bg-green-500/10 text-green-400 border-green-500/20";
                                        elseif($ls == 'dipinjam' && $isLate) $badgeClass = "bg-yellow-500/10 text-yellow-400 border-yellow-500/20";
                                        elseif($ls == 'dipinjam') $badgeClass = "bg-blue-500/10 text-blue-400 border-blue-500/20";
                                    ?>
                                    <span class="px-2.5 py-1 text-[11px] font-semibold rounded-full border <?php echo $badgeClass; ?> inline-block uppercase tracking-wider">
                                        <?php echo htmlspecialchars($row['loan_status']); ?> <?php echo ($ls == 'dipinjam' && $isLate) ? '(Late)' : ''; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if($row['id_denda']): ?>
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2">
                                                <?php 
                                                    $f_type = $row['jenis_denda'];
                                                    $tClass = "text-gray-400";
                                                    if($f_type == 'Terlambat') $tClass = "text-yellow-400";
                                                    if($f_type == 'Hilang') $tClass = "text-red-400";
                                                    if($f_type == 'Rusak') $tClass = "text-orange-400";
                                                    
                                                    $d_en = $f_type;
                                                    if($f_type == 'Terlambat') $d_en = 'Late';
                                                    if($f_type == 'Hilang') $d_en = 'Lost';
                                                    if($f_type == 'Rusak') $d_en = 'Damaged';
                                                ?>
                                                <span class="text-xs font-semibold <?php echo $tClass; ?> uppercase"><?php echo $d_en; ?> Fine</span>
                                                <span class="text-sm font-bold text-white tracking-wide">Rp <?php echo number_format($row['jumlah_denda'], 0, ',', '.'); ?></span>
                                            </div>
                                            <div>
                                                <?php if($row['status_denda'] == 'Belum Dibayar'): ?>
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-500/10 text-red-400 border border-red-500/20">UNPAID</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-500/10 text-green-400 border border-green-500/20">PAID</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-600 text-xs italic">No fines</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex flex-col gap-2 relative z-10 w-full max-w-[140px] mx-auto">
                                        <?php if($row['id_denda'] && $row['status_denda'] == 'Belum Dibayar'): ?>
                                            <form action="" method="POST" onsubmit="return confirmAction('Mark this fine as Paid?');">
                                                <input type="hidden" name="id_denda" value="<?php echo $row['id_denda']; ?>">
                                                <button type="submit" class="w-full bg-green-500/20 hover:bg-green-500/30 text-green-400 border border-green-500/30 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center justify-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    Mark Paid
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                        <p>No records found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if(mysqli_num_rows($result) > 0): ?>
            <div class="p-4 border-t border-[#2d3139] bg-[#1a1d24]/30 text-xs text-gray-500 flex justify-between items-center">
                <span>Showing <?php echo mysqli_num_rows($result); ?> records</span>
            </div>
            <?php endif; ?>
        </div>
        
    </div>
</main>
</body>
</html>
