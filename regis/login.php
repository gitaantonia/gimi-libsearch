<?php
session_start();
require "koneksi.php";

$error   = "";
$success = "";

// ============================================================
// PROSES REGISTER
// ============================================================
if (isset($_POST["register"])) {
    $nama     = trim($_POST["nama"]);
    $email    = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($nama) || empty($email) || empty($password)) {
        $error = "Semua field wajib diisi.";
    } else {
        // Cek email duplikat
        $stmt = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->get_result()->num_rows > 0
            ? $error = "Email sudah terdaftar."
            : null;
        $stmt->close();

        if (empty($error)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $roleDefault    = 2;      // 2 = anggota biasa
            $status         = "Aktif";

            $stmt = $conn->prepare("
                INSERT INTO pengguna (nama, email, password_hash, role, aktif)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssis", $nama, $email, $hashedPassword, $roleDefault, $status);

            if ($stmt->execute()) {
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Terjadi kesalahan saat mendaftar.";
            }
            $stmt->close();
        }
    }
}

// ============================================================
// PROSES LOGIN
// ============================================================
if (isset($_POST["login"])) {
    $email    = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        $error = "Email dan password wajib diisi.";
    } else {
        $stmt = $conn->prepare("
            SELECT id_pengguna, nama, password_hash, role, aktif
            FROM pengguna
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = "Email tidak terdaftar.";
        } elseif ((int)$user["aktif"] !== 0) {
            $error = "Akun kamu tidak aktif. Hubungi admin.";
        } elseif (!password_verify($password, $user["password_hash"])) {
            $error = "Password salah.";
        } else {
            // Login sukses — set session
            session_regenerate_id(true);

            $_SESSION["id_pengguna"] = $user["id_pengguna"];
            $_SESSION["nama"]        = $user["nama"];
            $_SESSION["role"]        = $user["role"];

            // Redirect berdasarkan role
            if ((int)$user["role"] === 1) {
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../anggota/dashboard.php");
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GiMi Library — Login & Register</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(120deg, #9bbbd4, #3f7aa3);
        }

        .container {
            width: 1000px;
            height: 550px;
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            backdrop-filter: blur(15px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            background: rgba(255, 255, 255, 0.08);
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
            padding: 50px 70px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-box h2 {
            font-family: 'Playfair Display', serif;
            font-size: 38px;
            font-weight: 600;
            margin-bottom: 24px;
            text-align: center;
            color: white;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group input {
            width: 100%;
            padding: 10px 4px;
            border: none;
            border-bottom: 1.5px solid rgba(255, 255, 255, 0.5);
            background: transparent;
            font-size: 15px;
            outline: none;
            color: #ffffff;
            transition: border-color .2s;
        }

        .input-group input:focus {
            border-bottom-color: #ffffff;
        }

        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.55);
        }

        button[type="submit"] {
            width: 100%;
            padding: 13px;
            border-radius: 30px;
            border: none;
            background: linear-gradient(180deg, #2c4c8a, #1f3564);
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: opacity .2s;
        }

        button[type="submit"]:hover {
            opacity: .85;
        }

        .switch {
            margin-top: 20px;
            font-size: 13px;
            cursor: pointer;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            transition: color .2s;
        }

        .switch:hover {
            color: #fff;
        }

        .message-error {
            margin-bottom: 12px;
            font-size: 13px;
            color: #ffb3b3;
            text-align: center;
            background: rgba(255, 0, 0, 0.12);
            border-radius: 8px;
            padding: 8px 10px;
        }

        .message-success {
            margin-bottom: 12px;
            font-size: 13px;
            color: #b3ffcc;
            text-align: center;
            background: rgba(0, 200, 80, 0.12);
            border-radius: 8px;
            padding: 8px 10px;
        }

        /* OVERLAY / SLIDING IMAGE */
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 50%;
            height: 100%;
            background: linear-gradient(120deg, #3f7aa3, #9bbbd4);
            display: flex;
            justify-content: center;
            align-items: center;
            transition: 0.8s ease-in-out;
            border-radius: 30px;
            z-index: 10;
        }

        .overlay img {
            max-width: 420px;
            width: 90%;
        }

        .container.active .overlay {
            transform: translateX(100%);
        }
    </style>
</head>

<body>

    <div class="container" id="container">

        <!-- FORM AREA -->
        <div class="form-container">

            <!-- ===== LOGIN ===== -->
            <div class="form-box">
                <h2>Sign In</h2>

                <?php if (!empty($error) && isset($_POST["login"])): ?>
                    <div class="message-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="input-group">
                        <input type="email" name="email"
                            placeholder="Email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <button type="submit" name="login">Sign In</button>
                </form>

                <div class="switch" onclick="toggleForm()">Create an account?</div>
            </div>

            <!-- ===== REGISTER ===== -->
            <div class="form-box">
                <h2>Sign Up</h2>

                <?php if (!empty($error) && isset($_POST["register"])): ?>
                    <div class="message-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="message-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="input-group">
                        <input type="text" name="nama"
                            placeholder="Full Name"
                            value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                            required>
                    </div>
                    <div class="input-group">
                        <input type="email" name="email"
                            placeholder="Email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <button type="submit" name="register">Sign Up</button>
                </form>

                <div class="switch" onclick="toggleForm()">Already have an account?</div>
            </div>

        </div>

        <!-- SLIDING OVERLAY -->
        <div class="overlay">
            <img id="overlayImage" src="aset/awal.png" alt="GiMi Library">
        </div>

    </div>

    <script>
        // Kalau registrasi sukses atau ada error register, langsung tampilkan panel register
        <?php if (isset($_POST["register"])): ?>
            document.getElementById("container").classList.add("active");
        <?php endif; ?>

        function toggleForm() {
            document.getElementById("container").classList.toggle("active");
        }
    </script>

</body>

</html>