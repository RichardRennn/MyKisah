<?php
require_once 'includes/config.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('admin/index.php');
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $username = $db->escape($_POST['username']);
    $email = $db->escape($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = $db->escape($_POST['phone']);
    $full_name = $db->escape($_POST['full_name']);

    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "Semua field harus diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Username atau email sudah terdaftar!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (username, email, password, full_name, phone) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $db->prepare($insert_sql);
            $insert_stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $phone);

            if ($insert_stmt->execute()) {
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Terjadi kesalahan. Silakan coba lagi.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - MyKisah</title>
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
        min-height: 100vh;
        color: var(--white);
        padding: 20px;
        /* PERBAIKAN SCROLLBAR: */
        overflow-x: hidden; /* Hilangkan scroll horizontal */
        position: relative;
    }

    /* Background Decoration */
    body::before {
        content: '';
        /* PERBAIKAN SCROLLBAR: Gunakan fixed agar tidak menambah dimensi halaman */
        position: fixed; 
        bottom: -10%;
        right: -10%;
        width: 50%;
        height: 50%;
        background: radial-gradient(circle, rgba(206, 243, 248, 0.08) 0%, rgba(15, 23, 42, 0) 70%);
        z-index: -1;
        pointer-events: none; /* Agar tidak mengganggu klik */
    }

    .card {
        background: var(--secondary);
        padding: 30px; /* Reduced padding */
        border-radius: 24px;
        box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.5);
        border: 1px solid var(--border);
        width: 100%;
        max-width: 500px; 
        text-align: center;
        position: relative;
        overflow: hidden;
        z-index: 1; /* Pastikan card di atas background */
    }

    .logo {
        font-size: 22px; /* Slightly smaller */
        font-weight: 800;
        color: var(--accent);
        margin-bottom: 4px;
        display: inline-block;
    }

    h2 {
        font-size: 16px;
        font-weight: 500;
        color: var(--text-light);
        margin-bottom: 20px; /* Reduced margin */
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px; /* Reduced gap */
    }

    .form-group {
        position: relative;
        margin-bottom: 12px; /* Reduced margin */
        text-align: left;
    }
    
    .form-group.full-width {
        grid-column: span 2;
    }

    input {
        width: 100%;
        padding: 12px 40px 12px 16px; /* Reduced padding */
        border: 1px solid var(--border);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.03);
        color: var(--white);
        font-size: 13px;
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
        font-size: 16px;
        opacity: 0.5;
        pointer-events: none;
    }

    button {
        background: var(--accent);
        color: var(--primary);
        border: none;
        padding: 12px 0; /* Reduced padding */
        width: 100%;
        border-radius: 12px;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        margin-top: 8px;
    }

    button:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(206, 243, 248, 0.2);
    }

    p {
        font-size: 12px;
        margin-top: 16px;
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
        font-size: 12px;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 15px;
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

    @media (max-width: 480px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        .form-group.full-width {
            grid-column: span 1;
        }
    }
</style>
</head>
<body>
<div class="card">
    <div class="logo">MyKisah</div>
    <h2>Buat Akun Baru</h2>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= $success ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group full-width">
            <input type="text" name="full_name" placeholder="Nama Lengkap" required>
            <span class="icon">📝</span>
        </div>
        
        <div class="form-grid">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
                <span class="icon">👤</span>
            </div>
            <div class="form-group">
                <input type="tel" name="phone" placeholder="No. Telepon" required>
                <span class="icon">📞</span>
            </div>
        </div>

        <div class="form-group full-width">
            <input type="email" name="email" placeholder="Email" required>
            <span class="icon">📧</span>
        </div>
        
        <div class="form-grid">
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
                <span class="icon">🔒</span>
            </div>
            <div class="form-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <span class="icon">🔑</span>
            </div>
        </div>
        
        <button type="submit" name="register">Daftar Sekarang</button>
    </form>
    <p>Sudah punya akun? <a href="login.php">Masuk</a></p>
</div>
</body>
</html>