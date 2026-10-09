<?php
session_start();
include '../config/csrf.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — QC Display Yanmar</title>
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
                    <span class="brand-sub">Yanmar · Change Password</span>
                </div>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <?php if ($_GET['error'] == 1): ?>
                    <div class="error">NIK not found.</div>
                <?php elseif ($_GET['error'] == 2): ?>
                    <div class="error">Current password is incorrect.</div>
                <?php elseif ($_GET['error'] == 3): ?>
                    <div class="error">New password and confirmation do not match.</div>
                <?php elseif ($_GET['error'] == 4): ?>
                    <div class="error">New password cannot be empty.</div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="success">Password changed successfully! Please sign in.</div>
            <?php endif; ?>

            <form action="proses_ganti_password.php" method="POST">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label class="form-label">NIK</label>
                    <input type="text" name="nik" class="form-input" placeholder="Enter NIK" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="password_lama" class="form-input" placeholder="Enter current password" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password_baru" class="form-input" placeholder="Enter new password" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="password_konfirmasi" class="form-input" placeholder="Repeat new password" required>
                </div>
                <button type="submit" class="btn-login">CHANGE PASSWORD</button>
            </form>

            <div class="login-footer">
                Cancel? <a href="login.php">Back to Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>