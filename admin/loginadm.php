<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - GiMi Library</title>
</head>
<style>
    body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background: #0d1117;
    color: #fff;
}

/* Centering */
.container {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

/* Login Box */
.login-box {
    background: #161b22;
    padding: 40px;
    border-radius: 12px;
    width: 350px;
    box-shadow: 0 0 30px rgba(0,0,0,0.6);
}

.login-box h2 {
    margin-bottom: 5px;
    text-align: center;
}

/* Input */
.input-group {
    margin-bottom: 20px;
}

.input-group label {
    font-size: 14px;
    color: #c9d1d9;
}

.input-group input {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border-radius: 6px;
    border: none;
    background: #0d1117;
    color: #fff;
    outline: none;
}

/* Button */
.btn-login {
    width: 100%;
    padding: 12px;
    background: #238636;
    border: none;
    border-radius: 6px;
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.btn-login:hover {
    background: #2ea043;
}

/* Footer */
.footer {
    text-align: center;
    font-size: 12px;
    color: #8b949e;
    margin-top: 20px;
}
</style>
<body>

<div class="container">
    <div class="login-box">
        <h2>GiMi Library</h2>

        <form action="process_login.php" method="POST">
            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <p class="footer">© 2026 GiMi System</p>
    </div>
</div>

</body>
</html>