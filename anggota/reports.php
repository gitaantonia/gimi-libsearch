<?php
session_start();
require "../regis/koneksi.php";

// 1. Cek Login
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../regis/login.php");
    exit;
}

// 2. Ambil Data User untuk Verification Section
$id_user_login = $_SESSION['id_pengguna'];
$query_user = $conn->prepare("SELECT id_anggota, nama, nim FROM anggota WHERE id_pengguna = ?");
$query_user->bind_param("s", $id_user_login);
$query_user->execute();
$res_user = $query_user->get_result()->fetch_assoc();
$id_anggota = $res_user['id_anggota'];

// 3. Proses Form saat disubmit
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_report'])) {
    $tipe_report  = $_POST['report_type'];
    $tgl_kejadian = $_POST['incident_date'];
    $item_terkait = mysqli_real_escape_string($conn, $_POST['related_item']);
    $deskripsi    = mysqli_real_escape_string($conn, $_POST['description']);

    // Logika Upload File
    $nama_file_baru = "";
    if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] == 0) {
        $target_dir = "../uploads/reports/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $file_ext = strtolower(pathinfo($_FILES["evidence_file"]["name"], PATHINFO_EXTENSION));
        $nama_file_baru = "REP_" . time() . "_" . uniqid() . "." . $file_ext;
        move_uploaded_file($_FILES["evidence_file"]["tmp_name"], $target_dir . $nama_file_baru);
    }

    // Simpan ke Database
    $id_laporan = bin2hex(random_bytes(16));
    $sql = "INSERT INTO laporan (id_laporan, id_anggota, tipe_laporan, tgl_kejadian, terkait_item, deskripsi, bukti_file) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $id_laporan, $id_anggota, $tipe_report, $tgl_kejadian, $item_terkait, $deskripsi, $nama_file_baru);

    if ($stmt->execute()) {
        $msg = "success";
    } else {
        $msg = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Submit Report - GiMi Library</title>
    <link rel="stylesheet" href="css/reports.css">
</head>

<body>
    <nav class="navbar">
        <div class="nav-logo"><img src="aset/img/logo.png" alt="GiMi Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Home</a></li>
            <li><a href="books.php">Books</a></li>
            <li><a href="facilities.php">Facilities</a></li>
            <li><a href="reports.php" class="active">Reports</a></li>
            <li><a href="profile.php">Profile</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="header-section">
            <h1>Submit Report</h1>
            <p>Use this form to report issues, incidents, or irregular activities to GiMi.</p>
        </div>

        <?php if ($msg == "success"): ?>
            <div class="alert success">Laporan berhasil dikirim!</div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">

            <div class="card">
                <h3><i class="icon-info"></i> Report Information</h3>
                <div class="grid-2">
                    <div class="input-group">
                        <label>Report Type *</label>
                        <select name="report_type" required>
                            <option value="" disabled selected>Select Issue Type</option>
                            <option value="Issue">Issue</option>
                            <option value="Incident">Incident</option>
                            <option value="Irregular Activity">Irregular Activity</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Date & Time of Incident *</label>
                        <input type="datetime-local" name="incident_date" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Related Facility / Book (Optional)</label>
                    <input type="text" name="related_item" placeholder="Search for book title, room name, or equipment ID...">
                </div>
            </div>

            <div class="card">
                <h3><i class="icon-desc"></i> Description</h3>
                <div class="input-group">
                    <label>Report Description *</label>
                    <textarea name="description" maxlength="2000" placeholder="Describe what happened clearly and chronologically..." required></textarea>
                    <small>Please be as specific as possible. <span class="char-count">0/2000 characters</span></small>
                </div>
            </div>

            <div class="card">
                <h3><i class="icon-file"></i> Supporting Evidence</h3>
                <div class="upload-area" id="drop-zone" onclick="document.getElementById('evidence_file').click()">
                    <i class="icon-upload"></i>
                    <div id="upload-text">
                        <p>Click to upload</p>
                        <span>Optional, but helpful. JPG, PNG, PDF (Max 10MB)</span>
                    </div>
                    <div id="file-preview" style="display:none; margin-top: 10px;">
                        <strong id="file-name" style="color: #2e7d32;"></strong>
                        <br>
                        <small>(Click again to change file)</small>
                    </div>
                    <input type="file" name="evidence_file" id="evidence_file" hidden accept="image/*,.pdf">
                </div>
            </div>

            <div class="card">
                <h3><i class="icon-check"></i> Verification</h3>
                <div class="user-profile-box">
                    <div class="user-info">
                        <strong><?= strtoupper($res_user['nama']) ?></strong>
                        <span>NIM: <?= $res_user['nim'] ?></span>
                    </div>
                    <span class="auto-filled">Auto-filled</span>
                </div>
                <label class="checkbox-container">
                    <input type="checkbox" name="verify_check" required>
                    <span class="checkmark"></span>
                    I certify that the information provided in this report is true and accurate...
                </label>

                <div class="footer-form">
                    <p><i class="icon-lock"></i> This report is confidential and only visible to senior staff.</p>
                    <button type="submit" name="submit_report" class="btn-submit">Submit Report <i class="icon-send"></i></button>
                </div>
            </div>
        </form>
    </main>
    <script>
document.getElementById('evidence_file').addEventListener('change', function() {
    const fileInput = this;
    const uploadText = document.getElementById('upload-text');
    const filePreview = document.getElementById('file-preview');
    const fileNameDisplay = document.getElementById('file-name');
    const dropZone = document.getElementById('drop-zone');

    if (fileInput.files && fileInput.files[0]) {
        const fileName = fileInput.files[0].name;
        
        // Sembunyikan instruksi awal, tampilkan nama file
        uploadText.style.display = 'none';
        filePreview.style.display = 'block';
        fileNameDisplay.textContent = "Selected: " + fileName;
        
        // Tambahkan styling agar border berubah warna saat file ada
        dropZone.style.borderColor = "#2e7d32";
        dropZone.style.background = "#f1f8e9";
    }
});

// Tambahan: Character Counter untuk Textarea
const textarea = document.querySelector('textarea[name="description"]');
const charCount = document.querySelector('.char-count');

textarea.addEventListener('input', () => {
    const length = textarea.value.length;
    charCount.textContent = `${length}/2000 characters`;
});
</script>
</body>

</html>