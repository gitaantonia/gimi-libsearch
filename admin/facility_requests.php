<?php
//session_start();
include 'koneksi.php';

// Validasi role
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'pustakawan' && $_SESSION['role'] != 'admin')) {
    header("Location: loginadm.php");
    exit;
}

// Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_clauses = ["p.role = 'anggota'"];
$params = [];
$types = "";

if ($search !== '') {
    $where_clauses[] = "(p.nama LIKE ? OR f.nama_fasilitas LIKE ?)";
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

// Query
$query = "
    SELECT 
        r.id_request,
        r.created_at,
        r.status,
        r.tanggal_pakai,
        r.waktu_mulai,
        r.waktu_selesai,
        p.nama,
        f.id AS id_fasilitas,
        f.nama_fasilitas,
        f.status AS status_fasilitas
    FROM request_fasilitas r
    JOIN pengguna p ON r.id_pengguna = p.id_pengguna
    JOIN fasilitas f ON r.id_fasilitas = f.id
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
<title>Facility Requests</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #0b0d10;
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
    </style>

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
            <svg class="icon" viewBox="0 0 24 24"><path d="M3 21h18"></path><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path><path d="M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2-2v4"></path></svg>
            <span class="font-medium">Facility</span>
        </a>
        <a href="book_catalogue.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>
            <span class="font-medium">Book Catalogue</span>
        </a>
        <a href= "book_requests.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <span class="font-medium">Book Requests</span>
        </a>
        <a href="facility_requests.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span class="font-medium">Facility Request</span>
        </a>
        <a href="handling_fines.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
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
        <a href="logout.php" class="mt-2 flex items-center gap-3 px-3 py-2 rounded-lg text-red-400 hover:bg-[#1a1d24] transition-colors cursor-pointer">
            <svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            <span class="font-medium">Logout</span>
        </a>
    </div>
</aside>

<!-- MAIN -->
<main class="flex-1 flex flex-col overflow-hidden bg-[#0b0d10] relative">

    <!-- Header -->
    <header class="px-8 py-6 border-b border-[#2d3139] flex justify-between items-end shrink-0">
        <div>
            <div class="text-xs text-gray-400 mb-1 font-medium tracking-wide">
                OVERVIEW
            </div>
            <h2 class="text-2xl font-semibold text-white tracking-tight">Facilities Requests Management</h2>
        </div>
    </header>
<!-- CONTENT -->
<div class="p-8 space-y-6">

<!-- FILTER -->
<form method="GET" class="flex gap-3">
    <input type="text" name="search" placeholder="Search..."
        class="px-3 py-2 bg-[#15181e] border border-gray-700 rounded"
        value="<?= htmlspecialchars($search) ?>">

    <select name="status" class="px-3 py-2 bg-[#15181e] border border-gray-700 rounded">
        <option value="">All</option>
        <option value="Pending" <?= $status_filter=='Pending'?'selected':'' ?>>Pending</option>
        <option value="Disetujui" <?= $status_filter=='Disetujui'?'selected':'' ?>>Approved</option>
        <option value="Ditolak" <?= $status_filter=='Ditolak'?'selected':'' ?>>Rejected</option>
    </select>

    <button class="bg-blue-600 px-4 rounded">Filter</button>
</form>

<!-- TABLE -->
<div class="bg-[#15181e] rounded-lg overflow-hidden">
<table class="w-full text-sm">
<thead class="bg-[#1a1d24] text-gray-400">
<tr>
<th class="p-3">ID</th>
<th class="p-3">Member</th>
<th class="p-3">Facility</th>
<th class="p-3">Schedule</th>
<th class="p-3">Status</th>
<th class="p-3 text-right">Action</th>
</tr>
</thead>

<tbody>
<?php while($row = $result->fetch_assoc()): ?>
<tr class="border-t border-gray-700">

<td class="p-3">#<?= $row['id_request'] ?></td>

<td class="p-3"><?= htmlspecialchars($row['nama']) ?></td>

<td class="p-3"><?= htmlspecialchars($row['nama_fasilitas']) ?></td>

<td class="p-3">
<?= date('d M Y', strtotime($row['tanggal_pakai'])) ?><br>
<span class="text-xs text-gray-400">
<?= $row['waktu_mulai'] ?> - <?= $row['waktu_selesai'] ?>
</span>
</td>

<td class="p-3">
<?php if($row['status']=='Pending'): ?>
<span class="text-yellow-400">Pending</span>
<?php elseif($row['status']=='Disetujui'): ?>
<span class="text-green-400">Approved</span>
<?php else: ?>
<span class="text-red-400">Rejected</span>
<?php endif; ?>
</td>

<td class="p-3 text-right">
<?php if($row['status']=='Pending'): ?>

<?php if($row['status_fasilitas']=='dipakai'): ?>
<button disabled class="text-gray-500">Dipakai</button>
<?php else: ?>
<button onclick="openAcc(<?= $row['id_request'] ?>, <?= $row['id_fasilitas'] ?>)" 
class="bg-green-600 px-2 py-1 rounded text-xs">ACC</button>
<?php endif; ?>

<button onclick="openReject(<?= $row['id_request'] ?>)" 
class="bg-red-600 px-2 py-1 rounded text-xs">X</button>

<?php endif; ?>
</td>

</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

</div>
</main>

<!-- MODAL ACC -->
<div id="modalAcc" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center">
<div class="bg-white text-black p-6 rounded w-80">
<form method="POST" action="proses_acc_fasilitas.php">
<input type="hidden" name="id_request" id="acc_id">
<input type="hidden" name="id_fasilitas" id="acc_fasilitas">

<p>Approve booking?</p>

<div class="mt-4 flex justify-end gap-2">
<button type="button" onclick="closeAcc()">Cancel</button>
<button class="bg-green-600 text-white px-3 py-1 rounded">Approve</button>
</div>

</form>
</div>
</div>

<!-- MODAL REJECT -->
<div id="modalReject" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center">
<div class="bg-white text-black p-6 rounded w-80">
<form method="POST" action="proses_reject_fasilitas.php">
<input type="hidden" name="id_request" id="reject_id">

<p>Reject booking?</p>

<div class="mt-4 flex justify-end gap-2">
<button type="button" onclick="closeReject()">Cancel</button>
<button class="bg-red-600 text-white px-3 py-1 rounded">Reject</button>
</div>

</form>
</div>
</div>

<script>
function openAcc(id, fasilitas){
    document.getElementById('acc_id').value = id;
    document.getElementById('acc_fasilitas').value = fasilitas;
    document.getElementById('modalAcc').classList.remove('hidden');
}

function closeAcc(){
    document.getElementById('modalAcc').classList.add('hidden');
}

function openReject(id){
    document.getElementById('reject_id').value = id;
    document.getElementById('modalReject').classList.remove('hidden');
}

function closeReject(){
    document.getElementById('modalReject').classList.add('hidden');
}
</script>

</body>
</html>