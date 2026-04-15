<?php
//session_start();

require "koneksi.php";

$error = "";

// cek jika tombol login ditekan
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
        $_SESSION["role"] = $user["role"];

        //  redirect berdasarkan role
        if ($user["role"] == "pustakawan") {
            header("Location: dashboard_adm.php");
        } else if ($user["role"] == "anggota") {
            header("Location: regis/dashboard.php");
        } else {
            $error = "Role tidak dikenali.";
        }

        exit;

    } else {
        $error = "Email atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - GiMi Library</title>
</head>

<style>
body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background: #192338;
    color: #fff;
}

.container {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

.login-box {
    background: #1E2E4F;
    padding: 40px;
    border-radius: 12px;
    width: 350px;
    box-shadow: 0 0 30px rgba(0,0,0,0.6);
}

.login-box h2 {
    text-align: center;
}

.input-group {
    margin-bottom: 20px;
}

.input-group label {
    font-size: 14px;
}

.input-group input {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: none;
    background: #192338;
    color: #fff;
}

.btn-login {
    width: 100%;
    padding: 12px;
    background: #8FB3E2;
    border: none;
    border-radius: 6px;
    color: white;
    cursor: pointer;
}

.btn-login:hover {
    background: #8FB3E2;
}

.error {
    color: red;
    text-align: center;
    margin-bottom: 10px;
}

.footer {
    text-align: center;
    font-size: 12px;
    margin-top: 20px;
}
</style>

<body>

<div class="container">
    <div class="login-box">
        <h2>GiMi Library</h2>

        <!-- tampilkan error -->
        <?php if ($error): ?>
            <p class="error"><?= $error; ?></p>
        <?php endif; ?>

        <form method="POST" action="proses_login.php">
            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <!--  FIX: tambahin name login -->
            <button type="submit" name="login" class="btn-login">Login</button>
        </form>

        <p class="footer">© 2026 GiMi LibSearch</p>
    </div>
</div>

</body>
</html>