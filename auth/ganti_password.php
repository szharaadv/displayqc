<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password — QC Display Yanmar</title>
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
                    <span class="brand-sub">Yanmar · Ganti Password</span>
                </div>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <?php if ($_GET['error'] == 1): ?>
                    <div class="error">NIK tidak ditemukan.</div>
                <?php elseif ($_GET['error'] == 2): ?>
                    <div class="error">Password lama salah.</div>
                <?php elseif ($_GET['error'] == 3): ?>
                    <div class="error">Password baru dan konfirmasi tidak cocok.</div>
                <?php elseif ($_GET['error'] == 4): ?>
                    <div class="error">Password baru tidak boleh kosong.</div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="success">Password berhasil diubah! Silakan login.</div>
            <?php endif; ?>

            <form action="proses_ganti_password.php" method="POST">
                <div class="form-group">
                    <label class="form-label">NIK</label>
                    <input type="text" name="nik" class="form-input" placeholder="Masukkan NIK" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Password Lama</label>
                    <input type="password" name="password_lama" class="form-input" placeholder="Masukkan password lama" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password_baru" class="form-input" placeholder="Masukkan password baru" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <input type="password" name="password_konfirmasi" class="form-input" placeholder="Ulangi password baru" required>
                </div>
                <button type="submit" class="btn-login">GANTI PASSWORD</button>
            </form>

            <div class="login-footer">
                Batal? <a href="login.php">Kembali ke Login</a>
            </div>
        </div>
    </div>
</body>
</html>