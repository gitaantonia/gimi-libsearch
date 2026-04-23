<?php
session_start();
include '../../regis/koneksi.php';

// Cek login
if (!isset($_SESSION['role'])) {
    header("Location: ../../regis/login.php");
    exit;
}

$message = "";

// PROSES DELETE
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    // Ambil data gambar utk dihapus opsional
    $q_del = mysqli_query($conn, "SELECT gambar FROM fasilitas WHERE id='$id'");
    if($r_del = mysqli_fetch_assoc($q_del)) {
        if(!empty($r_del['gambar']) && file_exists("upload/" . $r_del['gambar'])){
            unlink("upload/" . $r_del['gambar']);
        }
    }
    
    $del = mysqli_query($conn, "DELETE FROM fasilitas WHERE id='$id'");
    if($del) {
        $message = "Facility successfully deleted";
    }
}

// PROSES CREATE & UPDATE
if (isset($_POST['submit'])) {
    $id = isset($_POST['id']) ? mysqli_real_escape_string($conn, $_POST['id']) : '';
    $is_update = !empty($id);

    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $kapasitas = mysqli_real_escape_string($conn, $_POST['kapasitas']);
    $lokasi = mysqli_real_escape_string($conn, $_POST['lokasi']);

    $folder = "upload/";
    if (!is_dir($folder)) mkdir($folder, 0777, true);

    $gambar = "";
    if ($is_update) {
        $gambar = mysqli_real_escape_string($conn, $_POST['old_gambar']);
    }

    if (!empty($_FILES['gambar']['name'])) {
        $file = $_FILES['gambar']['name'];
        $tmp = $_FILES['gambar']['tmp_name'];
        $namaBaru = time() . "_" . $file;
        if (move_uploaded_file($tmp, $folder . $namaBaru)) {
            if ($is_update && !empty($gambar) && file_exists($folder . $gambar)) {
                unlink($folder . $gambar);
            }
            $gambar = $namaBaru;
        }
    }

    if ($is_update) {
        $query = "UPDATE fasilitas SET 
            nama_fasilitas='$nama', 
            kategori='$kategori', 
            deskripsi='$deskripsi', 
            kapasitas='$kapasitas', 
            lokasi='$lokasi',
            gambar='$gambar' 
            WHERE id='$id'";
        if (mysqli_query($conn, $query)) {
            $message = "Facility successfully updated";
        }
    } else {
        $query = "INSERT INTO fasilitas (nama_fasilitas, kategori, deskripsi, kapasitas, lokasi, gambar) 
                  VALUES ('$nama', '$kategori', '$deskripsi', '$kapasitas', '$lokasi', '$gambar')";
        if (mysqli_query($conn, $query)) {
            $message = "Facility successfully added";
        }
    }
}

// Data untuk form Edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = mysqli_real_escape_string($conn, $_GET['edit']);
    $q_edit = mysqli_query($conn, "SELECT * FROM fasilitas WHERE id='$id_edit'");
    if (mysqli_num_rows($q_edit) > 0) {
        $edit_data = mysqli_fetch_assoc($q_edit);
    }
}
$is_editing = $edit_data !== null;
$default_preview = "https://images.unsplash.com/photo-1541123437800-1c39af6fcaad";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Facility Management - GiMi Library</title>

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

    .card-bg {
        background-color: #15181e;
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
        background-color: rgba(30, 58, 138, 0.2);
        color: #60a5fa;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    .icon { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }

    .upload-box {
        border: 2px dashed #2d3139;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        color: #94a3b8;
    }
    .upload-box:hover {
        background: #1a1d24;
    }
    .upload-box input {
        display: none;
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
        <a href="fasilitas.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
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
                FACILITY MANAGEMENT
            </div>
            <h2 class="text-2xl font-semibold text-white tracking-tight">Facilities</h2>
        </div>
        
        <?php if($message): ?>
            <div id="notifMessage" class="absolute top-6 right-8 bg-blue-600 text-white px-4 py-2 rounded-md shadow-md text-sm transition-opacity duration-500 border border-blue-400">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="flex-1 flex overflow-hidden">
        
        <!-- LEFT: FACILITY LIST (GRID) -->
        <div class="w-[60%] p-10 overflow-y-auto">
            <h3 class="text-lg font-semibold text-white mb-6">Facility List</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6">
                <?php
                $q_fasilitas = mysqli_query($conn, "SELECT * FROM fasilitas ORDER BY id DESC");
                if ($q_fasilitas && mysqli_num_rows($q_fasilitas) > 0) {
                    while ($fasilitas = mysqli_fetch_assoc($q_fasilitas)) {
                        $imgSrc = !empty($fasilitas['gambar']) ? "upload/".$fasilitas['gambar'] : $default_preview;
                        ?>
                        <div class="card-bg p-4 rounded-xl flex flex-col gap-3 transition-transform hover:-translate-y-1">
                            <div class="h-48 w-full rounded-lg bg-[#111827] overflow-hidden relative">
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="w-full h-full object-cover">
                                <span class="absolute top-2 right-2 px-2 py-1 rounded text-xs font-semibold <?php echo ($fasilitas['status']=='Available') ? 'bg-green-500 text-white' : 'bg-red-500 text-white'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($fasilitas['status'])); ?>
                                </span>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-white font-semibold line-clamp-1" title="<?php echo htmlspecialchars($fasilitas['nama_fasilitas']); ?>">
                                    <?php echo htmlspecialchars($fasilitas['nama_fasilitas']); ?>
                                </h4>
                                <p class="text-xs text-blue-400 mt-1 font-medium"><?php echo htmlspecialchars($fasilitas['kategori']); ?></p>
                                <p class="text-xs text-gray-500 mt-1">Location: <span class="text-gray-300"><?php echo htmlspecialchars($fasilitas['lokasi']); ?></span></p>
                                <p class="text-xs text-gray-500 mt-1">Capacity: <span class="text-gray-300"><?php echo htmlspecialchars($fasilitas['kapasitas']); ?></span></p>
                                <p class="text-xs text-gray-500 mt-1">Booking Schedule: <span class="text-gray-300">09:00 AM - 04:00 PM</span></p>
                            </div>
                            <div class="mt-2 flex gap-2">
                                <a href="?edit=<?php echo urlencode($fasilitas['id']); ?>" class="flex-1 text-center py-2 bg-[#2d3139] hover:bg-[#4b5563] text-white rounded-lg text-xs font-medium transition-colors">Edit</a>
                                <a href="?delete=<?php echo urlencode($fasilitas['id']); ?>" onclick="return confirm('Delete this facility?')" class="flex-1 text-center py-2 bg-red-900/50 hover:bg-red-800 text-red-200 rounded-lg text-xs font-medium transition-colors border border-red-900">Delete</a>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='col-span-full text-gray-500 text-center py-10'>No added facilities available.</div>";
                }
                ?>
            </div>
        </div>

        <!-- RIGHT: FORM INPUT/EDIT -->
        <div class="w-[40%] p-10 border-l border-[#2d3139] overflow-y-auto">

            <h3 class="text-lg font-semibold text-white mb-6"><?php echo $is_editing ? 'Edit Facility' : 'Add New Facility'; ?></h3>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $is_editing ? htmlspecialchars($edit_data['id']) : ''; ?>">
                <input type="hidden" name="old_gambar" value="<?php echo $is_editing ? htmlspecialchars($edit_data['gambar']) : ''; ?>">

                <div class="mb-5">
                    <label class="text-gray-400 text-sm">Facility Name *</label>
                    <input type="text" name="nama" required value="<?php echo $is_editing ? htmlspecialchars($edit_data['nama_fasilitas']) : ''; ?>"
                    class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-5">
                        <label class="text-gray-400 text-sm">Category *</label>
<select name="kategori" class="... text-white">
    <option value="meja_baca" <?php echo ($is_editing && $edit_data['kategori'] == 'meja_baca') ? 'selected' : ''; ?>>Reading room</option>
    <option value="ruang_komputer" <?php echo ($is_editing && $edit_data['kategori'] == 'ruang_komputer') ? 'selected' : ''; ?>>Computer Room</option>
    <option value="ruang_diskusi" <?php echo ($is_editing && $edit_data['kategori'] == 'ruang_diskusi') ? 'selected' : ''; ?>>Discussion Room</option>
</select>
                    </div>
                    <div class="mb-5">
                        <label class="text-gray-400 text-sm">Location</label>
                        <select name="lokasi" class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                            <option value="Lantai 1" <?php echo ($is_editing && $edit_data['lokasi'] == 'Lantai 1') ? 'selected' : ''; ?>>Lantai 1</option>
                            <option value="Lantai 2" <?php echo ($is_editing && $edit_data['lokasi'] == 'Lantai 2') ? 'selected' : ''; ?>>Lantai 2</option>
                            <option value="Lantai 3" <?php echo ($is_editing && $edit_data['lokasi'] == 'Lantai 3') ? 'selected' : ''; ?>>Lantai 3</option>
                        </select>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="text-gray-400 text-sm">Capacity</label>
                    <input type="number" name="kapasitas" min="1" required value="<?php echo $is_editing ? htmlspecialchars($edit_data['kapasitas']) : ''; ?>"
                    class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                </div>

                <div class="mb-5">
                    <label class="text-gray-400 text-sm">Description</label>
                    <textarea name="deskripsi" class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500 min-h-[100px]"><?php echo $is_editing ? htmlspecialchars($edit_data['deskripsi']) : ''; ?></textarea>
                </div>

                <div class="mb-5">
                    <label class="text-gray-400 text-sm">Facility Image</label>
                    <?php if($is_editing && !empty($edit_data['gambar'])): ?>
                        <div class="mt-1 mb-2 text-xs text-blue-400">Current image uploaded</div>
                    <?php endif; ?>
                    <div class="upload-box mt-2">
                        <label class="w-full block">
                            Select image file
                            <input type="file" name="gambar" id="gambarInput" accept="image/*" <?php echo !$is_editing ? 'required' : ''; ?>>
                        </label>
                    </div>
                    <?php 
                        $preview_src = "";
                        if ($is_editing && !empty($edit_data['gambar'])) {
                            $preview_src = "upload/" . $edit_data['gambar'];
                        }
                    ?>
                    <img id="previewImage" src="<?php echo htmlspecialchars($preview_src); ?>" class="mt-3 w-full h-48 object-cover rounded-lg <?php echo empty($preview_src) ? 'hidden' : ''; ?> border border-[#2d3139]">
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit" name="submit" class="flex-1 py-3 rounded-lg bg-blue-600 hover:bg-blue-700 font-semibold transition-colors duration-200">
                        <?php echo $is_editing ? 'Update Facility' : 'Save Facility'; ?>
                    </button>
                    <?php if($is_editing): ?>
                        <a href="fasilitas.php" class="py-3 px-6 rounded-lg bg-[#2d3139] hover:bg-[#4b5563] text-center font-semibold transition-colors duration-200">Cancel</a>
                    <?php endif; ?>
                </div>

            </form>

        </div>
    </div>

</main>

<script>
// Live Preview
document.getElementById("gambarInput").addEventListener("change", function(e){
    const file = e.target.files[0];
    if(file){
        const reader = new FileReader();
        reader.onload = function(e){
            const img = document.getElementById("previewImage");
            img.src = e.target.result;
            img.classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    }
});

// Hide Notification
const notif = document.getElementById('notifMessage');
if (notif) {
    setTimeout(() => {
        notif.style.opacity = '0';
        setTimeout(() => notif.remove(), 500);
    }, 3000);
}
</script>

</body>
</html>