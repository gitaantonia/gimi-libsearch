<?php
// session_start();

require "koneksi.php";

$error = "";
$success = "";

/* =======================
   PROSES REGISTER
======================= */
if (isset($_POST["register"])) {

    $nama = trim($_POST["nama"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($nama) || empty($email) || empty($password)) {
        $error = "Semua field wajib diisi.";
    } else {

        $stmt = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email sudah terdaftar.";
        } else {

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $roleDefault = 2;
            $status = "Aktif";

            $stmt = $conn->prepare("INSERT INTO pengguna (nama, email, password_hash, role, aktif) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $nama, $email, $hashedPassword, $roleDefault, $status);

            if ($stmt->execute()) {
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Terjadi kesalahan.";
            }
        }
    }
}

/* =======================
   PROSES LOGIN
======================= */
if (isset($_POST["login"])) {

    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT * FROM pengguna WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user["password_hash"])) {

        session_regenerate_id(true);

        $_SESSION["id_pengguna"] = $user["id_pengguna"];
        $_SESSION["nama"] = $user["nama"];
        $_SESSION["role"] = $user["id_role"];

        header("Location: dashboard.php");
        exit;

    } else {
        $error = "Email atau password salah.";
    }
}
?>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }

body {
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(120deg,#9bbbd4,#3f7aa3);
}

.container {
    width: 1000px;
    height: 550px;
    position: relative;
    overflow: hidden;
    border-radius: 30px;
    backdrop-filter: blur(15px);
    box-shadow: 0 25px 50px rgba(0,0,0,0.25);
    background: rgba(255,255,255,0.08);
}

/* FORM AREA */
.form-container {
    position: absolute;
    
    width: 100%;
    height: 100%;
    display: flex;
}

.form-box {
    width: 50%;
    padding: 70px;
    color: white;
}

.form-box h2 {
font-family: 'Playfair Display', serif;
    font-size: 42px;
    font-weight: 600;
    margin-bottom: 30px;
    text-align: center;
    color: white;
}

.input-group {
    margin-bottom: 25px;
}

/* 1. Mengubah judul Sign In & Sign Up */
.form-box h2 {
    font-size: 38px;
    margin-bottom: 40px;
    text-align: center;
}

/* 2. Mengubah teks input saat diketik dan garis bawah */
.input-group input {
    width: 100%;
    padding: 10px;
    border: none;
    border-bottom: 1px solid black; /* Garis lebih tebal dan biru gelap */
    background: transparent;
    font-size: 16px;
    outline: none;
    color: #ffffff; /* Warna teks saat diketik */
}

/* 3. Mengubah warna teks Placeholder (Email, Password, dll) */
.input-group input::placeholder {
    color: rgba(24, 47, 95, 0.7); /* Biru gelap dengan sedikit transparansi */
}

/* 4. Mengubah teks switch (Already have an account?) */
.switch {
    margin-top: 25px;
    font-size: 14px;
    cursor: pointer;
    text-align: center;
    font-weight: 500;
}

/* 5. Opsional: Mengubah warna pesan error/sukses agar kontras */
.message {
    margin-bottom: 15px;
    font-size: 14px;
    color: #d9534f; /* Tetap merah untuk error atau sesuaikan */
    text-align: center;
}

button {
    width: 100%;
    padding: 14px;
    border-radius: 30px;
    border: none;
    background: linear-gradient(180deg, #2c4c8a, #1f3564);
    color: white;
    font-size: 16px;
    cursor: pointer;
    margin-top: 20px;
}

.switch {
    margin-top: 25px;
    font-size: 14px;
    cursor: pointer;
    text-align: center;
}

/* OVERLAY IMAGE */
.overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 50%;
    height: 100%;
    background: linear-gradient(120deg,#3f7aa3,#9bbbd4);
    display: flex;
    justify-content: center;
    align-items: center;
    transition: 0.8s ease-in-out;
    border-radius: 30px;
    z-index: 10;
}

.overlay img {
    max-width: 500px;
}

/* ACTIVE STATE */
.container.active .overlay {
    transform: translateX(100%);
}

.message {
    margin-bottom: 15px;
    font-size: 14px;
}
</style>

<div class="container" id="container">

    <!-- FORM AREA -->
    <div class="form-container">

        <!-- LOGIN -->
        <div class="form-box">
            <h2>Sign In</h2>

            <?php if($error): ?>
                <div class="message"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>

                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <button name="login">Sign In</button>
            </form>

            <div class="switch" onclick="toggle()">Create an account?</div>
        </div>

        <!-- REGISTER -->
        <div class="form-box">
            <h2>Sign Up</h2>

            <?php if($success): ?>
                <div class="message"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <input type="text" name="nama" placeholder="Name" required>
                </div>

                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>

                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <button name="register">Sign Up</button>
            </form>

            <div class="switch" onclick="toggle()">Already have an account?</div>
        </div>

    </div>

    <!-- SLIDING IMAGE -->
    <div class="overlay">
        <img id="overlayImage" src="aset/awal.png" alt="Auth Image">
    </div>

</div>

<script>
function toggle() {
    const container = document.getElementById("container");
    const image = document.getElementById("overlayImage");

    container.classList.toggle("active");

    if (container.classList.contains("active")) {
        image.src = "aset/awal.png";
    } else {
        image.src = "aset/awal.png";
    }
}
</script>