<?php
require_once 'includes/config.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isLoggedIn()) {
    // Cek role untuk redirect yang sesuai
    if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
        redirect('admin/index.php');
    } else {
        redirect('index.php');
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $db->escape($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // Cek apakah ada redirect URL yang disimpan (misal dari halaman pesan kursi)
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect_url = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirect_url);
                } else {
                    if ($user['role'] == 'admin') {
                        redirect('admin/index.php');
                    } else {
                        redirect('index.php');
                    }
                }
            } else {
                $error = "Username atau password salah!";
            }
        } else {
            $error = "Username atau password salah!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - MyKisah</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #0f172a;
        --secondary: #1e293b;
        --accent: #CEF3F8;
        --text-light: #94a3b8;
        --white: #ffffff;
        --border: rgba(255,255,255,0.1);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

    body {
        background: var(--primary);
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        color: var(--white);
    }

    /* Background Decoration */
    body::before {
        content: '';
        position: absolute;
        top: -10%;
        left: -10%;
        width: 50%;
        height: 50%;
        background: radial-gradient(circle, rgba(206, 243, 248, 0.1) 0%, rgba(15, 23, 42, 0) 70%);
        z-index: -1;
    }

    .card {
        background: var(--secondary);
        padding: 40px;
        border-radius: 24px;
        box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.5);
        border: 1px solid var(--border);
        width: 100%;
        max-width: 380px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .logo {
        font-size: 24px;
        font-weight: 800;
        color: var(--accent);
        margin-bottom: 8px;
        display: inline-block;
    }

    h2 {
        font-size: 18px;
        font-weight: 500;
        color: var(--text-light);
        margin-bottom: 30px;
    }

    .form-group {
        position: relative;
        margin-bottom: 20px;
        text-align: left;
    }

    input {
        width: 100%;
        padding: 14px 45px 14px 20px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.03);
        color: var(--white);
        font-size: 14px;
        outline: none;
        transition: all 0.3s;
    }

    input:focus {
        border-color: var(--accent);
        background: rgba(255, 255, 255, 0.05);
        box-shadow: 0 0 0 4px rgba(206, 243, 248, 0.1);
    }

    input::placeholder {
        color: rgba(255, 255, 255, 0.3);
    }

    .icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 18px;
        opacity: 0.5;
        pointer-events: none;
    }

    button {
        background: var(--accent);
        color: var(--primary);
        border: none;
        padding: 14px 0;
        width: 100%;
        border-radius: 12px;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        margin-top: 10px;
    }

    button:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(206, 243, 248, 0.2);
    }

    p {
        font-size: 13px;
        margin-top: 24px;
        color: var(--text-light);
    }

    a {
        color: var(--accent);
        text-decoration: none;
        font-weight: 600;
        transition: opacity 0.2s;
    }

    a:hover {
        opacity: 0.8;
        text-decoration: underline;
    }

    .alert {
        font-size: 13px;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: left;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
</style>
</head>
<body>
<div class="card">
    <div class="logo">MyKisah</div>
    <h2>Selamat Datang Kembali</h2>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= $success ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <input type="text" name="username" placeholder="Username atau Email" required>
            <span class="icon">👤</span>
        </div>
        <div class="form-group">
            <input type="password" name="password" placeholder="Password" required>
            <span class="icon">🔒</span>
        </div>
        <button type="submit" name="login">Masuk</button>
    </form>
    <p>Belum punya akun? <a href="register.php">Daftar Sekarang</a></p>
    <p><a href="index.php" style="color: var(--text-light); font-size: 12px; font-weight: 400;">← Kembali ke Beranda</a></p>
</div>
</body>
</html>