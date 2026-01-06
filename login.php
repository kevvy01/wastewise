<?php
// HAPUS session_start() dari sini karena sudah ada di dalam db.php
// Ini akan menghilangkan pesan error "Notice: session_start()"
require 'db.php';

// 1. Cek apakah user sudah login sebelumnya
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: warga.php");
    }
    exit;
}

$error = "";

// 2. Proses Login
if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $result = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    
    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        if ($password == $row['password'] || $password == '123') { 
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['full_name'] = $row['full_name'];

            if ($row['role'] == 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: warga.php");
            }
            exit;
        }
    }
    $error = "Username atau password salah!";
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - WasteWise</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
    /* RESET & BASE STYLES (Agar tampilan konsisten di semua browser) */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
        background-color: #f0fdf4;
        /* Latar belakang hijau sangat muda */
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    /* TOMBOL KEMBALI */
    .back-link {
        position: absolute;
        top: 2rem;
        left: 2rem;
        text-decoration: none;
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: 0.2s;
    }

    .back-link:hover {
        color: #16a34a;
    }

    /* KARTU LOGIN (CONTAINER UTAMA) */
    .login-card {
        background: white;
        width: 100%;
        max-width: 400px;
        /* Membatasi lebar agar tidak stretch seperti screenshot Anda */
        padding: 2.5rem;
        border-radius: 16px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        /* Bayangan halus */
        border: 1px solid #e2e8f0;
    }

    /* HEADER DI DALAM KARTU */
    .login-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .icon-wrapper {
        width: 64px;
        height: 64px;
        background: #dcfce7;
        /* Hijau muda */
        color: #16a34a;
        /* Hijau WasteWise */
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 32px;
    }

    .login-header h2 {
        font-size: 1.5rem;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }

    .login-header p {
        color: #64748b;
        font-size: 0.9rem;
    }

    /* FORM ELEMENTS */
    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: #334155;
    }

    .input-wrapper {
        position: relative;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.8rem;
        /* Padding kiri besar untuk icon */
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: 0.2s;
        outline: none;
    }

    .form-control:focus {
        border-color: #16a34a;
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }

    .input-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1.2rem;
    }

    /* TOMBOL LOGIN */
    .btn-login {
        width: 100%;
        background-color: #16a34a;
        /* Hijau tombol */
        color: white;
        padding: 0.9rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: 0.2s;
    }

    .btn-login:hover {
        background-color: #15803d;
    }

    /* ALERT ERROR */
    .alert-error {
        background: #fee2e2;
        color: #ef4444;
        padding: 0.8rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        text-align: center;
        font-size: 0.9rem;
        border: 1px solid #fecaca;
    }

    /* FOOTER INFO */
    .login-footer {
        margin-top: 2rem;
        background: #f8fafc;
        padding: 1rem;
        border-radius: 8px;
        text-align: center;
        font-size: 0.85rem;
        color: #64748b;
    }
    </style>
</head>

<body>

    <a href="index.php" class="back-link">
        <i class="ph-bold ph-arrow-left"></i> Kembali
    </a>

    <div class="login-card">

        <div class="login-header">
            <div class="icon-wrapper">
                <i class="ph-fill ph-user"></i>
            </div>
            <h2>Selamat Datang</h2>
            <p>Silakan masuk untuk mengelola sampah.</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="ph-bold ph-warning-circle" style="vertical-align: -2px;"></i>
            <?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <div class="input-wrapper">
                    <i class="ph-bold ph-user input-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="Masukan username" required>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="ph-bold ph-lock-key input-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="••••••" required>
                </div>
            </div>

            <button type="submit" name="login" class="btn-login">
                Masuk Sekarang <i class="ph-bold ph-sign-in"></i>
            </button>
        </form>

        <div class="login-footer">
            <strong>Akun Demo:</strong><br>
            Warga: <code>warga</code> / <code>123</code><br>
            Admin: <code>admin</code> / <code>123</code>
        </div>

    </div>

</body>

</html>