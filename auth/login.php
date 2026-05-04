<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — QC Display Yanmar</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-brand">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                </div>
                <div class="brand-text">
                    <span class="brand-name">QC Display</span>
                    <span class="brand-sub">Yanmar · Manufacturing</span>
                </div>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="error">NIK atau password salah.</div>
            <?php endif; ?>

            <form action="proses_login.php" method="POST">
                <div class="form-group">
                    <label class="form-label">NIK</label>
                    <input type="text" name="nik" class="form-input" placeholder="Masukkan NIK" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="btn-login">SIGN IN</button>
            </form>

            <div class="login-footer">
                <a href="ganti_password.php">Ganti Password</a>
            </div>
        </div>
    </div>
</body>
</html>