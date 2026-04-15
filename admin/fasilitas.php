<?php
include 'koneksi.php';

// Cek login
if (!isset($_SESSION['role'])) {
    header("Location: loginadm.php");
    exit;
}

// default preview
$preview = "https://images.unsplash.com/photo-1524758631624-e2822e304c36";

if (isset($_POST['submit'])) {

    $nama = $_POST['nama'];
    $kategori = $_POST['kategori'];
    $deskripsi = $_POST['deskripsi'];

    $file = $_FILES['gambar']['name'];
    $tmp = $_FILES['gambar']['tmp_name'];
    $folder = "upload/";

    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    if (!empty($file)) {

        $namaBaru = time() . "_" . $file;

        if (move_uploaded_file($tmp, $folder . $namaBaru)) {

            $preview = $folder . $namaBaru;

            mysqli_query($conn, "INSERT INTO fasilitas 
            (nama_fasilitas, kategori, deskripsi, gambar)
            VALUES ('$nama','$kategori','$deskripsi','$namaBaru')");

        } else {
            echo "Gagal upload gambar!";
        }

    } else {
        echo "Pilih gambar!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Fasilitas - GiMi Library</title>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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

/* preview panel */
.card-image {
    width: 100%;
    height: 350px;
    border-radius: 20px;
    overflow: hidden;
    background: #111827;
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* upload */
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
        <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            <span class="font-medium">Book Requests</span>
        </a>
        <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span class="font-medium">Facility Request</span>
        </a>
        <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
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
<main class="flex-1 flex flex-col overflow-hidden bg-[#0b0d10]">

    <!-- Header -->
    <header class="px-8 py-6 border-b border-[#2d3139] flex justify-between items-end shrink-0">
        <div>
            <div class="text-xs text-gray-400 mb-1 font-medium tracking-wide">
                FACILITY MANAGEMENT
            </div>
            <h2 class="text-2xl font-semibold text-white tracking-tight">Add New Facility</h2>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
        <!-- LEFT PREVIEW -->
        <div class="w-[60%] p-10 overflow-y-auto">
            <h3 class="text-lg font-semibold text-white">Preview</h3>
            <p class="text-sm text-gray-400 mb-4">Library Facility Preview</p>

            <div class="card-image mt-2">
                <img id="previewImage" src="<?php echo $preview; ?>">
            </div>
        </div>

        <!-- RIGHT FORM -->
        <div class="w-[40%] p-10 border-l border-[#2d3139] overflow-y-auto">

            <h3 class="text-lg font-semibold text-white mb-6">Facility Details</h3>

        <form method="POST" enctype="multipart/form-data">

            <div class="mb-5">
                <label class="text-gray-400 text-sm">Image</label>
                <div class="upload-box mt-2">
                    <label>
                        Click to upload
                        <input type="file" name="gambar" id="gambarInput" required>
                    </label>
                </div>
            </div>

            <div class="mb-5">
                <label class="text-gray-400 text-sm">Facility Name</label>
                <input type="text" name="nama" required
                class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white">
            </div>

            <div class="mb-5">
                <label class="text-gray-400 text-sm">Category</label>
                <select name="kategori"
                class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white">
                    <option>Reading room</option>
                    <option>Computer Room</option>
                    <option>Discussion Room</option>
                </select>
            </div>

            <div class="mb-5">
                <label class="text-gray-400 text-sm">Description</label>
                <textarea name="deskripsi"
                class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white"></textarea>
            </div>

            <button name="submit"
            class="w-full py-3 rounded-lg bg-blue-600 hover:bg-blue-700">
                Save Facility
            </button>

        </form>

        </div>
    </div>

</main>

<script>
// LIVE PREVIEW
document.getElementById("gambarInput").addEventListener("change", function(e){
    const file = e.target.files[0];
    if(file){
        const reader = new FileReader();
        reader.onload = function(e){
            document.getElementById("previewImage").src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});
</script>

</body>
</html>