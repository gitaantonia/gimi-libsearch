<?php
//session_start();
include '../koneksi.php';

// Validasi role (hanya admin/pustakawan)
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'pustakawan' && $_SESSION['role'] != 'admin')) {
    header("Location: ../login/loginadm.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_peminjaman'])) {
    $id_peminjaman = $_POST['id_peminjaman'];

    if (isset($_POST['tanggal_pinjam']) && isset($_POST['tanggal_kembali'])) {
        // Proses ACC
        $tanggal_pinjam = $_POST['tanggal_pinjam'];
        $tanggal_kembali = $_POST['tanggal_kembali'];
        $id_buku = $_POST['id_buku'] ?? '';

        $conn->begin_transaction();
        try {
            // Update peminjaman status
            $query_update = "UPDATE peminjaman SET status = 'dipinjam', tanggal_pinjam = ?, tanggal_kembali = ? WHERE id_peminjaman = ?";
            $stmt_update = $conn->prepare($query_update);
            $stmt_update->bind_param("ssi", $tanggal_pinjam, $tanggal_kembali, $id_peminjaman);
            $stmt_update->execute();

            // Mengurangi stok buku karena dipinjam
            if (!empty($id_buku)) {
                $query_buku = "UPDATE buku SET stok = stok - 1 WHERE id_buku = ? AND stok > 0";
                $stmt_buku = $conn->prepare($query_buku);
                $stmt_buku->bind_param("s", $id_buku);
                $stmt_buku->execute();
            }

            $conn->commit();
            $_SESSION['msg'] = "Request successfully approved!";
            $_SESSION['msg_type'] = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "Failed to approve request.";
            $_SESSION['msg_type'] = "danger";
        }
    } else {
        // Proses Reject
        $query = "UPDATE peminjaman SET status = 'ditolak' WHERE id_peminjaman = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_peminjaman);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = "Request has been rejected.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "Failed to reject request.";
            $_SESSION['msg_type'] = "danger";
        }
    }
    header("Location: book_requests.php");
    exit;
}

// Mengambil data request
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_clauses = ["p.role = 'anggota'"];
$params = [];
$types = "";

if ($search !== '') {
    $where_clauses[] = "(p.nama LIKE ? OR b.judul LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if ($status_filter !== '') {
    $where_clauses[] = "r.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

$query = "
    SELECT 
        r.id_peminjaman,
        r.created_at,
        r.status,
        p.id_pengguna,
        p.nama,
        b.id_buku,
        b.judul,
        b.stok
    FROM peminjaman r
    JOIN pengguna p ON r.id_pengguna = p.id_pengguna
    LEFT JOIN buku b ON r.id_buku = b.id_buku
    WHERE $where_sql
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($query);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Requests - GiMi Library</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- FontAwesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #0b0d10; /* Dark background from design */
        color: #e2e8f0;
    }
    
    /* Custom Scrollbar for sleek look */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #2d3139; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #4b5563; }

    .card-bg {
        background-color: #15181e; /* matches UI card borders */
        border: 1px solid #2d3139;
    }
    
    .sidebar-item {
        color: #94a3b8;
        transition: all 0.2s ease-in-out;
    }
    .sidebar-item:hover {
        color: #e2e8f0;
        background-color: #1a1d24;
    }
    .sidebar-item.active {
        background-color: rgba(30, 58, 138, 0.2); /* blue-900 transparent */
        color: #60a5fa; /* blue-400 */
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    .icon { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
    
    /* Input outlines */
    input:focus, select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px #3b82f6;
    }
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
        <a href="book_requests.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
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

        <a href="handling_fines.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
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
    <header class="px-8 py-6 border-b border-[#2d3139] flex justify-between items-end">
        <div>
            <div class="text-xs text-gray-400 mb-1 font-medium tracking-wide">
                OVERVIEW
            </div>
            <h2 class="text-2xl font-semibold text-white tracking-tight">Book Requests Management</h2>
        </div>
    </header>

    <!-- Content -->
    <div class="p-8 space-y-6 max-w-[1400px]">

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="flex px-4 py-3 rounded-lg <?php echo $_SESSION['msg_type'] == 'success' ? 'bg-green-900/40 border border-green-500/50 text-green-400' : ($_SESSION['msg_type'] == 'danger' ? 'bg-red-900/40 border border-red-500/50 text-red-400' : 'bg-yellow-900/40 border border-yellow-500/50 text-yellow-400'); ?> my-4 relative">
                <span class="block sm:inline"><?php echo $_SESSION['msg']; ?></span>
            </div>
            <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
        <?php endif; ?>

        <!-- Filter & Search Section -->
        <form method="GET" action="book_requests.php" class="flex flex-col sm:flex-row gap-4 items-center">
            
            <div class="relative w-full sm:w-auto flex-1 max-w-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </div>
                <input type="text" name="search" class="w-full bg-[#15181e] border border-[#2d3139] text-gray-200 text-sm rounded-lg block pl-10 p-2.5 transition-colors focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Search for members / titles..." value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="w-full sm:w-auto">
                <select name="status" class="bg-[#15181e] border border-[#2d3139] text-gray-200 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="dipinjam" <?php echo ($status_filter == 'dipinjam') ? 'selected' : ''; ?>>Approved/Borrowed</option>
                    <option value="ditolak" <?php echo ($status_filter == 'ditolak') ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>

            <div class="w-full sm:w-auto flex gap-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors text-sm font-medium border border-blue-600">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <a href="book_requests.php" class="bg-[#1a1d24] hover:bg-[#2d3139] text-gray-300 px-4 py-2.5 rounded-lg border border-[#2d3139] flex items-center gap-2 transition-colors text-sm font-medium">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            </div>
        </form>

        <!-- Table Card -->
        <div class="card-bg rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#1a1d24] text-gray-400 text-xs uppercase tracking-wider border-b border-[#2d3139]">
                            <th class="px-6 py-4 font-medium">ID</th>
                            <th class="px-6 py-4 font-medium">Members Name</th>
                            <th class="px-6 py-4 font-medium">Book Title</th>
                            <th class="px-6 py-4 font-medium">Request Date</th>
                            <th class="px-6 py-4 font-medium">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#2d3139]">
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-[#1a1d24] transition-colors">
                            <td class="px-6 py-4 text-gray-300">#<?php echo $row['id_peminjaman']; ?></td>
                            <td class="px-6 py-4 font-medium text-white"><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td class="px-6 py-4">
                                <?php 
                                if (empty($row['id_buku'])) {
                                    echo '<div class="flex items-center gap-2 text-yellow-500/90 text-xs font-medium bg-yellow-500/10 px-2 py-1 rounded w-fit border border-yellow-500/20"><i class="fa-solid fa-triangle-exclamation"></i> Book has not been selected yet</div>';
                                } else {
                                    echo '<div class="text-gray-200">'.htmlspecialchars($row['judul']).'</div>';
                                    if ($row['stok'] == 0) {
                                        echo '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-red-500/20 text-red-400 border border-red-500/20 mt-1">Out of stock</span>';
                                    } else {
                                        echo '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-700 text-gray-300 border border-gray-600 mt-1">Stok: '.$row['stok'].'</span>';
                                    }
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-gray-400 text-sm whitespace-nowrap"><?php echo date('d M Y H:i', strtotime($row['created_at'])); ?></td>
                            <td class="px-6 py-4">
                                <?php 
                                if ($row['status'] == 'pending') {
                                    echo '<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-yellow-500/20 text-yellow-500 border border-yellow-500/30">Pending</span>';
                                } elseif ($row['status'] == 'dipinjam') {
                                    echo '<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-green-500/20 text-green-400 border border-green-500/30">Approved</span>';
                                } elseif ($row['status'] == 'ditolak') {
                                    echo '<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/30">Rejected</span>';
                                } else {
                                    echo '<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-500/20 text-gray-400 border border-gray-500/30">'.htmlspecialchars(ucfirst($row['status'])).'</span>';
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($row['status'] == 'pending'): ?>
                                    <div class="flex justify-end gap-2">
                                        <?php if (empty($row['id_buku'])): ?>
                                            <button disabled class="bg-gray-800 text-gray-500 px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-700 cursor-not-allowed">Book has not been selected yet</button>
                                            <button onclick="openRejectModal(<?php echo $row['id_peminjaman']; ?>)" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/30 px-2.5 py-1.5 rounded-lg transition-colors"><i class="fa-solid fa-xmark"></i></button>
                                        <?php elseif ($row['stok'] == 0): ?>
                                            <button disabled class="bg-gray-800 text-gray-500 px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-700 cursor-not-allowed">Out of stock</button>
                                            <button onclick="openRejectModal(<?php echo $row['id_peminjaman']; ?>)" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/30 px-2.5 py-1.5 rounded-lg transition-colors"><i class="fa-solid fa-xmark"></i></button>
                                        <?php else: ?>
                                            <button onclick="openAccModal(<?php echo $row['id_peminjaman']; ?>, '<?php echo $row['id_buku']; ?>')" class="bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5">
                                                <i class="fa-solid fa-check"></i> ACC
                                            </button>
                                            <button onclick="openRejectModal(<?php echo $row['id_peminjaman']; ?>)" class="bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 px-2.5 py-1.5 rounded-lg transition-colors"><i class="fa-solid fa-xmark"></i></button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-600"><i class="fa-solid fa-ellipsis"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if($result->num_rows == 0): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fa-solid fa-inbox text-4xl mb-3 opacity-20"></i>
                                    <p>There are no book requests yet.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Tailwind Modal ACC -->
<div id="modalAcc" class="fixed inset-0 z-50 hidden flex items-center justify-center">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAccModal()"></div>
    <div class="bg-[#15181e] border border-[#2d3139] rounded-xl shadow-2xl z-10 w-full max-w-md mx-4 transform transition-all">
        <div class="flex justify-between items-center p-5 border-b border-[#2d3139]">
            <h3 class="text-lg font-semibold text-white">Request Approve</h3>
            <button onclick="closeAccModal()" class="text-gray-400 hover:text-white transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form action="" method="POST">
            <div class="p-5 space-y-4">
                <input type="hidden" name="id_peminjaman" id="acc_id_request">
                <input type="hidden" name="id_buku" id="acc_id_buku">
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Request Date</label>
                    <input type="date" class="w-full bg-[#0b0d10] border border-[#2d3139] text-gray-200 rounded-lg p-2.5" name="tanggal_pinjam" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Deadline</label>
                    <input type="date" class="w-full bg-[#0b0d10] border border-[#2d3139] text-gray-200 rounded-lg p-2.5" name="tanggal_kembali" required value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
                </div>
            </div>
            <div class="p-5 border-t border-[#2d3139] flex justify-end gap-3 bg-[#0b0d10]/50 rounded-b-xl">
                <button type="button" onclick="closeAccModal()" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-300 bg-[#1a1d24] hover:bg-[#2d3139] border border-[#2d3139] transition-colors">Cancelled</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-500 transition-colors">Approve the Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Tailwind Modal Reject -->
<div id="modalReject" class="fixed inset-0 z-50 hidden flex items-center justify-center">
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" onclick="closeRejectModal()"></div>
    <div class="bg-[#15181e] border border-[#2d3139] rounded-xl shadow-2xl z-10 w-full max-w-sm mx-4 transform transition-all">
        <form action="" method="POST">
            <div class="p-6 text-center">
                <input type="hidden" name="id_peminjaman" id="reject_id_request">
                
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-500/10 mb-4 border border-red-500/20">
                    <i class="fa-solid fa-triangle-exclamation text-red-500 text-xl"></i>
                </div>
                
                <h3 class="text-lg font-semibold text-white mb-2">Request Reject</h3>
                <p class="text-sm text-gray-400">Are you sure you want to reject this request? This action cannot be undone.</p>
                
                <div class="mt-6 flex justify-center gap-3">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-300 bg-[#1a1d24] hover:bg-[#2d3139] border border-[#2d3139] transition-colors">Cancelled</button>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-500 transition-colors">Yes, Reject</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal controls for ACC
    const modalAcc = document.getElementById('modalAcc');
    const accReqId = document.getElementById('acc_id_request');
    const accBukuId = document.getElementById('acc_id_buku');

    function openAccModal(id, buku) {
        accReqId.value = id;
        accBukuId.value = buku;
        modalAcc.classList.remove('hidden');
    }

    function closeAccModal() {
        modalAcc.classList.add('hidden');
    }

    // Modal controls for Reject
    const modalReject = document.getElementById('modalReject');
    const rejectReqId = document.getElementById('reject_id_request');

    function openRejectModal(id) {
        rejectReqId.value = id;
        modalReject.classList.remove('hidden');
    }

    function closeRejectModal() {
        modalReject.classList.add('hidden');
    }
</script>

</body>
</html>
