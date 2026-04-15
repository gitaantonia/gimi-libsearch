<?php
include 'koneksi.php';

// Memastikan koneksi sesuai permintaan:
$conn = mysqli_connect("localhost", "root", "", "gimi");

// Cek login
if (!isset($_SESSION['role'])) {
    header("Location: loginadm.php");
    exit;
}

$message = "";

// PROSES DELETE
if (isset($_GET['delete'])) {
    $id_buku = mysqli_real_escape_string($conn, $_GET['delete']);
    // Ambil data gambar utk dihapus opsional
    $q_del = mysqli_query($conn, "SELECT cover_url, foto_pengarang FROM buku WHERE id_buku='$id_buku'");
    if($r_del = mysqli_fetch_assoc($q_del)) {
        if(!empty($r_del['cover_url']) && file_exists("aset/covers/" . $r_del['cover_url'])){
            unlink("aset/covers/" . $r_del['cover_url']);
        }
        if(!empty($r_del['foto_pengarang']) && file_exists("aset/pengarang/" . $r_del['foto_pengarang'])){
            unlink("aset/pengarang/" . $r_del['foto_pengarang']);
        }
    }
    
    $del = mysqli_query($conn, "DELETE FROM buku WHERE id_buku='$id_buku'");
    if($del) {
        $message = "Data successfully deleted";
    }
}

// PROSES CREATE & UPDATE
if (isset($_POST['submit'])) {
    $id_buku = isset($_POST['id_buku']) ? mysqli_real_escape_string($conn, $_POST['id_buku']) : '';
    $is_update = !empty($id_buku);
    
    if (!$is_update) {
        $id_buku = uniqid('BK'); // generate UUID / ID string
    }

    $barcode = mysqli_real_escape_string($conn, $_POST['barcode']);
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $pengarang = mysqli_real_escape_string($conn, $_POST['pengarang']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $tahun_terbit = mysqli_real_escape_string($conn, $_POST['tahun_terbit']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $stok = mysqli_real_escape_string($conn, $_POST['stok']);
    $isbn = mysqli_real_escape_string($conn, $_POST['isbn']);
    $edisi = mysqli_real_escape_string($conn, $_POST['edisi']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    // Folder
    $folder_cover = "aset/covers/";
    $folder_pengarang = "aset/pengarang/";

    if (!is_dir($folder_cover)) mkdir($folder_cover, 0777, true);
    if (!is_dir($folder_pengarang)) mkdir($folder_pengarang, 0777, true);

    $cover_url = "";
    $foto_pengarang = "";

    // Mempertahankan file lama untuk update
    if ($is_update) {
        $cover_url = mysqli_real_escape_string($conn, $_POST['old_cover_url']);
        $foto_pengarang = mysqli_real_escape_string($conn, $_POST['old_foto_pengarang']);
    }

    // Upload Cover
    if (!empty($_FILES['cover_url']['name'])) {
        $file_c = $_FILES['cover_url']['name'];
        $tmp_c = $_FILES['cover_url']['tmp_name'];
        $namaBaru_c = time() . "_" . $file_c;
        if (move_uploaded_file($tmp_c, $folder_cover . $namaBaru_c)) {
            // Hapus file lama jika ada update file baru
            if ($is_update && !empty($cover_url) && file_exists($folder_cover . $cover_url)) {
                unlink($folder_cover . $cover_url);
            }
            $cover_url = $namaBaru_c;
        }
    }

    // Upload Foto Pengarang
    if (!empty($_FILES['foto_pengarang']['name'])) {
        $file_p = $_FILES['foto_pengarang']['name'];
        $tmp_p = $_FILES['foto_pengarang']['tmp_name'];
        $namaBaru_p = time() . "_" . $file_p;
        if (move_uploaded_file($tmp_p, $folder_pengarang . $namaBaru_p)) {
            // Hapus file lama jika ada update file baru
            if ($is_update && !empty($foto_pengarang) && file_exists($folder_pengarang . $foto_pengarang)) {
                unlink($folder_pengarang . $foto_pengarang);
            }
            $foto_pengarang = $namaBaru_p;
        }
    }

    if ($is_update) {
        $query = "UPDATE buku SET 
            barcode='$barcode', 
            judul='$judul', 
            pengarang='$pengarang', 
            kategori='$kategori', 
            tahun_terbit='$tahun_terbit', 
            status='$status', 
            stok='$stok', 
            cover_url='$cover_url', 
            foto_pengarang='$foto_pengarang', 
            isbn='$isbn', 
            edisi='$edisi', 
            deskripsi='$deskripsi' 
            WHERE id_buku='$id_buku'";
        if (mysqli_query($conn, $query)) {
            $message = "Data successfully updated";
        }
    } else {
        $query = "INSERT INTO buku (id_buku, barcode, judul, pengarang, kategori, tahun_terbit, status, stok, cover_url, foto_pengarang, isbn, edisi, deskripsi) 
                  VALUES ('$id_buku', '$barcode', '$judul', '$pengarang', '$kategori', '$tahun_terbit', '$status', '$stok', '$cover_url', '$foto_pengarang', '$isbn', '$edisi', '$deskripsi')";
        if (mysqli_query($conn, $query)) {
            $message = "Data successfully added";
        }
    }
}

// Data untuk form Edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = mysqli_real_escape_string($conn, $_GET['edit']);
    $q_edit = mysqli_query($conn, "SELECT * FROM buku WHERE id_buku='$id_edit'");
    if (mysqli_num_rows($q_edit) > 0) {
        $edit_data = mysqli_fetch_assoc($q_edit);
    }
}
$is_editing = $edit_data !== null;
$default_cover = "https://images.unsplash.com/photo-1524758631624-e2822e304c36";
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Catalogue - GiMi Library</title>

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
        <a href="fasilitas.php" class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
            <svg class="icon" viewBox="0 0 24 24"><path d="M3 21h18"></path><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path><path d="M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2-2v4"></path></svg>
            <span class="font-medium">Facility</span>
        </a>
        <a href="book_catalogue.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1">
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
<main class="flex-1 flex flex-col overflow-hidden bg-[#0b0d10] relative">

    <!-- Header -->
    <header class="px-8 py-6 border-b border-[#2d3139] flex justify-between items-end shrink-0">
        <div>
            <div class="text-xs text-gray-400 mb-1 font-medium tracking-wide">
                CATALOGUE MANAGEMENT
            </div>
            <h2 class="text-2xl font-semibold text-white tracking-tight">Book Catalogue</h2>
        </div>
        
        <?php if($message): ?>
            <div id="notifMessage" class="absolute top-6 right-8 bg-blue-600 text-white px-4 py-2 rounded-md shadow-md text-sm transition-opacity duration-500 border border-blue-400">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="flex-1 flex overflow-hidden">
        
        <!-- KIRI: DAFTAR BUKU (GRID/CARD) -->
        <div class="w-[60%] p-10 overflow-y-auto">
            <h3 class="text-lg font-semibold text-white mb-6">Book List</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php
                // Get all books or handle error if table doesn't exist to avoid ugly UI block
                $q_buku = @mysqli_query($conn, "SELECT * FROM buku ORDER BY id_buku DESC");
                if ($q_buku) {
                    while ($buku = mysqli_fetch_assoc($q_buku)) {
                        $cover = !empty($buku['cover_url']) ? "aset/covers/".$buku['cover_url'] : $default_cover;
                        ?>
                        <div class="card-bg p-4 rounded-xl flex flex-col gap-3 transition-transform hover:-translate-y-1">
                            <div class="h-48 w-full rounded-lg bg-[#111827] overflow-hidden relative">
                                <img src="<?php echo htmlspecialchars($cover); ?>" class="w-full h-full object-cover">
                                <span class="absolute top-2 right-2 px-2 py-1 rounded text-xs font-semibold <?php echo ($buku['status']=='tersedia') ? 'bg-green-500 text-white' : 'bg-red-500 text-white'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($buku['status'] == 'tersedia' ? 'available' : 'borrowed')); ?>
                                </span>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-white font-semibold line-clamp-2" title="<?php echo htmlspecialchars($buku['judul']); ?>">
                                    <?php echo htmlspecialchars($buku['judul']); ?>
                                </h4>
                                <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($buku['pengarang']); ?></p>
                                <p class="text-xs text-gray-500 mt-1">Cat: <?php echo htmlspecialchars($buku['kategori']); ?></p>
                                <p class="text-xs text-gray-500 mt-1">Stock: <?php echo htmlspecialchars($buku['stok']); ?></p>
                            </div>
                            <div class="mt-2 flex gap-2">
                                <a href="?edit=<?php echo urlencode($buku['id_buku']); ?>" class="flex-1 text-center py-2 bg-[#2d3139] hover:bg-[#4b5563] text-white rounded-lg text-xs font-medium transition-colors">Edit</a>
                                <a href="?delete=<?php echo urlencode($buku['id_buku']); ?>" onclick="return confirm('Delete this book?')" class="flex-1 text-center py-2 bg-red-900/50 hover:bg-red-800 text-red-200 rounded-lg text-xs font-medium transition-colors border border-red-900">Delete</a>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='col-span-full text-gray-500 text-center py-10'>No book data available or table not found.</div>";
                }
                ?>
            </div>
        </div>

        <!-- KANAN: FORM INPUT/EDIT -->
        <div class="w-[40%] p-10 border-l border-[#2d3139] overflow-y-auto">

            <h3 class="text-lg font-semibold text-white mb-6"><?php echo $is_editing ? 'Edit Book' : 'Add New Book'; ?></h3>

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="id_buku" value="<?php echo $is_editing ? htmlspecialchars($edit_data['id_buku']) : ''; ?>">
                <input type="hidden" name="old_cover_url" value="<?php echo $is_editing ? htmlspecialchars($edit_data['cover_url']) : ''; ?>">
                <input type="hidden" name="old_foto_pengarang" value="<?php echo $is_editing ? htmlspecialchars($edit_data['foto_pengarang']) : ''; ?>">

                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-5">
                        <label class="text-gray-400 text-sm">Barcode *</label>
                        <input type="text" name="barcode" required value="<?php echo $is_editing ? htmlspecialchars($edit_data['barcode']) : ''; ?>"
                        class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="mb-5">
                        <label class="text-gray-400 text-sm">ISBN</label>
                        <input type="text" name="isbn" value="<?php echo $is_editing ? htmlspecialchars($edit_data['isbn']) : ''; ?>"
                        class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div class="mb-5">
                    <label class="text-gray-400 text-sm">Title *</label>
                    <input type="text" name="judul" required value="<?php echo $is_editing ? htmlspecialchars($edit_data['judul']) : ''; ?>"
                    class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-5">
                        <label class="text-gray-400 text-sm">Author *</label>
                        <input type="text" name="pengarang" required value="<?php echo $is_editing ? htmlspecialchars($edit_data['pengarang']) : ''; ?>"
                        class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="mb-5">
                        <label class="text-gray-400 text-sm">Category *</label>
                        <input type="text" name="kategori" required value="<?php echo $is_editing ? htmlspecialchars($edit_data['kategori']) : ''; ?>" list="kategori_list"
                        class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                        <datalist id="kategori_list">
                            <option value="Fiction">
                            <option value="Non-Fiction">
                            <option value="Science & Technology">
                            <option value="History">
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="mb-5">
                        <label class="text-gray-400 text-sm">Publication Year *</label>
                        <input type="number" name="tahun_terbit" required value="<?php echo $is_editing ? htmlspecialchars($edit_data['tahun_terbit']) : ''; ?>"
                        class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="mb-5">
                        <label class="text-gray-400 text-sm">Stock *</label>
                        <input type="number" name="stok" required value="<?php echo $is_editing ? htmlspecialchars($edit_data['stok']) : ''; ?>"
                        class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="mb-5">
                        <label class="text-gray-400 text-sm">Status *</label>
                        <select name="status" class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                            <option value="tersedia" <?php echo ($is_editing && $edit_data['status'] == 'tersedia') ? 'selected' : ''; ?>>Available</option>
                            <option value="dipinjam" <?php echo ($is_editing && $edit_data['status'] == 'dipinjam') ? 'selected' : ''; ?>>Borrowed</option>
                        </select>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="text-gray-400 text-sm">Edition</label>
                    <input type="text" name="edisi" value="<?php echo $is_editing ? htmlspecialchars($edit_data['edisi']) : ''; ?>"
                    class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500">
                </div>

                <div class="mb-5">
                    <label class="text-gray-400 text-sm">Description</label>
                    <textarea name="deskripsi" class="w-full mt-2 p-3 rounded-lg bg-[#0b0d10] border border-[#2d3139] text-white focus:outline-none focus:border-blue-500 min-h-[100px]"><?php echo $is_editing ? htmlspecialchars($edit_data['deskripsi']) : ''; ?></textarea>
                </div>

                <div class="mb-5">
                    <label class="text-gray-400 text-sm">Book Cover</label>
                    <?php if($is_editing && !empty($edit_data['cover_url'])): ?>
                        <div class="mt-1 mb-2 text-xs text-blue-400">Current cover uploaded</div>
                    <?php endif; ?>
                    <div class="upload-box mt-2">
                        <label class="w-full block">
                            Select cover image
                            <input type="file" name="cover_url" id="coverInput" accept="image/*">
                        </label>
                    </div>
                    <img id="previewCover" class="mt-3 w-32 h-auto rounded-lg hidden border border-[#2d3139]">
                </div>

                <div class="mb-6">
                    <label class="text-gray-400 text-sm">Author Photo</label>
                    <?php if($is_editing && !empty($edit_data['foto_pengarang'])): ?>
                        <div class="mt-1 mb-2 text-xs text-blue-400">Current photo uploaded</div>
                    <?php endif; ?>
                    <div class="upload-box mt-2">
                        <label class="w-full block">
                            Select author photo
                            <input type="file" name="foto_pengarang" id="pengarangInput" accept="image/*">
                        </label>
                    </div>
                    <img id="previewPengarang" class="mt-3 w-24 h-24 object-cover rounded-full hidden border border-[#2d3139]">
                </div>

                <div class="flex gap-3 mt-4">
                    <button type="submit" name="submit" class="flex-1 py-3 rounded-lg bg-blue-600 hover:bg-blue-700 font-semibold transition-colors duration-200">
                        <?php echo $is_editing ? 'Update Book' : 'Save New Book'; ?>
                    </button>
                    <?php if($is_editing): ?>
                        <a href="book_catalogue.php" class="py-3 px-6 rounded-lg bg-[#2d3139] hover:bg-[#4b5563] text-center font-semibold transition-colors duration-200">Cancel</a>
                    <?php endif; ?>
                </div>

            </form>

        </div>
    </div>

</main>

<script>
// Fungsi untuk memunculkan preview gambar secara dinamis
function setupPreview(inputId, previewId) {
    document.getElementById(inputId).addEventListener("change", function(e){
        const file = e.target.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(e){
                const img = document.getElementById(previewId);
                img.src = e.target.result;
                img.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    });
}
setupPreview("coverInput", "previewCover");
setupPreview("pengarangInput", "previewPengarang");

// Fungsi untuk menghilangkan notifikasi secara otomatis setelah 3 detik
const notif = document.getElementById('notifMessage');
if (notif) {
    setTimeout(() => {
        notif.style.opacity = '0';
        setTimeout(() => notif.remove(), 500); // Tunggu transisi beres, hapus dari DOM
    }, 3000);
}
</script>

</body>
</html>
