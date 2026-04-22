<?php
// session_start() is handled by koneksi.php
include '../koneksi.php';

// Cek login
if (!isset($_SESSION['role'])) {
    header("Location: login/loginadm.php");
    exit;
}

if ($_SESSION['role'] != 'pustakawan' && $_SESSION['role'] != 'admin') {
    echo "Akses ditolak";
    exit;
}

// Helper time formatter
function time_elapsed_string($datetime, $full = false) {
    if (!$datetime) return 'unknown time';
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array('y' => 'year','m' => 'month','w' => 'week','d' => 'day','h' => 'hour','i' => 'min','s' => 'sec');
    foreach ($string as $k => &$v) {
        if ($diff->$k) { $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); } 
        else { unset($string[$k]); }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$total_buku = 0; $total_anggota = 0; $total_aktif = 0; $total_selesai = 0;
try {
    $q_buku = mysqli_query($conn, "SELECT COUNT(*) as total FROM buku");
    if($q_buku) { $d = mysqli_fetch_assoc($q_buku); $total_buku = $d['total']; }
    $q_anggota = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengguna WHERE role='anggota'");
    if($q_anggota) { $d = mysqli_fetch_assoc($q_anggota); $total_anggota = $d['total']; }
    $q_aktif = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE status='dipinjam'");
    if($q_aktif) { $d = mysqli_fetch_assoc($q_aktif); $total_aktif = $d['total']; }
    $q_selesai = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE status IN ('kembali', 'dikembalikan')");
    if($q_selesai) { $d = mysqli_fetch_assoc($q_selesai); $total_selesai = $d['total']; }
} catch (Exception $e) {}

// Fetch recent activities
$activities = [];
try {
    $q_act = mysqli_query($conn, "
        SELECT p.id_peminjaman, p.status, p.created_at, p.tanggal_dikembalikan, u.nama, b.judul 
        FROM peminjaman p 
        JOIN pengguna u ON p.id_pengguna = u.id_pengguna 
        LEFT JOIN buku b ON p.id_buku = b.id_buku 
        ORDER BY p.updated_at DESC, p.created_at DESC 
        LIMIT 5
    ");
    if($q_act) {
        while($row = mysqli_fetch_assoc($q_act)) {
            $activities[] = $row;
        }
    }
} catch (Exception $e) {}

// If updated_at is not available, we can fallback to created_at
if (empty($activities)) {
    try {
        $q_act = mysqli_query($conn, "
            SELECT p.id_peminjaman, p.status, p.created_at, p.tanggal_dikembalikan, u.nama, b.judul 
            FROM peminjaman p 
            JOIN pengguna u ON p.id_pengguna = u.id_pengguna 
            LEFT JOIN buku b ON p.id_buku = b.id_buku 
            ORDER BY p.created_at DESC 
            LIMIT 5
        ");
        if($q_act) {
            while($row = mysqli_fetch_assoc($q_act)) {
                $activities[] = $row;
            }
        }
    } catch (Exception $e) {}
}

// Fetch chart data (last 7 months)
$chart_months = [];
$chart_active = [];
$chart_completed = [];
for ($i = 6; $i >= 0; $i--) {
    $month_year = date('Y-m', strtotime("-$i month"));
    $month_label = date('M', strtotime("-$i month"));
    $chart_months[] = $month_label;

    $act_count = 0; $comp_count = 0;
    try {
        $q_act_m = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month_year'");
        if($q_act_m) { $act_count = mysqli_fetch_assoc($q_act_m)['total']; }
        $q_comp_m = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE DATE_FORMAT(tanggal_dikembalikan, '%Y-%m') = '$month_year'");
        if($q_comp_m) { $comp_count = mysqli_fetch_assoc($q_comp_m)['total']; }
    } catch (Exception $e) {}
    $chart_active[] = $act_count;
    $chart_completed[] = $comp_count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - GiMi Library</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        <a href="dashboard_adm.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
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
            <h2 class="text-2xl font-semibold text-white tracking-tight">Dashboard Statistics</h2>
        </div>
        <div>
            <button class="bg-[#1a1d24] hover:bg-[#2d3139] text-gray-300 px-4 py-2 rounded-lg border border-[#2d3139] flex items-center gap-2 transition-colors text-sm font-medium">
                <svg class="icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Export Report
            </button>
        </div>
    </header>

    <!-- Content -->
    <div class="p-8 space-y-6 max-w-[1400px]">
        
        <!-- Summary Cards / Charts -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <?php
            $stats = [
                ['label' => 'Total Books', 'value' => $total_buku, 'color' => '#3b82f6', 'icon' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>'],
                ['label' => 'Total Members', 'value' => $total_anggota, 'color' => '#10b981', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
                ['label' => 'Active Requests', 'value' => $total_aktif, 'color' => '#f59e0b', 'icon' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>'],
                ['label' => 'Completed Requests', 'value' => $total_selesai, 'color' => '#8b5cf6', 'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>'],
            ];

            foreach($stats as $index => $stat) {
                // Generate color variants for shadows and fills
                $hex = ltrim($stat['color'], '#');
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $rgbaDark = "rgba($r, $g, $b, 0.1)";

                echo '
                <div class="card-bg p-5 rounded-xl flex flex-col justify-between relative overflow-hidden group hover:border-gray-600 transition-colors">
                    <div class="z-10 flex justify-between items-start mb-2">
                        <div>
                            <p class="text-gray-400 text-xs font-semibold uppercase tracking-wider mb-1">'.$stat['label'].'</p>
                            <h3 class="text-3xl font-bold text-white">'.number_format($stat['value']).'</h3>
                        </div>
                        <div class="p-2 rounded-lg" style="background-color: '.$rgbaDark.'; color: '.$stat['color'].'">
                            <svg class="icon w-5 h-5" viewBox="0 0 24 24">'.$stat['icon'].'</svg>
                        </div>
                    </div>
                    <div class="mt-2 h-14 w-full z-10 w-full">
                        <canvas id="miniChart'.$index.'"></canvas>
                    </div>
                    <div class="absolute -right-4 -top-4 w-32 h-32 rounded-full opacity-5 pointer-events-none group-hover:opacity-10 transition-opacity" style="background-color: '.$stat['color'].'; filter: blur(20px);"></div>
                </div>
                ';
            }
            ?>
        </div>

        <!-- Main Chart Area & Secondary Info -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
            
            <!-- Large Chart -->
            <div class="card-bg rounded-xl overflow-hidden lg:col-span-2">
                <div class="p-6 border-b border-[#2d3139] flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Monthly Circulation</h3>
                        <p class="text-sm text-gray-400 mt-1">Active vs Completed Loans Comparison</p>
                    </div>
                    <select class="bg-[#0b0d10] border border-[#2d3139] text-gray-300 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2 outline-none">
                        <option>This Year</option>
                        <option>This Month</option>
                        <option>This Week</option>
                    </select>
                </div>
                <div class="p-6 relative h-80">
                    <canvas id="mainChart"></canvas>
                </div>
            </div>

            <!-- Side Widgets -->
            <div class="space-y-6">
                <!-- Status List (Mock Example matching design) -->
                <div class="card-bg rounded-xl overflow-hidden">
                    <div class="p-5 border-b border-[#2d3139]">
                        <h3 class="text-sm font-semibold text-white">Recent Activities</h3>
                    </div>
                    <div class="p-0">
                        <ul class="divide-y divide-[#2d3139]">
                            <?php if (!empty($activities)): ?>
                                <?php foreach ($activities as $act): ?>
                                    <?php 
                                        $act_status = strtolower($act['status']);
                                        $color = 'bg-blue-500';
                                        $action_text = "borrowed by";
                                        $date_val = $act['created_at'];

                                        if (in_array($act_status, ['kembali', 'dikembalikan'])) {
                                            $color = 'bg-purple-500';
                                            $action_text = "was returned by";
                                            $date_val = $act['tanggal_dikembalikan'] ? $act['tanggal_dikembalikan'] : $act['created_at'];
                                        } elseif ($act_status === 'pending') {
                                            $color = 'bg-yellow-500';
                                            $action_text = "was requested by";
                                        } elseif ($act_status === 'ditolak') {
                                            $color = 'bg-red-500';
                                            $action_text = "request rejected for";
                                        }

                                        $title = $act['judul'] ? $act['judul'] : 'Unknown Book';
                                        $nama = $act['nama'];
                                        
                                        $msg = "Book \"$title\" $action_text $nama.";
                                        if ($act_status === 'ditolak') $msg = "Book request for \"$title\" by $nama was rejected.";
                                        
                                        $time_ago = time_elapsed_string($date_val);
                                    ?>
                                    <li class="p-4 hover:bg-[#1a1d24] transition-colors flex gap-3">
                                        <div class="w-2 h-2 mt-1.5 rounded-full <?php echo $color; ?> shrink-0"></div>
                                        <div>
                                            <p class="text-sm text-gray-200"><?php echo htmlspecialchars($msg); ?></p>
                                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($time_ago); ?></p>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="p-4 text-center text-gray-500 text-sm">No recent activities found.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="p-4 border-t border-[#2d3139] text-center">
                        <a href="#" class="text-blue-400 hover:text-blue-300 text-sm font-medium">View All Activities</a>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</main>

<script>
    // Global Chart Config for dark mode
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Inter', sans-serif";

    // Helper fake data generator for sparklines
    const generateData = (base) => Array.from({length: 8}, () => Math.floor(Math.random() * (base * 0.5)) + (base*0.5));

    const sparklineOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: {
            x: { display: false },
            y: { display: false, min: 0 }
        },
        elements: {
            line: { tension: 0.4, borderWidth: 2 },
            point: { radius: 0, hoverRadius: 0 }
        },
        layout: { padding: 0 }
    };

    <?php foreach($stats as $index => $stat): ?>
    new Chart(document.getElementById('miniChart<?php echo $index; ?>').getContext('2d'), {
        type: 'line',
        data: {
            labels: ['1','2','3','4','5','6','7','8'],
            datasets: [{
                data: generateData(<?php echo max(10, $stat['value']); ?>),
                borderColor: '<?php echo $stat['color']; ?>',
                fill: true,
                backgroundColor: (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 56); // 56px roughly canvas height
                    gradient.addColorStop(0, '<?php echo $stat['color']; ?>40'); // 25% opacity
                    gradient.addColorStop(1, '<?php echo $stat['color']; ?>00');
                    return gradient;
                }
            }]
        },
        options: sparklineOptions
    });
    <?php endforeach; ?>


    // Main Bar Chart 
    const ctxMain = document.getElementById('mainChart').getContext('2d');
    
    // Dynamic data fetched from database
    const months = <?php echo json_encode($chart_months); ?>;
    const activeData = <?php echo json_encode($chart_active); ?>;
    const completedData = <?php echo json_encode($chart_completed); ?>;

    new Chart(ctxMain, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Active Loans',
                    data: activeData.map(v => Math.max(0, v)),
                    backgroundColor: '#3b82f6',
                    borderRadius: 4,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Completed Loans',
                    data: completedData.map(v => Math.max(0, v)),
                    backgroundColor: '#8b5cf6',
                    borderRadius: 4,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { 
                        boxWidth: 10, 
                        usePointStyle: true, 
                        pointStyle: 'circle',
                        padding: 20,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: '#15181e',
                    titleColor: '#fff',
                    bodyColor: '#94a3b8',
                    borderColor: '#2d3139',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { color: '#2d3139', drawBorder: false },
                    border: { display: false }
                },
                x: { 
                    grid: { display: false },
                    border: { display: false }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
        }
    });
</script>
</body>
</html>

